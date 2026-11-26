#!/usr/bin/env python3
"""
Standalone TTS helper invoked by score-engine or directly by Laravel Process.
Reads JSON {text, voice, rate, pitch} from stdin and writes JSON MP3 payload to stdout.
Tries Microsoft Edge Neural TTS first, then falls back cleanly to gTTS.
"""
import asyncio
import base64
import io
import json
import re
import sys

try:
    import edge_tts
except ImportError:
    edge_tts = None

try:
    from gtts import gTTS
except ImportError:
    gTTS = None


ALLOWED_VOICES = {
    "en-IN-PrabhatNeural",
    "en-IN-NeerjaNeural",
    "hi-IN-MadhurNeural",
    "hi-IN-SwaraNeural",
    "en-GB-RyanNeural",
    "en-AU-WilliamNeural",
}


async def synthesize_edge(text: str, voice: str, rate: str, pitch: str):
    if edge_tts is None:
        raise RuntimeError("edge-tts missing")

    try:
        comm = edge_tts.Communicate(text, voice, rate=rate, pitch=pitch, boundary="WordBoundary")
    except TypeError:
        comm = edge_tts.Communicate(text, voice, rate=rate, pitch=pitch)

    audio_chunks = []
    boundaries = []
    async for chunk in comm.stream():
        ctype = chunk.get("type")
        if ctype == "audio" and chunk.get("data"):
            audio_chunks.append(chunk["data"])
        elif ctype == "WordBoundary":
            boundaries.append({
                "offset_ms": round(float(chunk.get("offset", 0)) / 10000.0),
                "duration_ms": max(80, round(float(chunk.get("duration", 0)) / 10000.0)),
                "text": str(chunk.get("text", "")),
            })

    audio_bytes = b"".join(audio_chunks)
    if not audio_bytes:
        raise RuntimeError("edge-tts returned empty audio")

    return audio_bytes, boundaries, "edge-tts"


def synthesize_gtts(text: str, voice: str):
    if gTTS is None:
        raise RuntimeError("gTTS missing")

    is_hindi = voice.startswith("hi-") or bool(re.search(r"[\u0900-\u097F]", text))
    lang = "hi" if is_hindi else "en"
    tld = "com" if is_hindi else ("co.uk" if voice.startswith("en-GB") else "co.in")

    buf = io.BytesIO()
    tts = gTTS(text=text, lang=lang, tld=tld, slow=False)
    tts.write_to_fp(buf)
    audio_bytes = buf.getvalue()
    if not audio_bytes:
        raise RuntimeError("gTTS returned empty audio")

    return audio_bytes, [], "gtts"


async def synthesize_best(text: str, voice: str = "hi-IN-MadhurNeural", rate: str = "+5%", pitch: str = "+0Hz"):
    clean_text = re.sub(r"\s+", " ", (text or "")).strip()
    if not clean_text:
        raise ValueError("Narration text cannot be empty.")

    safe_voice = (voice or "hi-IN-MadhurNeural").strip()
    if safe_voice not in ALLOWED_VOICES and not re.match(r"^[a-z]{2}-[A-Z]{2}-[A-Za-z0-9]+Neural$", safe_voice):
        safe_voice = "hi-IN-MadhurNeural"

    safe_rate = (rate or "+5%").strip()
    if not re.match(r"^[+-](?:[0-9]|[1-4][0-9]|50)%$", safe_rate):
        safe_rate = "+5%"

    safe_pitch = (pitch or "+0Hz").strip()
    if not re.match(r"^[+-](?:[0-9]|[12][0-9]|30)Hz$", safe_pitch):
        safe_pitch = "+0Hz"

    errors = []
    if edge_tts is not None:
        try:
            audio_bytes, boundaries, engine = await asyncio.wait_for(
                synthesize_edge(clean_text, safe_voice, safe_rate, safe_pitch),
                timeout=12.0,
            )
            return {
                "status": "success",
                "engine": engine,
                "voice": safe_voice,
                "mime_type": "audio/mpeg",
                "audio_base64": base64.b64encode(audio_bytes).decode("ascii"),
                "boundaries": boundaries,
            }
        except Exception as exc:
            errors.append(f"edge: {exc}")

    if gTTS is not None:
        try:
            audio_bytes, boundaries, engine = await asyncio.to_thread(synthesize_gtts, clean_text, safe_voice)
            return {
                "status": "success",
                "engine": engine,
                "voice": safe_voice,
                "mime_type": "audio/mpeg",
                "audio_base64": base64.b64encode(audio_bytes).decode("ascii"),
                "boundaries": boundaries,
            }
        except Exception as exc:
            errors.append(f"gtts: {exc}")

    raise RuntimeError("; ".join(errors) or "No TTS backend available.")


if __name__ == "__main__":
    try:
        raw = sys.stdin.read()
        payload = json.loads(raw or "{}")
        result = asyncio.run(
            synthesize_best(
                payload.get("text", ""),
                payload.get("voice", "hi-IN-MadhurNeural"),
                payload.get("rate", "+5%"),
                payload.get("pitch", "+0Hz"),
            )
        )
        print(json.dumps(result))
    except Exception as exc:
        print(json.dumps({"status": "error", "message": str(exc)[:250]}))
        sys.exit(1)
