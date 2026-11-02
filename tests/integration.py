#!/usr/bin/env python3
# Copyright © 2016-2026 Localzet Group. All rights reserved.
# SPDX-License-Identifier: AGPL-3.0-or-later

import os
import socket
import subprocess
import sys
import tempfile
import threading
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def free_port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


def echo_handler(sock):
    try:
        while data := sock.recv(65536):
            sock.sendall(data)
    except OSError:
        pass
    finally:
        sock.close()


def read_response(sock):
    data = b""
    while b"\r\n\r\n" not in data:
        chunk = sock.recv(4096)
        if not chunk:
            break
        data += chunk
    return data


def request(proxy_port, request_bytes):
    sock = socket.create_connection(("127.0.0.1", proxy_port), timeout=3)
    sock.sendall(request_bytes)
    return sock


def authorized(headers=b''):
    credentials = b'integration-user:integration-password'
    return b'Proxy-Authorization: Basic ' + __import__('base64').b64encode(credentials) + b'\r\n' + headers


def main():
    target_port, proxy_port, closed_port = free_port(), free_port(), free_port()
    listener = socket.socket()
    listener.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    listener.bind(("127.0.0.1", target_port))
    listener.listen()
    listener.settimeout(0.2)
    stopped = threading.Event()

    def accept_loop():
        while not stopped.is_set():
            try:
                conn, _ = listener.accept()
            except socket.timeout:
                continue
            threading.Thread(target=echo_handler, args=(conn,), daemon=True).start()

    threading.Thread(target=accept_loop, daemon=True).start()
    environment = os.environ.copy()
    environment.update(
        PROXY_TEST_PORT=str(proxy_port),
        PROXY_TEST_TARGET_PORT=str(target_port),
        PROXY_TEST_CLOSED_PORT=str(closed_port),
    )
    process = subprocess.Popen(
        ["php", "-d", "disable_functions=pcntl_fork", str(ROOT / "tests/integration_server.php"), "start"],
        cwd=ROOT,
        env=environment,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    try:
        deadline = time.monotonic() + 8
        while time.monotonic() < deadline:
            if process.poll() is not None:
                raise RuntimeError(f"Proxy process exited early with status {process.returncode}")
            try:
                with socket.create_connection(("127.0.0.1", proxy_port), timeout=0.2):
                    break
            except OSError:
                time.sleep(0.05)
        else:
            raise RuntimeError("Proxy listener did not start")

        with request(proxy_port, f"GET http://127.0.0.1:{target_port}/ HTTP/1.1\r\n".encode() + authorized(f"Host: 127.0.0.1:{target_port}\r\n\r\n".encode())) as sock:
            sock.settimeout(3)
            response = read_response(sock)
            if not response.startswith(b"GET http://127.0.0.1:"):
                raise RuntimeError(f"Allowed HTTP request did not reach the target: {response!r}")

        with request(proxy_port, f"CONNECT 127.0.0.1:{target_port} HTTP/1.1\r\n".encode() + authorized(f"Host: 127.0.0.1:{target_port}\r\n\r\n".encode())) as sock:
            sock.settimeout(3)
            response = read_response(sock)
            if not response.startswith(b"HTTP/1.1 200 Connection Established\r\n\r\n"):
                raise RuntimeError(f"Allowed CONNECT was rejected: {response!r}")
            marker = b"connect-tunnel-probe"
            sock.sendall(marker)
            if sock.recv(len(marker)) != marker:
                raise RuntimeError("CONNECT tunnel did not relay bidirectional bytes")

        with request(proxy_port, b"CONNECT 127.0.0.1:22 HTTP/1.1\r\n" + authorized(b"\r\n")) as sock:
            response = read_response(sock)
            if not response.startswith(b"HTTP/1.1 403 Forbidden"):
                raise RuntimeError(f"Disallowed CONNECT did not return 403: {response!r}")

        with request(proxy_port, b"GET /relative HTTP/1.1\r\n" + authorized(b"\r\n")) as sock:
            response = read_response(sock)
            if not response.startswith(b"HTTP/1.1 400 Bad Request"):
                raise RuntimeError(f"Malformed request did not return 400: {response!r}")

        with request(proxy_port, f"CONNECT 127.0.0.1:{closed_port} HTTP/1.1\r\n".encode() + authorized(b"\r\n")) as sock:
            response = read_response(sock)
            if not response.startswith(b"HTTP/1.1 502 Bad Gateway"):
                raise RuntimeError(f"Failed upstream did not return 502: {response!r}")

        with request(proxy_port, f"CONNECT 127.0.0.1:{target_port} HTTP/1.1\r\n\r\n".encode()) as sock:
            if not read_response(sock).startswith(b"HTTP/1.1 407 Proxy Authentication Required"):
                raise RuntimeError("Unauthenticated requests must be rejected.")

        print("Proxy loopback integration checks passed: Basic auth, HTTP, CONNECT, allowlist, malformed request, upstream failure.")
    finally:
        process.terminate()
        try:
            process.wait(timeout=3)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()
        stopped.set()
        listener.close()
        for suffix in (".pid", ".pid.lock", ".log", ".log.lock"):
            try:
                Path(tempfile.gettempdir(), f"proxy-integration-{process.pid}{suffix}").unlink()
            except FileNotFoundError:
                pass


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(str(error), file=sys.stderr)
        raise
