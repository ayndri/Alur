<?php

// Vercel menjalankan Laravel sebagai satu fungsi; semua request non-statis diarahkan ke sini.
// Peringatan PHP tidak boleh tercetak ke halaman: begitu ada output, header dan cookie sesi
// tidak bisa dikirim lagi. Laravel tetap mencatat error ke log (stderr).
ini_set('display_errors', '0');

require __DIR__.'/../public/index.php';
