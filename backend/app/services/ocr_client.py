"""Wrapper around Master_Scribe_Final.py to perform OCR ingestion."""

from __future__ import annotations

import os
import subprocess
import tempfile
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[3]
DEFAULT_OCR_SCRIPT = PROJECT_ROOT / "Master_Scribe_Final.py"


class OCRClientError(RuntimeError):
    pass


def extract_text(
    file_path: str,
    *,
    project_id: str | None = None,
    model: str | None = None,
    locations: str | None = None,
    auth_mode: str | None = None,
    prompt: str | None = None,
    max_tokens: int | None = None,
) -> str:
    source = Path(file_path).expanduser().resolve()
    if not source.exists() or not source.is_file():
        raise OCRClientError(f"File not found: {source}")

    script = Path(os.getenv("MASTER_SCRIBE_SCRIPT", str(DEFAULT_OCR_SCRIPT))).expanduser().resolve()
    if not script.exists() or not script.is_file():
        raise OCRClientError(f"OCR script not found: {script}")

    tmp = tempfile.NamedTemporaryFile(prefix="ocr_", suffix=".txt", delete=False)
    tmp_path = Path(tmp.name)
    tmp.close()

    cmd: list[str] = [
        "python3",
        str(script),
        "--input",
        str(source),
        "--out",
        str(tmp_path),
        "--auth-mode",
        auth_mode or os.getenv("VERTEX_AUTH_MODE", "adc"),
        "--locations",
        locations or os.getenv("VERTEX_LOCATIONS", "global,us-central1"),
    ]

    selected_project_id = project_id or os.getenv("PROJECT_ID", "").strip()
    selected_model = model or os.getenv("DEEPSEEK_MODEL", "").strip()
    selected_prompt = prompt or os.getenv(
        "OCR_PROMPT",
        "Extract all text verbatim. Preserve all mathematical formulas and symbols exactly.",
    )

    if selected_project_id:
        cmd.extend(["--project-id", selected_project_id])
    if selected_model:
        cmd.extend(["--model", selected_model])
    if selected_prompt:
        cmd.extend(["--prompt", selected_prompt])
    if max_tokens is not None:
        cmd.extend(["--max-tokens", str(max_tokens)])

    proc = subprocess.run(
        cmd,
        cwd=str(PROJECT_ROOT),
        capture_output=True,
        text=True,
        env=os.environ.copy(),
    )

    try:
        if proc.returncode != 0:
            raise OCRClientError(
                "OCR command failed: "
                f"returncode={proc.returncode}; stdout={proc.stdout[-800:]}; stderr={proc.stderr[-800:]}"
            )

        if not tmp_path.exists():
            raise OCRClientError("OCR completed but output file was not created")

        text = tmp_path.read_text(encoding="utf-8").strip()
        if not text:
            raise OCRClientError("OCR output was empty")
        return text
    finally:
        try:
            tmp_path.unlink(missing_ok=True)
        except OSError:
            pass
