#!/usr/bin/env python3
"""Screenshot + ukur halaman dengan Chromium (CDP, tanpa Playwright).

    python3 tests/shot.py <user> <sandi> <halaman> <keluaran.png> [lebar]
"""
import asyncio
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cdp import BASE, Browser  # noqa: E402


async def main():
    user, sandi, halaman, keluar = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
    lebar = int(sys.argv[5]) if len(sys.argv) > 5 else 1440

    async with Browser(lebar=lebar) as b:
        page = await b.new_page()
        await page.login(BASE, user, sandi)
        await page.goto(f"{BASE}/{halaman}")

        kolom = await page.evaluate("""
            () => {
                const t = document.querySelector('table.tabel');
                if (!t) return [];
                return [...t.querySelectorAll('thead th')].map(th => {
                    const r = th.getBoundingClientRect();
                    return [th.textContent.trim(), Math.round(r.width)];
                });
            }
        """)
        for nama, w in kolom:
            print(f"  {nama:<24} {w:>5} px")

        await page.screenshot(keluar, lebar=lebar)
    print(f"screenshot -> {keluar}")


asyncio.run(main())
