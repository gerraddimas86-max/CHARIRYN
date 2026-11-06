#!/usr/bin/env python3
"""Pemeriksaan tata letak: memastikan tabel rapi dan tidak ada yang terpotong.

Memeriksa setiap halaman untuk:
  - sel tabel yang isinya terpotong (ellipsis) padahal bukan kolom aksi
  - tabel yang lebih lebar dari wadahnya
  - halaman yang melebar mendatar (overflow)

    python3 tests/layout-check.py
"""
import sys

from playwright.sync_api import sync_playwright

BASE = "http://localhost/piutang-pelanggan"

HALAMAN = {
    "manajer": ("Manajer123!", [
        "dashboard/index.php", "pelanggan/index.php", "pelanggan/detail.php?id=1",
        "pelanggan/permintaan.php", "piutang/index.php", "piutang/detail.php?id=1",
        "piutang/tambah.php", "pembayaran/index.php", "pembayaran/detail.php?id=1",
        "pembayaran/tambah.php", "pengajuan/index.php", "pengajuan/detail.php?id=1",
        "cari-pantau/index.php", "jatuh-tempo/index.php", "akun-pelanggan/index.php",
        "akun-pelanggan/tambah.php", "akun/sandi.php",
    ]),
    "pemilik": ("Admin123!", [
        "laporan/piutang.php", "laporan/pembayaran.php", "laporan/jatuh-tempo.php",
        "laporan/pelanggan.php", "akun/sandi.php",
    ]),
    "budi": ("Pelanggan123!", [
        "portal/index.php", "portal/detail.php?id=1", "portal/pengajuan.php",
        "portal/status-pengajuan.php", "portal/profil.php", "portal/akun.php",
        "akun/sandi.php",
    ]),
}

# Lebar kolom yang sama harus sama di semua halaman.
JAVASCRIPT_UNIFORM = """
() => {
    const lebar = {};
    document.querySelectorAll('table.tabel').forEach((t) => {
        t.querySelectorAll('thead th').forEach((th) => {
            const nama = th.textContent.trim();
            const w = Math.round(th.getBoundingClientRect().width);
            (lebar[nama] = lebar[nama] || new Set()).add(w);
        });
    });
    return Object.entries(lebar)
        .filter(([, w]) => w.size > 1)
        .map(([nama, w]) => `kolom "${nama}" punya lebar ${[...w].sort((a, b) => a - b).join('/')}px`);
}
"""

JAVASCRIPT = """
() => {
    const masalah = [];
    document.querySelectorAll('table.tabel').forEach((t, ti) => {
        t.querySelectorAll('tbody td').forEach((td) => {
            if (td.classList.contains('col-aksi')) return;
            if (td.scrollWidth > td.clientWidth + 1 && td.textContent.trim() !== '') {
                const isi = td.textContent.trim().slice(0, 28);
                masalah.push(`sel terpotong: "${isi}" (${td.scrollWidth}>${td.clientWidth})`);
            }
        });
    });
    if (document.documentElement.scrollWidth > window.innerWidth + 1) {
        masalah.push(`halaman melebar: ${document.documentElement.scrollWidth} > ${window.innerWidth}`);
    }
    return masalah;
}
"""

gagal = 0
total = 0

with sync_playwright() as p:
    browser = p.chromium.launch()
    for peran, (sandi, halaman_list) in HALAMAN.items():
        page = browser.new_page(viewport={"width": 1440, "height": 1000})
        page.goto(f"{BASE}/auth/login.php")
        halaman_login = "auth/login-pelanggan.php" if peran == "budi" else "auth/login.php"
        page.goto(f"{BASE}/{halaman_login}")
        page.fill("input[name=username]", peran)
        page.fill("input[name=password]", sandi)
        page.click("form.form-masuk button[type=submit]")
        page.wait_for_load_state("networkidle")

        for h in halaman_list:
            page.goto(f"{BASE}/{h}")
            page.wait_for_load_state("networkidle")
            masalah = page.evaluate(JAVASCRIPT)
            for pesan in page.evaluate(JAVASCRIPT_UNIFORM):
                masalah.append("lebar tidak seragam: " + pesan)
            total += 1
            if masalah:
                gagal += 1
                print(f"FAIL {peran}/{h}")
                for m in masalah[:5]:
                    print(f"       - {m}")
            else:
                print(f"OK   {peran}/{h}")

        # Uji layar ponsel
        page.set_viewport_size({"width": 390, "height": 844})
        for h in halaman_list[:3]:
            page.goto(f"{BASE}/{h}")
            page.wait_for_load_state("networkidle")
            masalah = [m for m in page.evaluate(JAVASCRIPT) if "halaman melebar" in m]
            total += 1
            if masalah:
                gagal += 1
                print(f"FAIL (ponsel) {peran}/{h}: {masalah}")
            else:
                print(f"OK   (ponsel) {peran}/{h}")

        page.close()

    browser.close()

print(f"\n== {total} pemeriksaan tata letak, {gagal} gagal ==")
sys.exit(1 if gagal else 0)
