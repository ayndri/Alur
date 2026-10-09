<?php

// Hanya aturan yang dipakai aplikasi ini; aturan lain jatuh ke bahasa Inggris (fallback).
return [
    'after_or_equal' => ':Attribute harus tanggal yang sama atau setelah :date.',
    'confirmed' => 'Konfirmasi :attribute tidak sama.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'email' => ':Attribute harus alamat email yang valid.',
    'exists' => ':Attribute yang dipilih tidak ditemukan.',
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus bilangan bulat.',
    'max' => [
        'file' => ':Attribute maksimal :max KB.',
        'numeric' => ':Attribute maksimal :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'mimes' => ':Attribute harus berformat: :values.',
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute ini sudah terdaftar.',
        'url' => ':Attribute harus URL yang valid, diawali https://.',

    'attributes' => [
        'assignee_id' => 'pemegang',
        'body' => 'isi',
        'color' => 'warna',
        'column_id' => 'kolom',
        'description' => 'deskripsi',
        'due_on' => 'tenggat',
        'email' => 'email',
        'key' => 'kode proyek',
        'kind' => 'jenis kolom',
        'name' => 'nama',
        'password' => 'password',
        'priority' => 'prioritas',
        'reason' => 'alasan',
        'role' => 'peran',
        'title' => 'judul',
        'wip_limit' => 'batas WIP',
    ],
];
