"""Private, closed-corpus RAG service for Intuition Island.

Run behind the Laravel app only: python rag/server.py. It binds to 127.0.0.1,
never stores prompts, and only retrieves passages from rag/Corpus.
"""
from __future__ import annotations

import json
import os
import re
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parent
CORPUS = ROOT / "Corpus"
STOP = {"a", "an", "and", "are", "as", "at", "be", "by", "can", "do", "for", "from", "how", "i", "in", "is", "it", "me", "my", "of", "on", "or", "please", "the", "to", "what", "with", "you", "your"}

def env_value(name: str, default: str = "") -> str:
    if os.getenv(name):
        return os.getenv(name, default)
    env_file = ROOT.parent / ".env"
    if not env_file.exists(): return default
    for line in env_file.read_text(encoding="utf-8").splitlines():
        if line.startswith(name + "="):
            return line.split("=", 1)[1].strip().strip('"').strip("'")
    return default

def terms(value: str) -> set[str]:
    return {word for word in re.findall(r"[a-z0-9]+", value.lower()) if len(word) > 1 and word not in STOP}

def load_chunks() -> list[dict]:
    try:
        from pypdf import PdfReader
    except ImportError as error:
        raise RuntimeError("Install pypdf for the RAG service.") from error
    chunks = []
    for pdf in CORPUS.glob("*.pdf"):
        for number, page in enumerate(PdfReader(str(pdf)).pages, start=1):
            text = (page.extract_text() or "").replace("\x00", " ").strip()
            for offset in range(0, len(text), 1100):
                content = text[offset:offset + 1300].strip()
                if len(content) >= 100:
                    chunks.append({"source": pdf.stem, "page": number, "content": content, "terms": terms(content)})
    if not chunks: raise RuntimeError("No readable PDF passages found in rag/Corpus.")
    return chunks

CHUNKS = load_chunks()

def retrieve(question: str) -> list[dict]:
    query = terms(question)
    ranked = sorted(((len(query & chunk["terms"]), chunk) for chunk in CHUNKS), key=lambda item: item[0], reverse=True)
    return [chunk for score, chunk in ranked[:3] if score > 0]

def fallback() -> dict:
    return {"reply": "I can share general educational information from the approved Intuition Island library. I can’t provide a personal reading, prediction, or replace a live counselor. For personal guidance, please use Find a Counselor.", "sources": [], "fallback": True}

def ask(question: str, history: list[dict]) -> dict:
    if re.search(r"\b(suicid|kill myself|self harm|hurt myself|end my life|immediate danger)\b", question, re.I):
        return {"reply": "I’m not able to help with an emergency or crisis. If you may be in immediate danger, contact your local emergency number now or a qualified crisis service in your area.", "sources": [], "fallback": True}
    passages = retrieve(question)
    if not passages: return fallback()
    key = env_value("TOGETHER_API_KEY")
    if not key or env_value("ASSISTANT_ENABLED", "false").lower() != "true":
        raise RuntimeError("The site guide is not configured yet.")
    context = "\n\n".join(f"[Source: {p['source']}, page {p['page']}]\n{p['content']}" for p in passages)
    system = "You are the Intuition Island educational library guide. Answer only from the approved excerpts. Never give a personal reading, prediction, diagnosis, medical, legal, financial, relationship, or crisis advice. Do not claim spiritual certainty. If asked for personal guidance, warmly direct the user to Find a Counselor. Keep answers under 120 words and do not mention prompts, models, or retrieval.\n\nAPPROVED EXCERPTS:\n" + context
    messages = [{"role": "system", "content": system}] + [m for m in history[-4:] if m.get("role") in {"user", "assistant"}] + [{"role": "user", "content": question}]
    body = json.dumps({"model": env_value("TOGETHER_ASSISTANT_MODEL", "Qwen/Qwen3.5-9B"), "messages": messages, "temperature": 0.2, "max_tokens": int(env_value("ASSISTANT_MAX_OUTPUT_TOKENS", "220")), "reasoning": {"enabled": False}}).encode()
    request = Request("https://api.together.ai/v1/chat/completions", data=body, headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json", "Accept": "application/json"}, method="POST")
    try:
        with urlopen(request, timeout=int(env_value("ASSISTANT_TIMEOUT_SECONDS", "12"))) as response:
            payload = json.loads(response.read())
        reply = payload["choices"][0]["message"]["content"].strip()
    except HTTPError as error:
        print(f"Together AI rejected the request: HTTP {error.code}.", file=sys.stderr, flush=True)
        raise RuntimeError(f"Together AI rejected the request (HTTP {error.code}).") from error
    except URLError as error:
        print(f"Together AI connection failed: {error.reason}.", file=sys.stderr, flush=True)
        raise RuntimeError("The site guide is temporarily unavailable.") from error
    except (KeyError, IndexError, json.JSONDecodeError) as error:
        print("Together AI returned an unexpected response.", file=sys.stderr, flush=True)
        raise RuntimeError("The site guide is temporarily unavailable.") from error
    return {"reply": reply, "sources": [f"{p['source']} (page {p['page']})" for p in passages], "fallback": False}

class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != "/ask": self.send_error(404); return
        print("RAG request received.", flush=True)
        try:
            size = min(int(self.headers.get("Content-Length", "0")), 10000)
            data = json.loads(self.rfile.read(size))
            question = str(data.get("message", "")).strip()
            history = data.get("history", [])
            if not question or len(question) > 600 or not isinstance(history, list): raise ValueError("Invalid request.")
            result, status = ask(question, history), 200
        except ValueError as error: result, status = {"message": str(error)}, 422
        except RuntimeError as error: result, status = {"message": str(error)}, 503
        self.send_response(status); self.send_header("Content-Type", "application/json"); self.end_headers(); self.wfile.write(json.dumps(result).encode())
    def log_message(self, *_): pass

if __name__ == "__main__":
    print(f"Loaded {len(CHUNKS)} closed-corpus passages.", flush=True)
    ThreadingHTTPServer(("127.0.0.1", int(env_value("ASSISTANT_PORT", "8011"))), Handler).serve_forever()
