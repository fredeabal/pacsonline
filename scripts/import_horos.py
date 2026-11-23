#!/usr/bin/env python3
"""
Resumable, low-memory Horos/OsiriX -> Orthanc DICOM importer.
Streams .dcm files from DATABASE.noindex shard directories (newest first)
with bounded in-flight futures and uploads them to Orthanc (/instances).
"""

import concurrent.futures
import errno
import http.client
import os
import sys
import threading
import time
from pathlib import Path
from urllib.parse import urlparse

HOROS_DB_DIR = Path(os.environ.get("HOROS_DB_DIR", "/mnt/osirix-ro/Horos Data/DATABASE.noindex"))
ORTHANC_URL = os.environ.get("ORTHANC_URL", "http://orthanc:8042").rstrip("/")
STATE_FILE = Path(os.environ.get("STATE_FILE", "/data/import_state.txt"))
ERROR_LOG = Path(os.environ.get("ERROR_LOG", "/data/import_errors.log"))
WORKERS = int(os.environ.get("WORKERS", "2"))
LIMIT_SHARDS = int(os.environ.get("LIMIT_SHARDS", "0"))
MAX_FILE_BYTES = int(os.environ.get("MAX_FILE_MB", "256")) * 1024 * 1024

_PARSED_URL = urlparse(ORTHANC_URL)
_ORTHANC_HOST = _PARSED_URL.hostname or "orthanc"
_ORTHANC_PORT = _PARSED_URL.port or 8042
_BASE_PATH = _PARSED_URL.path.rstrip("/")
_THREAD_LOCAL = threading.local()
_ERROR_LOCK = threading.Lock()
_MOUNT_LOST = threading.Event()


def load_done_shards() -> set[str]:
    if not STATE_FILE.exists():
        return set()
    return {line.strip() for line in STATE_FILE.read_text(encoding="utf-8").splitlines() if line.strip()}


def mark_shard_done(shard_name: str) -> None:
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    with STATE_FILE.open("a", encoding="utf-8") as f:
        f.write(f"{shard_name}\n")


def log_error(path: Path, reason: str) -> None:
    with _ERROR_LOCK:
        ERROR_LOG.parent.mkdir(parents=True, exist_ok=True)
        with ERROR_LOG.open("a", encoding="utf-8") as f:
            f.write(f"{path}\t{reason}\n")


def _get_conn() -> http.client.HTTPConnection:
    conn = getattr(_THREAD_LOCAL, "conn", None)
    if conn is None:
        conn = http.client.HTTPConnection(_ORTHANC_HOST, _ORTHANC_PORT, timeout=60)
        _THREAD_LOCAL.conn = conn
    return conn


def _reset_conn() -> None:
    conn = getattr(_THREAD_LOCAL, "conn", None)
    if conn is not None:
        try:
            conn.close()
        except Exception:
            pass
        _THREAD_LOCAL.conn = None


def upload_dicom(path: Path) -> bool:
    if _MOUNT_LOST.is_set():
        return False
    try:
        size = path.stat().st_size
        if size == 0:
            log_error(path, "skipped_empty")
            return False
        if size > MAX_FILE_BYTES:
            log_error(path, f"skipped_too_large ({size} bytes)")
            return False

        data = path.read_bytes()
        for attempt in range(2):
            try:
                conn = _get_conn()
                conn.request(
                    "POST",
                    f"{_BASE_PATH}/instances",
                    body=data,
                    headers={"Content-Type": "application/dicom", "Connection": "keep-alive"},
                )
                resp = conn.getresponse()
                resp.read()
                if resp.status >= 400:
                    raise RuntimeError(f"HTTP {resp.status} {resp.reason}")
                return True
            except Exception:
                _reset_conn()
                if attempt == 1:
                    raise
        return False
    except OSError as exc:
        if exc.errno in (errno.ENOTCONN, errno.EIO, errno.ESTALE):
            _MOUNT_LOST.set()
        log_error(path, str(exc))
        return False
    except Exception as exc:
        log_error(path, str(exc))
        return False


def list_shard_dirs() -> list[Path]:
    dirs = [p for p in HOROS_DB_DIR.iterdir() if p.is_dir() and not p.name.startswith(".")]
    dirs.sort(key=lambda p: (0, int(p.name)) if p.name.isdigit() else (1, p.name), reverse=True)
    return dirs


def _raise_walk_error(err: OSError) -> None:
    if err.errno in (errno.ENOTCONN, errno.EIO, errno.ESTALE):
        _MOUNT_LOST.set()
    raise err


def iter_dcm_files(shard: Path):
    for root, _, filenames in os.walk(shard, onerror=_raise_walk_error):
        if _MOUNT_LOST.is_set():
            break
        for name in filenames:
            if name.lower().endswith(".dcm"):
                yield Path(root) / name


def process_shard(pool: concurrent.futures.ThreadPoolExecutor, shard: Path, max_in_flight: int) -> tuple[int, int]:
    ok = 0
    err = 0
    in_flight: set[concurrent.futures.Future[bool]] = set()

    for path in iter_dcm_files(shard):
        if _MOUNT_LOST.is_set():
            break
        in_flight.add(pool.submit(upload_dicom, path))
        if len(in_flight) >= max_in_flight:
            done, in_flight = concurrent.futures.wait(
                in_flight, return_when=concurrent.futures.FIRST_COMPLETED
            )
            for fut in done:
                if fut.result():
                    ok += 1
                else:
                    err += 1
            if (ok + err) % 500 == 0:
                print(f"  [shard={shard.name}] progress ok={ok} err={err}", flush=True)

    if in_flight:
        done, _ = concurrent.futures.wait(in_flight)
        for fut in done:
            if fut.result():
                ok += 1
            else:
                err += 1

    return ok, err


def main() -> int:
    if not HOROS_DB_DIR.is_dir():
        print(f"[ERROR] HOROS_DB_DIR not found: {HOROS_DB_DIR}", file=sys.stderr)
        return 1

    done = load_done_shards()
    shards = [s for s in list_shard_dirs() if s.name not in done]
    if LIMIT_SHARDS > 0:
        shards = shards[:LIMIT_SHARDS]

    print(f"[INFO] Pending shards: {len(shards)} (already done: {len(done)}, workers: {WORKERS})", flush=True)

    total_ok = 0
    total_err = 0
    max_in_flight = max(2, WORKERS * 2)

    with concurrent.futures.ThreadPoolExecutor(max_workers=WORKERS) as pool:
        for idx, shard in enumerate(shards, start=1):
            t0 = time.monotonic()
            try:
                ok, err = process_shard(pool, shard, max_in_flight)
            except OSError as exc:
                print(
                    f"[FATAL] Source mount error on shard={shard.name}: {exc}. "
                    f"Stopping without marking shard done.",
                    file=sys.stderr,
                    flush=True,
                )
                return 3

            total_ok += ok
            total_err += err
            elapsed = time.monotonic() - t0

            if _MOUNT_LOST.is_set():
                print(
                    f"[FATAL] Source mount disconnected during shard={shard.name} "
                    f"(ok={ok}, err={err}). Stopping without marking shard done.",
                    file=sys.stderr,
                    flush=True,
                )
                return 3

            mark_shard_done(shard.name)

            print(
                f"[{idx}/{len(shards)}] shard={shard.name} files={ok + err} ok={ok} err={err} "
                f"time={elapsed:.1f}s (total_ok={total_ok}, total_err={total_err})",
                flush=True,
            )

    print(f"[DONE] total_ok={total_ok} total_err={total_err}", flush=True)
    return 0 if total_err == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
