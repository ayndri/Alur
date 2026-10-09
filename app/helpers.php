<?php

if (! function_exists('tanggal')) {
    /** "12 Okt 2026" atau, dengan jam, "12 Okt 2026, 14.30". */
    function tanggal(?\DateTimeInterface $date, bool $withTime = false): string
    {
        if (! $date) {
            return '-';
        }

        return \Illuminate\Support\Carbon::instance($date)->translatedFormat($withTime ? 'j M Y, H.i' : 'j M Y');
    }
}

if (! function_exists('hari')) {
    /** Durasi dalam hari untuk dibaca orang: "kurang dari sehari", "3 hari", "2,5 hari". */
    function hari(?float $days): string
    {
        if ($days === null) {
            return '-';
        }
        if ($days < 1) {
            return $days < 1 / 24 ? 'kurang dari sejam' : round($days * 24).' jam';
        }

        return ($days < 10 ? str_replace('.', ',', (string) round($days, 1)) : (string) round($days)).' hari';
    }
}

if (! function_exists('asset_v')) {
    /** URL aset dengan penanda versi untuk cache-busting. Tidak gagal kalau file tidak terbaca dari fungsi. */
    function asset_v(string $path): string
    {
        $version = @filemtime(public_path($path)) ?: config('app.asset_version', '1');

        return asset($path).'?v='.$version;
    }
}
