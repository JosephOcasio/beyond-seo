#!/usr/bin/env python3
"""Direct text extraction for TXT/HTML (and PDF if pdftotext is available)."""

import argparse
import html
import os
import re
import subprocess
import sys
from html.parser import HTMLParser
from typing import List

ALLOWED_TEXT_EXTS = {".txt", ".html", ".htm"}
ALLOWED_PDF_EXTS = {".pdf"}


def ensure_dir(path: str) -> None:
    os.makedirs(path, exist_ok=True)


class _TextExtractor(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self._chunks: List[str] = []

    def handle_data(self, data: str) -> None:
        if data:
            self._chunks.append(data)

    def get_text(self) -> str:
        return " ".join(self._chunks)


def extract_html_text(path: str) -> str:
    with open(path, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()
    parser = _TextExtractor()
    parser.feed(content)
    text = parser.get_text()
    text = html.unescape(text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def extract_txt(path: str) -> str:
    with open(path, "r", encoding="utf-8", errors="replace") as f:
        return f.read().strip()


def extract_pdf(path: str, out_txt: str) -> bool:
    if not shutil_which("pdftotext"):
        return False
    cmd = ["pdftotext", "-layout", path, out_txt]
    proc = subprocess.run(cmd, check=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    return proc.returncode == 0


def shutil_which(cmd: str) -> str:
    for base in os.environ.get("PATH", "").split(os.pathsep):
        candidate = os.path.join(base, cmd)
        if os.path.isfile(candidate) and os.access(candidate, os.X_OK):
            return candidate
    return ""


def main() -> int:
    parser = argparse.ArgumentParser(description="Direct text extraction for TXT/HTML/PDF")
    parser.add_argument("--input", default="input", help="Input folder")
    parser.add_argument("--output", default="out/direct", help="Output folder")
    args = parser.parse_args()

    input_dir = args.input
    output_dir = args.output

    if not os.path.isdir(input_dir):
        print(f"Input folder not found: {input_dir}")
        return 1

    out_text = os.path.join(output_dir, "text")
    ensure_dir(out_text)

    index_path = os.path.join(output_dir, "index.txt")
    wrote_any = False

    with open(index_path, "w", encoding="utf-8") as index_file:
        for name in sorted(os.listdir(input_dir)):
            path = os.path.join(input_dir, name)
            if not os.path.isfile(path):
                continue
            ext = os.path.splitext(name)[1].lower()
            base = os.path.splitext(name)[0]
            if ext in ALLOWED_TEXT_EXTS:
                text = extract_txt(path) if ext == ".txt" else extract_html_text(path)
                out_path = os.path.join(out_text, f"{base}.txt")
                with open(out_path, "w", encoding="utf-8") as tf:
                    tf.write(text)
                index_file.write(f"=== {name} ===\n")
                index_file.write(text + "\n\n")
                wrote_any = True
            elif ext in ALLOWED_PDF_EXTS:
                out_path = os.path.join(out_text, f"{base}.txt")
                if extract_pdf(path, out_path):
                    with open(out_path, "r", encoding="utf-8", errors="replace") as tf:
                        text = tf.read().strip()
                    index_file.write(f"=== {name} ===\n")
                    index_file.write(text + "\n\n")
                    wrote_any = True

    if not wrote_any:
        print("No direct-text sources found (txt/html/pdf with text).")
        return 2

    print(f"Direct extraction complete. Output in {output_dir}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
