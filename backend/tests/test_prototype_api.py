from __future__ import annotations


def test_health_endpoint(client):
    resp = client.get('/api/prototype/health')
    assert resp.status_code == 200
    body = resp.get_json()
    assert body["status"] == "ok"


def test_evaluate_and_fetch_claim_text(client):
    payload = {
        "source_name": "constraint-first-systems-doc",
        "source_type": "text",
        "raw_text": (
            "E = mc^2\n"
            "For all n, sum_{i=1}^n i = n(n+1)/2\n"
            "\\int_0^1 x^2 dx = 1/3\n"
            "This line extends the technical context with enough words to avoid short-text hold."
        ),
        "metadata": {"origin": "pytest"},
        "min_admissibility": 0.2,
        "drift_tolerance": 0.5,
    }

    post_resp = client.post('/api/prototype/claims/evaluate', json=payload)
    assert post_resp.status_code == 201
    post_body = post_resp.get_json()
    assert post_body["claim_id"]
    assert "measurement" in post_body
    assert post_body["measurement"]["formula_count"] >= 1

    claim_id = post_body["claim_id"]
    get_resp = client.get(f'/api/prototype/claims/{claim_id}')
    assert get_resp.status_code == 200
    get_body = get_resp.get_json()
    assert get_body["claim_id"] == claim_id
    assert len(get_body["ledger"]) >= 6


def test_text_validation_requires_raw_text(client):
    payload = {
        "source_name": "invalid-text-claim",
        "source_type": "text",
        "raw_text": "",
    }
    resp = client.post('/api/prototype/claims/evaluate', json=payload)
    assert resp.status_code == 400
    body = resp.get_json()
    assert body["error"] == "validation_error"


def test_ocr_missing_file_returns_422(client):
    payload = {
        "source_name": "missing-image",
        "source_type": "image",
        "file_path": "/tmp/does-not-exist-image.png",
        "metadata": {"origin": "pytest"},
    }
    resp = client.post('/api/prototype/claims/evaluate', json=payload)
    assert resp.status_code == 422
    body = resp.get_json()
    assert "File not found" in body["error"]


def test_rbac_enforced_blocks_missing_token(tmp_path, monkeypatch):
    db_path = tmp_path / "rbac_test.db"
    db_url = f"sqlite:///{db_path}"

    monkeypatch.setenv("DATABASE_URL", db_url)
    monkeypatch.setenv("RBAC_ENFORCE", "true")
    monkeypatch.setenv("JWT_SECRET", "test-secret")

    from backend.app.db import configure_database, init_db

    configure_database(db_url)
    init_db(db_url)

    from backend.app import create_app

    app = create_app()
    app.config.update(TESTING=True)
    client = app.test_client()

    payload = {
        "source_name": "needs-auth",
        "source_type": "text",
        "raw_text": "x = y + z",
    }
    resp = client.post('/api/prototype/claims/evaluate', json=payload)
    assert resp.status_code == 401
