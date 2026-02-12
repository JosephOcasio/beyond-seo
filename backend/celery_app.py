"""Shared Celery application instance for asynchronous reasoner jobs."""

from __future__ import annotations

import os

from celery import Celery


REDIS_URL = os.environ.get("REDIS_URL", "redis://localhost:6379/0")
CELERY_BACKEND_URL = os.environ.get("CELERY_BACKEND_URL", REDIS_URL)


celery = Celery(
    "reasoner_tasks",
    broker=REDIS_URL,
    backend=CELERY_BACKEND_URL,
)

