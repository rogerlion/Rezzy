#!/usr/bin/env python3
"""Small OpenAI-compatible embedding server for GEOFlow.

This intentionally avoids external dependencies and API keys. It uses a
deterministic feature-hashing vectorizer over Chinese characters, word tokens,
and short n-grams. It is a lightweight production fallback, not a replacement
for a trained semantic embedding model.
"""

from __future__ import annotations

import hashlib
import json
import math
import re
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any


DEFAULT_DIMENSIONS = 1536
MAX_DIMENSIONS = 3072
TOKEN_RE = re.compile(r"[\u4e00-\u9fff]|[a-z0-9]+", re.IGNORECASE)


def tokens_for(text: str) -> list[str]:
    normalized = (text or "").lower().strip()
    if not normalized:
        return []

    base_tokens = TOKEN_RE.findall(normalized)
    chinese_chars = [token for token in base_tokens if len(token) == 1 and "\u4e00" <= token <= "\u9fff"]
    grams: list[str] = []
    for size in (2, 3):
        grams.extend("".join(chinese_chars[index : index + size]) for index in range(max(0, len(chinese_chars) - size + 1)))

    word_grams: list[str] = []
    words = [token for token in base_tokens if len(token) > 1]
    for size in (2, 3):
        word_grams.extend("_".join(words[index : index + size]) for index in range(max(0, len(words) - size + 1)))

    return base_tokens + grams + word_grams


def add_feature(vector: list[float], token: str, weight: float) -> None:
    digest = hashlib.blake2b(token.encode("utf-8"), digest_size=8).digest()
    bucket = int.from_bytes(digest[:4], "big") % len(vector)
    sign = 1.0 if digest[4] & 1 else -1.0
    vector[bucket] += sign * weight


def embed(text: str, dimensions: int) -> list[float]:
    dimensions = max(8, min(int(dimensions or DEFAULT_DIMENSIONS), MAX_DIMENSIONS))
    vector = [0.0] * dimensions
    tokens = tokens_for(text)
    if not tokens:
        vector[0] = 1.0
        return vector

    frequencies: dict[str, int] = {}
    for token in tokens:
        frequencies[token] = frequencies.get(token, 0) + 1

    for token, count in frequencies.items():
        length_boost = 1.35 if len(token) >= 2 else 1.0
        add_feature(vector, token, (1.0 + math.log(count)) * length_boost)

    norm = math.sqrt(sum(value * value for value in vector))
    if norm <= 0:
        vector[0] = 1.0
        return vector

    return [round(value / norm, 8) for value in vector]


class Handler(BaseHTTPRequestHandler):
    server_version = "geoflow-local-embedding/1.0"

    def log_message(self, fmt: str, *args: Any) -> None:
        return

    def do_GET(self) -> None:
        if self.path in {"/", "/health", "/v1/health"}:
            self.send_json({"status": "ok"})
            return
        self.send_error(404)

    def do_POST(self) -> None:
        if self.path not in {"/embeddings", "/v1/embeddings"}:
            self.send_error(404)
            return

        length = int(self.headers.get("Content-Length", "0") or 0)
        try:
            payload = json.loads(self.rfile.read(length).decode("utf-8"))
        except Exception:
            self.send_json({"error": {"message": "invalid JSON request"}}, status=400)
            return

        raw_inputs = payload.get("input", [])
        if isinstance(raw_inputs, str):
            inputs = [raw_inputs]
        elif isinstance(raw_inputs, list):
            inputs = [str(item) for item in raw_inputs]
        else:
            self.send_json({"error": {"message": "input must be a string or list"}}, status=400)
            return

        dimensions = int(payload.get("dimensions") or DEFAULT_DIMENSIONS)
        model = str(payload.get("model") or "geoflow-local-hash-embedding")
        data = [
            {
                "object": "embedding",
                "index": index,
                "embedding": embed(text, dimensions),
            }
            for index, text in enumerate(inputs)
        ]
        token_count = sum(max(1, len(text)) for text in inputs)
        self.send_json(
            {
                "object": "list",
                "data": data,
                "model": model,
                "usage": {
                    "prompt_tokens": token_count,
                    "total_tokens": token_count,
                },
            }
        )

    def send_json(self, payload: dict[str, Any], status: int = 200) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


if __name__ == "__main__":
    ThreadingHTTPServer(("0.0.0.0", 8000), Handler).serve_forever()
