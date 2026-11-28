FROM python:3.11-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

RUN pip install --no-cache-dir \
    faster-whisper==1.1.0 \
    requests==2.32.3 \
    fastapi==0.115.6 \
    uvicorn==0.34.0 \
    python-multipart==0.0.20

WORKDIR /app
COPY app.py /app/app.py

EXPOSE 8000

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]
