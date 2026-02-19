from __future__ import annotations

from pathlib import Path

import pytest


@pytest.fixture()
def app(tmp_path: Path, monkeypatch: pytest.MonkeyPatch):
    db_path = tmp_path / "prototype_test.db"
    db_url = f"sqlite:///{db_path}"

    monkeypatch.setenv("DATABASE_URL", db_url)
    monkeypatch.setenv("RBAC_ENFORCE", "false")

    from backend.app.db import configure_database, init_db

    configure_database(db_url)
    init_db(db_url)

    from backend.app import create_app

    app = create_app()
    app.config.update(TESTING=True)
    return app


@pytest.fixture()
def client(app):
    return app.test_client()
