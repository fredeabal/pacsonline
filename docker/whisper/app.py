import os
import tempfile
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from faster_whisper import WhisperModel

MODEL_NAME = os.getenv("WHISPER_MODEL", "small")
CPU_THREADS = int(os.getenv("WHISPER_THREADS", "4"))
DOWNLOAD_ROOT = os.getenv("WHISPER_CACHE_DIR", "/models")
DEFAULT_PROMPT = (
    "Informe radiológico médico en español. Hallazgos, conclusión, "
    "parénquima pulmonar, silueta cardiomediastínica, senos costofrénicos, "
    "derrame pleural, consolidaciones, lesiones óseas."
)

app = FastAPI(title="PACS Local Whisper")
_model: WhisperModel | None = None


def get_model() -> WhisperModel:
    global _model
    if _model is None:
        _model = WhisperModel(
            MODEL_NAME,
            device="cpu",
            compute_type="int8",
            cpu_threads=CPU_THREADS,
            download_root=DOWNLOAD_ROOT,
        )
    return _model


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME, "loaded": _model is not None}


@app.post("/transcribe")
async def transcribe(
    file: UploadFile = File(...),
    language: str = Form("es"),
):
    data = await file.read()
    if not data:
        raise HTTPException(status_code=400, detail="Archivo de audio vacío.")

    suffix = os.path.splitext(file.filename or "audio.webm")[1] or ".webm"
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            tmp.write(data)
            tmp_path = tmp.name

        model = get_model()
        segments, _info = model.transcribe(
            tmp_path,
            language=language or "es",
            beam_size=1,
            vad_filter=True,
            initial_prompt=DEFAULT_PROMPT,
        )
        text = " ".join(seg.text.strip() for seg in segments if seg.text.strip()).strip()
        return {"text": text}
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Error transcribiendo audio: {exc}") from exc
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.remove(tmp_path)
