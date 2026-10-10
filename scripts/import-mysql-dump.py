#!/usr/bin/env python3
"""phpMyAdmin の MySQL ダンプから、指定テーブルの INSERT だけを SQLite 向けに変換して取り込む。

構造は Laravel の migrate で作成済みであることが前提（CREATE TABLE は使わない）。
使い方:
  python3 scripts/import-mysql-dump.py <dump.sql> <sqlite.db> [--dry-run]
"""
import re
import sqlite3
import sys

# 取り込む順序（外部キーの親が先）。migrations / cache / sessions は取り込まない。
TABLES = ["admins", "posts", "tool_events", "ui_pattern_snippets"]

ESCAPES = {"n": "\n", "r": "\r", "t": "\t", "0": "\0", "b": "\b", "Z": "\x1a"}


def split_statements(text):
    """文字列リテラルを考慮して、セミコロン区切りで文に分ける。"""
    stmts, buf, i, n = [], [], 0, len(text)
    while i < n:
        c = text[i]
        if c == "'":
            j = i + 1
            while j < n:
                if text[j] == "\\":
                    j += 2
                elif text[j] == "'":
                    if j + 1 < n and text[j + 1] == "'":
                        j += 2
                    else:
                        break
                else:
                    j += 1
            buf.append(text[i : j + 1])
            i = j + 1
        elif c == ";":
            stmts.append("".join(buf))
            buf = []
            i += 1
        else:
            buf.append(c)
            i += 1
    return stmts


def convert_literals(stmt):
    """MySQL の '...'（バックスラッシュエスケープ）を SQLite の '...'（'' エスケープ）へ。"""
    out, i, n = [], 0, len(stmt)
    while i < n:
        c = stmt[i]
        if c != "'":
            out.append(c)
            i += 1
            continue
        i += 1
        s = []
        while i < n:
            ch = stmt[i]
            if ch == "\\" and i + 1 < n:
                nxt = stmt[i + 1]
                s.append(ESCAPES.get(nxt, nxt))
                i += 2
            elif ch == "'":
                if i + 1 < n and stmt[i + 1] == "'":
                    s.append("'")
                    i += 2
                else:
                    i += 1
                    break
            else:
                s.append(ch)
                i += 1
        out.append("'" + "".join(s).replace("'", "''") + "'")
    return "".join(out)


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    dry_run = "--dry-run" in sys.argv
    if len(args) != 2:
        sys.exit(__doc__)
    dump_path, db_path = args

    with open(dump_path, encoding="utf-8") as f:
        text = f.read()

    inserts = {t: [] for t in TABLES}
    for stmt in split_statements(text):
        m = re.search(r"INSERT INTO `(\w+)`", stmt)
        if m and m.group(1) in inserts:
            body = stmt[m.start() :]
            # 逆クォートを外す（識別子のみ。文字列内には出現しない前提で、変換後に処理）
            inserts[m.group(1)].append(convert_literals(body).replace("`", ""))

    conn = sqlite3.connect(db_path)
    conn.execute("PRAGMA foreign_keys = ON")
    try:
        conn.execute("BEGIN")
        for table in TABLES:
            before = conn.execute(f"SELECT COUNT(*) FROM {table}").fetchone()[0]
            if before != 0:
                sys.exit(f"{table} は空ではありません（{before} 件）。中止します。")
            for sql in inserts[table]:
                conn.execute(sql)
            after = conn.execute(f"SELECT COUNT(*) FROM {table}").fetchone()[0]
            print(f"{table}: ダンプ {len(inserts[table])} 件 → 取り込み後 {after} 件")
            if after != len(inserts[table]):
                raise RuntimeError(f"{table} の件数が一致しません")
        if dry_run:
            conn.execute("ROLLBACK")
            print("--dry-run のため ROLLBACK しました")
        else:
            conn.execute("COMMIT")
            print("COMMIT しました")
    except Exception:
        conn.execute("ROLLBACK")
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    main()
