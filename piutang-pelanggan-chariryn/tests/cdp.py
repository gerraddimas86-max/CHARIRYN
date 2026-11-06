#!/usr/bin/env python3
"""Kontrol Chromium lewat Chrome DevTools Protocol (CDP).

Tidak memakai Playwright - cukup Chromium + pustaka `websockets`.

    from cdp import Browser
    with Browser() as b:
        page = b.new_page()
        page.goto(url)
        print(page.evaluate("1+1"))
"""
import json
import os
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request

import websockets

BASE = "http://localhost/piutang-pelanggan"
CHROME_CANDIDATES = [
    os.path.expanduser("~/.cache/ms-playwright/chromium-1148/chrome-linux/chrome"),
    os.path.expanduser("~/.cache/ms-playwright/chromium-1243/chrome-linux/chrome"),
    "/usr/bin/chromium",
    "/usr/bin/chromium-browser",
    "/usr/bin/google-chrome",
]


def cari_chrome() -> str:
    for p in CHROME_CANDIDATES:
        if os.path.exists(p):
            return p
    p = shutil.which("chromium") or shutil.which("google-chrome")
    if p:
        return p
    raise RuntimeError("Chromium tidak ditemukan")


def port_bebas() -> int:
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


class Halaman:
    def __init__(self, ws):
        self._ws = ws
        self._id = 0

    async def _kirim(self, method, params=None):
        self._id += 1
        await self._ws.send(json.dumps({"id": self._id, "method": method, "params": params or {}}))
        while True:
            msg = json.loads(await self._ws.recv())
            if msg.get("id") == self._id:
                if "error" in msg:
                    raise RuntimeError(f"{method}: {msg['error']}")
                return msg.get("result", {})

    async def goto(self, url, tunggu_muat=True):
        await self._kirim("Page.navigate", {"url": url})
        if tunggu_muat:
            await asyncio.sleep(0.7)

    async def evaluate(self, script):
        r = await self._kirim("Runtime.evaluate", {
            "expression": script, "returnByValue": True, "awaitPromise": True})
        return r.get("result", {}).get("value")

    async def screenshot(self, path, lebar=1440, tinggi=1400):
        await self._kirim("Emulation.setDeviceMetricsOverride", {
            "width": lebar, "height": tinggi, "deviceScaleFactor": 1, "mobile": False})
        await asyncio.sleep(0.4)
        r = await self._kirim("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": True})
        import base64
        with open(path, "wb") as f:
            f.write(base64.b64decode(r["data"]))

    async def login(self, base, user, sandi):
        halaman_login = "auth/login-pelanggan.php" if user == "budi" else "auth/login.php"
        await self.goto(f"{base}/{halaman_login}")
        await self.evaluate(f"""
            (() => {{
                document.querySelector('input[name=username]').value = {json.dumps(user)};
                document.querySelector('input[name=password]').value = {json.dumps(sandi)};
                document.querySelector('form.form-masuk').requestSubmit();
                return true;
            }})()
        """)
        await asyncio.sleep(1.0)


class Browser:
    def __init__(self, lebar=1440, tinggi=1400):
        self.lebar, self.tinggi = lebar, tinggi
        self._dir = tempfile.mkdtemp(prefix="cdp-")
        self._port = port_bebas()
        self._proses = None

    async def __aenter__(self):
        self._proses = subprocess.Popen(
            [cari_chrome(), "--headless", "--disable-gpu", "--no-sandbox", "--hide-scrollbars",
             f"--remote-debugging-port={self._port}", f"--user-data-dir={self._dir}/profile",
             f"--window-size={self.lebar},{self.tinggi}", "about:blank"],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        url = f"http://127.0.0.1:{self._port}/json/version"
        for _ in range(80):
            try:
                urllib.request.urlopen(url, timeout=1).read()
                break
            except Exception:
                time.sleep(0.15)
        return self

    async def __aexit__(self, *exc):
        if self._proses:
            self._proses.terminate()
            try:
                self._proses.wait(timeout=5)
            except Exception:
                self._proses.kill()
        shutil.rmtree(self._dir, ignore_errors=True)

    async def new_page(self, url="about:blank"):
        req = urllib.request.Request(f"http://127.0.0.1:{self._port}/json/new?{url}", method="PUT")
        info = json.loads(urllib.request.urlopen(req, timeout=5).read())
        ws = await websockets.connect(info["webSocketDebuggerUrl"], max_size=32 * 1024 * 1024)
        halaman = Halaman(ws)
        await halaman._kirim("Page.enable")
        await halaman._kirim("Runtime.enable")
        await halaman._kirim("Emulation.setDeviceMetricsOverride", {
            "width": self.lebar, "height": self.tinggi, "deviceScaleFactor": 1, "mobile": False})
        return halaman


import asyncio  # noqa: E402  (dipakai di Halaman)
