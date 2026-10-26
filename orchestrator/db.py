"""DB access — PyMySQL + prepared statements (mirror database layer PHP)."""
from __future__ import annotations

import pymysql
from config import settings


def get_conn() -> pymysql.connections.Connection:
    """Koneksi baru per pemanggilan (short-lived, thread-safe untuk worker)."""
    return pymysql.connect(
        host=settings.DB_HOST,
        port=settings.DB_PORT,
        user=settings.DB_USER,
        password=settings.DB_PASS,
        database=settings.DB_NAME,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def query(sql: str, params: tuple = ()) -> list[dict]:
    with get_conn() as conn, conn.cursor() as cur:
        cur.execute(sql, params)
        rows: list[dict] = list(cur.fetchall())  # type: ignore[assignment]
        return rows


def query_one(sql: str, params: tuple = ()) -> dict | None:
    rows = query(sql, params)
    return rows[0] if rows else None


def execute(sql: str, params: tuple = ()) -> int:
    """INSERT/UPDATE/DELETE — return rowcount / lastrowid via return value terpisah."""
    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            return cur.rowcount


def insert_returning_id(sql: str, params: tuple = ()) -> int:
    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            return int(cur.lastrowid)
