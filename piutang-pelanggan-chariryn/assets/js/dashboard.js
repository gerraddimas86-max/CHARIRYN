/**
 * dashboard.js
 * Efek halus pada header saat halaman digulir.
 */
(function () {
    'use strict';

    var kepala = document.querySelector('.kepala');

    if (!kepala) {
        return;
    }

    function perbarui() {
        if (window.scrollY > 8) {
            kepala.classList.add('mengambang');
        } else {
            kepala.classList.remove('mengambang');
        }
    }

    window.addEventListener('scroll', perbarui, { passive: true });
    perbarui();
})();