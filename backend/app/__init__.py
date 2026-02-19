"""Minimal Flask app factory for reasoner API endpoints."""

from __future__ import annotations

import os

from flask import Flask

from backend.app.db import init_db
from backend.app.routes_jrz import jrz_api
from backend.app.routes import reasoner_api
from backend.app.routes_pipeline import prototype_api


def _env_flag(name: str, default: bool) -> bool:
    raw = os.getenv(name, "true" if default else "false").strip().lower()
    return raw in {"1", "true", "yes", "on"}


def create_app() -> Flask:
    app = Flask(__name__)
    if _env_flag("AUTO_INIT_DB", True):
        init_db()
    app.register_blueprint(reasoner_api)
    app.register_blueprint(jrz_api)
    app.register_blueprint(prototype_api)
    return app
