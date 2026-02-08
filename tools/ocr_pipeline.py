#!/usr/bin/env python3
"""OCR + layout pipeline for PDFs and page images."""

import argparse
import datetime as dt
import json
import os
import shutil
import subprocess
import sys
import tempfile
from typing import Dict, List, Optional, Tuple

try:
    import cv2  # type: ignore
except Exception:
    print("Error: OpenCV (cv2) is required. Install with: pip install opencv-python", file=sys.stderr)
    sys.exit(1)

try:
    import numpy as np  # type: ignore
except Exception:
    print("Error: numpy is required. Install with: pip install numpy", file=sys.stderr)
    sys.exit(1)

try:
    from pdf2image import convert_from_path  # type: ignore
except Exception:
    convert_from_path = None

try:
    import pytesseract  # type: ignore
except Exception:
    pytesseract = None

try:
    from PIL import Image  # type: ignore
except Exception:
    Image = None

ALLOWED_IMAGE_EXTS = {".png", ".jpg", ".jpeg", ".tif", ".tiff", ".webp"}
ALLOWED_PDF_EXTS = {".pdf"}


def ensure_dir(path: str) -> None:
    os.makedirs(path, exist_ok=True)


def list_inputs(input_dir: str) -> List[str]:
    if not os.path.isdir(input_dir):
        return []
    files = []
    for name in sorted(os.listdir(input_dir)):
        path = os.path.join(input_dir, name)
        if not os.path.isfile(path):
            continue
        ext = os.path.splitext(name)[1].lower()
        if ext in ALLOWED_IMAGE_EXTS or ext in ALLOWED_PDF_EXTS:
            files.append(path)
    return files


def load_image(path: str) -> np.ndarray:
    img = cv2.imread(path, cv2.IMREAD_COLOR)
    if img is not None:
        return img
    if Image is None:
        raise RuntimeError("Failed to load image; Pillow is not installed.")
    pil = Image.open(path).convert("RGB")
    return cv2.cvtColor(np.array(pil), cv2.COLOR_RGB2BGR)


def save_image(path: str, img: np.ndarray) -> None:
    ok = cv2.imwrite(path, img)
    if ok:
        return
    if Image is None:
        raise RuntimeError("Failed to write image; Pillow is not installed.")
    rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    Image.fromarray(rgb).save(path)


def convert_pdf_to_images(pdf_path: str, dpi: int, temp_dir: str) -> List[np.ndarray]:
    images: List[np.ndarray] = []
    if convert_from_path is not None:
        try:
            pages = convert_from_path(pdf_path, dpi=dpi)
            for page in pages:
                rgb = np.array(page.convert("RGB"))
                bgr = cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)
                images.append(bgr)
            return images
        except Exception:
            images = []
    # Fallback to pdftoppm
    if shutil.which("pdftoppm") is None:
        raise RuntimeError("pdf2image not available and pdftoppm not found in PATH.")
    prefix = os.path.join(temp_dir, "page")
    cmd = ["pdftoppm", "-r", str(dpi), "-png", pdf_path, prefix]
    subprocess.run(cmd, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    generated = sorted(
        f for f in os.listdir(temp_dir) if f.startswith("page-") and f.endswith(".png")
    )
    for name in generated:
        path = os.path.join(temp_dir, name)
        images.append(load_image(path))
    return images


def compute_skew_angle(gray: np.ndarray) -> float:
    edges = cv2.Canny(gray, 50, 150)
    lines = cv2.HoughLines(edges, 1, np.pi / 180.0, 200)
    if lines is None:
        return 0.0
    angles = []
    for rho, theta in lines[:, 0]:
        angle = (theta - np.pi / 2.0) * 180.0 / np.pi
        if abs(angle) <= 15:
            angles.append(angle)
    if not angles:
        return 0.0
    return float(np.median(angles))


def rotate_image(gray: np.ndarray, angle: float) -> np.ndarray:
    h, w = gray.shape[:2]
    center = (w // 2, h // 2)
    m = cv2.getRotationMatrix2D(center, angle, 1.0)
    return cv2.warpAffine(
        gray, m, (w, h), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_CONSTANT, borderValue=255
    )


def preprocess_image(image: np.ndarray, profile: str) -> Tuple[np.ndarray, np.ndarray, np.ndarray, float]:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    work = gray
    scale = 1.0
    if profile == "ui":
        work = cv2.bilateralFilter(work, 9, 75, 75)
        scale = 2.0
        work = cv2.resize(work, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8)).apply(work)
        angle = compute_skew_angle(clahe)
        rotated = rotate_image(clahe, angle)
        thresh = cv2.adaptiveThreshold(
            rotated, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 31, 15
        )
    elif profile == "scanned":
        work = cv2.medianBlur(work, 5)
        scale = 1.5
        work = cv2.resize(work, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
        clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8)).apply(work)
        angle = compute_skew_angle(clahe)
        rotated = rotate_image(clahe, angle)
        _, thresh = cv2.threshold(rotated, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    else:
        work = cv2.medianBlur(work, 3)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8)).apply(work)
        angle = compute_skew_angle(clahe)
        rotated = rotate_image(clahe, angle)
        _, thresh = cv2.threshold(rotated, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

    white_ratio = float(np.sum(thresh == 255)) / float(thresh.size)
    if white_ratio > 0.5:
        binary_foreground = 255 - thresh
        binary_for_ocr = thresh
    else:
        binary_foreground = thresh
        binary_for_ocr = 255 - thresh

    return rotated, binary_for_ocr, binary_foreground, angle


def classify_page(binary_foreground: np.ndarray) -> Dict[str, float]:
    h, w = binary_foreground.shape
    num_labels, _, stats, _ = cv2.connectedComponentsWithStats(binary_foreground, connectivity=8)
    long_lines = 0
    char_like = 0
    total_components = max(0, num_labels - 1)

    for i in range(1, num_labels):
        x, y, cw, ch, area = stats[i]
        if cw <= 0 or ch <= 0:
            continue
        if cw > 0.5 * w and ch <= 5:
            long_lines += 1
        if 8 <= cw <= 200 and 8 <= ch <= 200 and 30 <= area <= 10000:
            char_like += 1

    classification = "text_page"
    if long_lines >= 15 or (long_lines >= 8 and char_like < 20):
        classification = "line_mask"

    return {
        "classification": classification,
        "long_lines": float(long_lines),
        "char_like": float(char_like),
        "components": float(total_components),
    }


def detect_lines(binary_foreground: np.ndarray) -> List[List[int]]:
    h, w = binary_foreground.shape
    row_sum = np.sum(binary_foreground > 0, axis=1)
    threshold = max(10, int(0.02 * w))

    lines = []
    in_run = False
    start = 0
    for y in range(h):
        active = row_sum[y] > threshold
        if active and not in_run:
            start = y
            in_run = True
        if not active and in_run:
            end = y - 1
            cols = np.where(np.any(binary_foreground[start:end + 1, :] > 0, axis=0))[0]
            if cols.size > 0:
                x0, x1 = int(cols[0]), int(cols[-1])
                lines.append([x0, int(start), x1, int(end)])
            in_run = False
    if in_run:
        end = h - 1
        cols = np.where(np.any(binary_foreground[start:end + 1, :] > 0, axis=0))[0]
        if cols.size > 0:
            x0, x1 = int(cols[0]), int(cols[-1])
            lines.append([x0, int(start), x1, int(end)])

    if lines:
        return lines

    # Fallback: contour based
    contours, _ = cv2.findContours(binary_foreground, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    for cnt in contours:
        x, y, cw, ch = cv2.boundingRect(cnt)
        if cw > 0.1 * w and ch <= 0.05 * h:
            lines.append([int(x), int(y), int(x + cw), int(y + ch)])
    return lines


def group_lines_to_blocks(lines: List[List[int]]) -> List[List[int]]:
    if not lines:
        return []
    lines_sorted = sorted(lines, key=lambda b: b[1])
    gaps = []
    for i in range(1, len(lines_sorted)):
        gap = lines_sorted[i][1] - lines_sorted[i - 1][3]
        if gap > 0:
            gaps.append(gap)
    median_gap = int(np.median(gaps)) if gaps else 10
    threshold = max(10, int(2.5 * median_gap))

    blocks = []
    current = [lines_sorted[0]]
    for line in lines_sorted[1:]:
        gap = line[1] - current[-1][3]
        if gap > threshold:
            blocks.append(_block_from_lines(current))
            current = [line]
        else:
            current.append(line)
    blocks.append(_block_from_lines(current))
    return blocks


def _block_from_lines(lines: List[List[int]]) -> List[int]:
    x0 = min(l[0] for l in lines)
    y0 = min(l[1] for l in lines)
    x1 = max(l[2] for l in lines)
    y1 = max(l[3] for l in lines)
    return [int(x0), int(y0), int(x1), int(y1)]


def detect_columns(lines: List[List[int]], width: int) -> bool:
    if len(lines) < 6:
        return False
    left = sum(1 for l in lines if l[0] < 0.4 * width)
    right = sum(1 for l in lines if l[0] > 0.6 * width)
    return left >= 3 and right >= 3


def ocr_with_pytesseract(image: np.ndarray, lang: str, oem: int, psm: int) -> Tuple[str, Optional[float]]:
    config = f"--oem {oem} --psm {psm}"
    text = pytesseract.image_to_string(image, lang=lang, config=config)
    data = pytesseract.image_to_data(image, lang=lang, config=config, output_type=pytesseract.Output.DICT)
    confs = []
    for c in data.get("conf", []):
        try:
            val = float(c)
        except Exception:
            continue
        if val >= 0:
            confs.append(val)
    avg_conf = float(np.mean(confs)) if confs else None
    return text, avg_conf


def ocr_with_tesseract_cli(image_path: str, lang: str, oem: int, psm: int) -> Tuple[str, Optional[float]]:
    cmd = ["tesseract", image_path, "stdout", "-l", lang, "--oem", str(oem), "--psm", str(psm)]
    proc = subprocess.run(cmd, check=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    text = proc.stdout

    cmd_tsv = cmd + ["tsv"]
    proc_tsv = subprocess.run(cmd_tsv, check=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    confs = []
    for line in proc_tsv.stdout.splitlines()[1:]:
        parts = line.split("\t")
        if len(parts) < 11:
            continue
        try:
            val = float(parts[10])
        except Exception:
            continue
        if val >= 0:
            confs.append(val)
    avg_conf = float(np.mean(confs)) if confs else None
    return text, avg_conf


def main() -> int:
    parser = argparse.ArgumentParser(description="OCR + layout pipeline for PDFs and page images")
    parser.add_argument("--input", default="input", help="Input folder with PDFs or images")
    parser.add_argument("--output", default="out", help="Output folder")
    parser.add_argument("--lang", default="eng", help="OCR language (default: eng)")
    parser.add_argument("--dpi", type=int, default=300, help="PDF render DPI (default: 300)")
    parser.add_argument("--oem", type=int, default=1, help="Tesseract OEM (default: 1)")
    parser.add_argument("--psm", type=int, default=None, help="Override PSM for OCR")
    parser.add_argument("--psm-columns", type=int, default=4, help="PSM for detected columns (default: 4)")
    parser.add_argument("--layout-only", action="store_true", help="Skip OCR and generate layout only")
    parser.add_argument(
        "--profile",
        choices=["default", "scanned", "ui"],
        default="default",
        help="Preprocessing profile: default, scanned, or ui",
    )
    args = parser.parse_args()

    input_dir = args.input
    output_dir = args.output

    inputs = list_inputs(input_dir)
    if not inputs:
        print(f"No inputs found in {input_dir}. Place PDFs or images there.")
        return 1

    out_pages = os.path.join(output_dir, "pages")
    out_clean = os.path.join(output_dir, "clean")
    out_layout = os.path.join(output_dir, "layout")
    out_text = os.path.join(output_dir, "text")
    out_quality = os.path.join(output_dir, "quality")
    ensure_dir(out_pages)
    ensure_dir(out_clean)
    ensure_dir(out_layout)
    ensure_dir(out_text)
    ensure_dir(out_quality)

    temp_dir = tempfile.mkdtemp(prefix="pdf_pages_", dir=output_dir)

    pages = []
    page_index = 1
    try:
        for path in inputs:
            ext = os.path.splitext(path)[1].lower()
            base = os.path.basename(path)
            if ext in ALLOWED_PDF_EXTS:
                try:
                    images = convert_pdf_to_images(path, args.dpi, temp_dir)
                except Exception as exc:
                    print(f"Failed to convert PDF {base}: {exc}", file=sys.stderr)
                    continue
                for idx, img in enumerate(images, start=1):
                    out_path = os.path.join(out_pages, f"page_{page_index:03d}.png")
                    save_image(out_path, img)
                    pages.append({
                        "page_index": page_index,
                        "source": f"{base}#page={idx}",
                        "page_path": out_path,
                    })
                    page_index += 1
            else:
                try:
                    img = load_image(path)
                except Exception as exc:
                    print(f"Failed to load image {base}: {exc}", file=sys.stderr)
                    continue
                out_path = os.path.join(out_pages, f"page_{page_index:03d}.png")
                save_image(out_path, img)
                pages.append({
                    "page_index": page_index,
                    "source": base,
                    "page_path": out_path,
                })
                page_index += 1
    finally:
        shutil.rmtree(temp_dir, ignore_errors=True)

    if not pages:
        print("No pages could be processed.")
        return 1

    tesseract_path = shutil.which("tesseract")
    ocr_available = tesseract_path is not None
    ocr_enabled = ocr_available and not args.layout_only

    report = {
        "meta": {
            "generated_at": dt.datetime.utcnow().isoformat() + "Z",
            "input_dir": os.path.abspath(input_dir),
            "output_dir": os.path.abspath(output_dir),
            "language": args.lang,
            "oem": args.oem,
            "psm_default": args.psm,
            "psm_columns": args.psm_columns,
            "ocr_engine": "pytesseract" if (pytesseract and ocr_available and not args.layout_only)
            else ("tesseract" if (ocr_available and not args.layout_only) else None),
            "tesseract_available": ocr_available,
            "layout_only": args.layout_only,
            "profile": args.profile,
            "version": "1.0",
        },
        "pages": [],
        "summary": {},
    }

    index_path = os.path.join(out_text, "index.txt")
    with open(index_path, "w", encoding="utf-8") as index_file:
        for page in pages:
            page_path = page["page_path"]
            page_name = os.path.basename(page_path)
            image = load_image(page_path)
            _, binary_for_ocr, binary_fore, angle = preprocess_image(image, args.profile)

            clean_path = os.path.join(out_clean, page_name)
            save_image(clean_path, binary_for_ocr)

            classification_info = classify_page(binary_fore)
            classification = classification_info["classification"]

            lines = detect_lines(binary_fore)
            blocks = group_lines_to_blocks(lines)

            h, w = binary_fore.shape
            foreground_ratio = float(np.sum(binary_fore > 0)) / float(binary_fore.size)
            density_stats = {
                "foreground_ratio": foreground_ratio,
                "line_count": len(lines),
                "block_count": len(blocks),
            }

            layout_path = os.path.join(out_layout, page_name.replace(".png", ".json"))
            layout_payload = {
                "page_size": {"width": int(w), "height": int(h)},
                "line_boxes": lines,
                "block_boxes": blocks,
                "skew_angle": angle,
                "density_stats": density_stats,
            }
            with open(layout_path, "w", encoding="utf-8") as lf:
                json.dump(layout_payload, lf, indent=2)

            ocr_used = False
            ocr_text_path = None
            avg_conf = None
            ocr_warning = None
            psm = None

            if classification == "text_page" and ocr_enabled:
                psm = args.psm
                if psm is None:
                    if detect_columns(lines, w):
                        psm = args.psm_columns
                    else:
                        psm = 6
                if pytesseract is not None:
                    text, avg_conf = ocr_with_pytesseract(binary_for_ocr, args.lang, args.oem, psm)
                    ocr_used = True
                elif tesseract_path is not None:
                    text, avg_conf = ocr_with_tesseract_cli(clean_path, args.lang, args.oem, psm)
                    ocr_used = True
                else:
                    text = ""
                    ocr_warning = "tesseract not available"

                if ocr_used:
                    ocr_text_path = os.path.join(out_text, page_name.replace(".png", ".txt"))
                    with open(ocr_text_path, "w", encoding="utf-8") as tf:
                        tf.write(text)

                    index_file.write(f"=== Page {page['page_index']:03d} ({page['source']}) ===\n")
                    index_file.write(text.strip() + "\n\n")
            elif classification == "text_page" and not ocr_enabled:
                if args.layout_only:
                    ocr_warning = "layout-only mode"
                elif not ocr_available:
                    ocr_warning = "tesseract not available"

            report["pages"].append({
                "page_index": page["page_index"],
                "source": page["source"],
                "page_path": page_path,
                "clean_path": clean_path,
                "layout_path": layout_path,
                "classification": classification,
                "skew_angle": angle,
                "classification_stats": classification_info,
                "density_stats": density_stats,
                "ocr": {
                    "used": ocr_used,
                    "psm": None if classification != "text_page" else psm,
                    "text_path": ocr_text_path,
                    "avg_confidence": avg_conf,
                    "warning": ocr_warning,
                },
            })

    total_pages = len(report["pages"])
    text_pages = sum(1 for p in report["pages"] if p["classification"] == "text_page")
    line_mask_pages = sum(1 for p in report["pages"] if p["classification"] == "line_mask")
    ocr_pages = sum(1 for p in report["pages"] if p["ocr"]["used"])
    confs = [p["ocr"]["avg_confidence"] for p in report["pages"] if p["ocr"]["avg_confidence"] is not None]
    avg_conf = float(np.mean(confs)) if confs else None

    report["summary"] = {
        "total_pages": total_pages,
        "text_pages": text_pages,
        "line_mask_pages": line_mask_pages,
        "ocr_pages": ocr_pages,
        "avg_confidence": avg_conf,
    }

    report_path = os.path.join(out_quality, "report.json")
    with open(report_path, "w", encoding="utf-8") as rf:
        json.dump(report, rf, indent=2)

    summary_path = os.path.join(out_quality, "summary.md")
    with open(summary_path, "w", encoding="utf-8") as sf:
        sf.write("# OCR Pipeline Summary\n\n")
        sf.write(f"Total pages: {total_pages}\n\n")
        sf.write(f"Text pages: {text_pages}\n\n")
        sf.write(f"Line-mask pages: {line_mask_pages}\n\n")
        sf.write(f"OCR pages: {ocr_pages}\n\n")
        sf.write(f"Average OCR confidence: {avg_conf if avg_conf is not None else 'n/a'}\n\n")
        if args.layout_only:
            sf.write("Mode: layout-only (OCR skipped)\n\n")
        elif not ocr_available:
            sf.write("Warning: tesseract not available; OCR skipped.\n\n")
        if text_pages == 0:
            sf.write("Warning: No text pages detected. Originals may be required for OCR.\n")

    if text_pages == 0:
        layout_only_path = os.path.join(output_dir, "layout_only.md")
        with open(layout_only_path, "w", encoding="utf-8") as lf:
            lf.write("# Layout-only output\n\n")
            lf.write("All pages were classified as line-mask images.\n\n")
            lf.write("OCR requires original pages with readable text.\n")

    print(f"Processed {total_pages} pages. Output in {output_dir}.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
