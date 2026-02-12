"""Minimal Flask app factory for reasoner API endpoints."""

from __future__ import annotations

from flask import Flask

from backend.app.routes import reasoner_api


def create_app() -> Flask:
    app = Flask(__name__)
    app.register_blueprint(reasoner_api)
    return app

