"""Database session + engine setup for the prototype admissibility pipeline."""

from __future__ import annotations

import os
from contextlib import contextmanager
from pathlib import Path

from sqlalchemy import create_engine
from sqlalchemy.engine import Engine
from sqlalchemy.orm import Session, declarative_base, scoped_session, sessionmaker

PROJECT_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_DB_PATH = PROJECT_ROOT / "out" / "us_audit" / "prototype.db"
DEFAULT_DATABASE_URL = f"sqlite:///{DEFAULT_DB_PATH}"

Base = declarative_base()

_engine: Engine | None = None
SessionLocal = scoped_session(sessionmaker(autoflush=False, autocommit=False, expire_on_commit=False))
_active_database_url: str | None = None


def _normalized_database_url(database_url: str | None = None) -> str:
    raw = (database_url or os.getenv("DATABASE_URL", DEFAULT_DATABASE_URL)).strip()
    return raw or DEFAULT_DATABASE_URL


def _create_engine(database_url: str) -> Engine:
    connect_args = {"check_same_thread": False} if database_url.startswith("sqlite") else {}
    return create_engine(database_url, future=True, pool_pre_ping=True, connect_args=connect_args)


def configure_database(database_url: str | None = None) -> str:
    """Configure and bind SQLAlchemy engine/session for the given URL."""
    global _engine, _active_database_url

    resolved_url = _normalized_database_url(database_url)
    if _engine is not None and _active_database_url == resolved_url:
        return resolved_url

    if _engine is not None:
        SessionLocal.remove()
        _engine.dispose()

    _engine = _create_engine(resolved_url)
    SessionLocal.configure(bind=_engine)
    _active_database_url = resolved_url
    return resolved_url


def get_engine() -> Engine:
    if _engine is None:
        configure_database()
    assert _engine is not None
    return _engine


def init_db(database_url: str | None = None) -> None:
    """Create tables if they do not exist."""
    resolved_url = configure_database(database_url)

    # Import models lazily so metadata is registered before create_all.
    from backend.app import models as _models  # noqa: F401

    if resolved_url.startswith("sqlite:///"):
        sqlite_path = Path(resolved_url.replace("sqlite:///", "", 1))
        sqlite_path.parent.mkdir(parents=True, exist_ok=True)

    Base.metadata.create_all(bind=get_engine())


@contextmanager
def get_session() -> Session:
    if _engine is None:
        configure_database()

    session = SessionLocal()
    try:
        yield session
        session.commit()
    except Exception:
        session.rollback()
        raise
    finally:
        session.close()
