# SocratesRead

Platform latihan literasi dan membaca kritis berbasis metode tanya jawab Socrates untuk siswa dan guru.

---

## Tim pengembang

Proyek ini dikembangkan oleh tim **Ngejar Deadline**:

| No | Nama Anggota | Username |
| :---: | :--- | :--- |
| 1 | **Dea Nasaria** | 0dexvnn |
| 2 | **Riski** | riskii111 |
| 3 | **Fibyo Aezravarell** | fibzzyy & Fibzz123 |
| 4 | **Hanan Alghazali** | HanzelBro |

---

## Tentang SocratesRead

SocratesRead melatih pemahaman bacaan siswa melalui dialog tanya jawab terarah ala Socrates. Siswa membaca materi yang telah dirapikan, menjawab pertanyaan berjenjang, menulis penjelasan dengan kalimat sendiri, serta menerima umpan balik langsung untuk menguji pemahaman konsep terhadap bahan bacaan.

Bagi guru, platform ini menyediakan ruang untuk mengunggah bahan bacaan digital (modul atau artikel PDF), menyusun latihan soal berbasis teks, serta memantau perkembangan literasi dan pemikiran kritis siswa di kelas masing-masing.

---

## Fitur utama

<details open>
<summary><strong>1. Ruang Belajar Siswa</strong> (klik untuk membuka/menutup)</summary>

- **Dashboard personal**: Menampilkan pencapaian XP, level belajar, target bacaan aktif, dan streak latihan harian.
- **Ruang baca terfokus**: Menyajikan bahan bacaan yang sudah dirapikan per bagian, dilengkapi glosarium kata sukar otomatis dan catatan samping.
- **Coach membaca Athena**: Asisten dialog interaktif yang mendampingi siswa saat membaca teks, memberikan pertanyaan pemantik tanpa langsung membocorkan jawaban akhir.
- **Tantangan Socrates**: Kuis pemahaman bertingkat mengacu pada taksonomi Bloom (C1 ingatan fakta hingga C5 evaluasi argumen).
- **Evaluasi parafrasa instan**: Umpan balik langsung terhadap esai siswa untuk mengukur pemahaman konsep sekaligus mendeteksi kutipan salin-tempel yang terlalu mirip dengan teks sumber.
- **Pencapaian dan lencana**: Sistem apresiasi belajar mencakup poin pengalaman (XP), lencana prestasi, dan papan peringkat antar-angkatan (kelas 10, 11, dan 12).
- **Kelas saya**: Siswa dapat bergabung ke ruang kelas binaan guru menggunakan kode kelas 6 karakter.
- **Pusat notifikasi**: Pemberitahuan tugas baru, umpan balik tantangan, dan aktivitas kelas yang responsif di berbagai perangkat.
</details>

<details open>
<summary><strong>2. Ruang Pengelolaan Guru</strong> (klik untuk membuka/menutup)</summary>

- **Manajemen kelas**: Membuat kelas baru sesuai tingkat angkatan, membagikan kode masuk kelas, mengelola status kelas aktif atau arsip, serta memantau daftar siswa terdaftar.
- **Pengelolaan modul bacaan**: Mengunggah materi bacaan berupa file PDF atau teks bebas. Sistem secara otomatis membersihkan tata letak yang berantakan, kop dokumen, dan catatan kaki yang tidak relevan.
- **Studio tantangan guru**: Guru dapat membuat soal pilihan ganda maupun esai berbobot nilai. Tersedia fitur co-pilot yang merekomendasikan jawaban acuan langsung dari kutipan bacaan.
- **Rekapitulasi dan laporan**: Matriks nilai kuis dan tugas esai siswa yang dapat difilter per kelas, dilengkapi fitur ekspor data ke format Excel (.xls) dan CSV.
- **Verifikasi profil pengajar**: Kolom identitas resmi mencakup NIP atau NUPTK serta asal sekolah.
</details>

---

## Integrasi kecerdasan buatan

SocratesRead menerapkan integrasi AI pada beberapa titik alur belajar:

<details open>
<summary><strong>Arsitektur integrasi AI</strong> (klik untuk detail alur)</summary>

### 1. Penyedia model bahasa
Sistem mendukung dua penyedia API utama yang dapat dikonfigurasi melalui berkas lingkungan:
- **Google Gemini API**: Menggunakan model `gemini-2.5-flash` untuk pemrosesan teks cepat dan ekstraksi intisari bacaan.
- **OpenRouter API**: Opsi alternatif untuk fleksibilitas pemilihan model.
- **Fallback heuristik lokal**: Apabila koneksi internet offline atau kuota API habis, modul internal tetap dapat memproses bacaan dan memeriksa jawaban dasar secara deterministik tanpa membuat aplikasi terhenti.

### 2. Pembersihan teks bacaan otomatis (`ReadingMaterialFormatter`)
Dokumen PDF materi pelajaran sering kali memuat header berulang, instruksi tugas kelompok di sela paragraf, nomor halaman, atau bullet point yang berantakan. Modul ini membersihkan derau tersebut, merapikan struktur judul markdown, dan menyusun daftar istilah sulit secara otomatis.

### 3. Generator pertanyaan bertingkat (`DocumentAiProcessor`)
AI menganalisis konten bacaan untuk memetakan alur ide pokok dan menghasilkan pertanyaan pemantik berjenjang berdasarkan taksonomi Bloom:
- **C1 (Mengingat)**: Menemukan fakta kunci dan definisi istilah.
- **C2 (Memahami)**: Meringkas alur dan menjelaskan konsep utama.
- **C3 (Menerapkan)**: Menghubungkan gagasan teks dengan situasi nyata.
- **C4 (Menganalisis)**: Membedakan sebab-akibat, membandingkan sudut pandang, atau memeriksa bukti penulis.
- **C5 (Mengevaluasi)**: Menilai kekuatan argumen, relevansi data, dan simpulan bacaan.

### 4. Pipeline evaluasi jawaban tiga lapis (`AnswerEvaluationPipeline`)
Saat siswa mengirimkan jawaban esai pada tantangan bacaan, sistem memeriksa teks melalui tiga tahap bertingkat:
- **Lapis 1 (Aturan dasar)**: Memeriksa panjang minimal kalimat, menyaring masukan kosong, dan mendeteksi teks acak atau spam.
- **Lapis 2 (Kemiripan semantik dan deteksi salin-tempel)**: Menghitung tingkat tumpang tindih kata (n-gram overlap, Jaccard, dan Levenshtein) antara jawaban siswa dan paragraf sumber. Jika siswa hanya menyalin teks mentah tanpa memparafrasa, sistem meminta siswa menyusun ulang jawaban dengan kalimat sendiri.
- **Lapis 3 (Rubrik penilaian LLM)**: Model AI mengevaluasi kesesuaian makna terhadap rubrik acuan dalam format JSON terstruktur. Hasilnya memberikan nilai pemahaman serta catatan bimbingan yang konstruktif bagi siswa.
</details>

---

## Teknologi yang digunakan

| Bagian | Teknologi |
| :--- | :--- |
| **Backend** | PHP 8.2+, Laravel 12 |
| **Frontend** | Blade Templates, Tailwind CSS v4, Vanilla JavaScript |
| **Build tool** | Vite 7 |
| **Database** | SQLite (bawaan lokal) |
| **Dokumen** | Smalot PDFParser |
| **Model AI** | OpenRouter |
| **Pengujian** | PHPUnit 11, Playwright |

---

## Cara instalasi lokal

Ikuti langkah berikut untuk menjalankan proyek di komputer lokal:

<details open>
<summary><strong>Panduan langkah demi langkah</strong> (klik untuk membuka/menutup)</summary>

### 1. Kebutuhan sistem
- PHP versi 8.2 atau lebih baru dengan ekstensi `mbstring`, `sqlite3`, dan `pdo_sqlite` aktif.
- Composer versi 2.
- Node.js versi 18 atau lebih baru beserta npm.

### 2. Kloning dan penyiapan berkas
Masuk ke terminal atau PowerShell, lalu jalankan:

```bash
# Pindah ke direktori web server (contoh)
cd C:\socratesread

# Jalankan skrip setup otomatis (memasang vendor PHP, membuat .env, kunci aplikasi, migrasi, dan npm build)
composer setup
```

Jika ingin mengatur konfigurasi secara manual:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

### 3. Konfigurasi API kecerdasan buatan
Buka berkas `.env` lalu sesuaikan kunci API sesuai kebutuhan:

```env

# Menggunakan OpenRouter:
# AI_PROVIDER=openrouter
# OPENROUTER_API_KEY=kunci_api_openrouter_anda
# OPENROUTER_MODEL=openrouter/free
```

### 4. Menjalankan server aplikasi
Jalankan server pengembangan Laravel:

```bash
php artisan serve
```

Buka peramban dan akses alamat `http://127.0.0.1:8000`.

### 5. Akun demo bawaan
Setelah database di-seed (`php artisan db:seed`), akun pengujian berikut siap digunakan:

- **Akun Guru**:
  - Email: `guru.demo@socratesread.id`
  - Kata sandi: `password123`
- **Akun Siswa**:
  - Email: `siswa.demo@socratesread.id`
  - Kata sandi: `password123`
</details>

---

## Menjalankan pengujian

Seluruh pengujian unit dan fitur dapat dijalankan menggunakan perintah:

```bash
php artisan test
```

Proyek ini telah mencakup 146 pengujian otomatis yang memeriksa pipeline evaluasi AI, pembersihan materi bacaan, autentikasi multi-peran, manajemen tantangan guru, serta perhitungan gamifikasi.
