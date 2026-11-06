        </main>
    </div>
</div>
<button type="button" class="nav-fab" id="tombolFab" aria-label="Buka panel samping" aria-controls="sidebar">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="9 18 15 12 9 6"></polyline>
    </svg>
</button>
<script src="<?= BASE_URL ?>assets/js/aplikasi.js?v=2" defer></script>
<script src="<?= BASE_URL ?>assets/js/formulir.js?v=3" defer></script>
<script src="<?= BASE_URL ?>assets/js/tabel.js?v=3" defer></script>
<script src="<?= BASE_URL ?>assets/js/dashboard.js" defer></script>
<div class="modal-lapisan" id="modalLapisan" hidden>
    <div class="modal-kotak" role="dialog" aria-modal="true" aria-labelledby="modalJudul">
        <div class="modal-kepala">
            <h3 id="modalJudul">Detail</h3>
            <a class="btn btn-netral btn-kecil" id="modalBukaHalaman" href="#" hidden>Buka Halaman</a>
            <button type="button" class="modal-tutup" id="modalTutup" aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-isi" id="modalIsi"></div>
    </div>
</div>
</body>
</html>