@extends('layouts.student')

@section('title', 'Kelas Saya')
@section('breadcrumb', 'Kelas saya')
@section('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
@endsection

@section('content')
    <div class="my-class-page">
        <section class="my-class-heading" aria-labelledby="classrooms-title">
            <div>
                <h1 id="classrooms-title">Kelas <span>saya</span></h1>
                <p class="my-class-subtitle">Kelola kelas belajar yang kamu ikuti bersama guru dan teman sekelas.</p>
            </div>
            <a class="my-class-back" href="{{ route('dashboard') }}">
                <x-icon name="arrow-left" />
                <span>Kembali ke beranda</span>
            </a>
        </section>

        @if (session('notice'))
            <div class="auth-notice my-class-notice" role="status">{{ session('notice') }}</div>
        @endif

        <section class="my-class-join" aria-labelledby="join-class-heading">
            <span class="my-class-join-accent" aria-hidden="true"></span>
            <span class="my-class-join-decoration" aria-hidden="true"></span>
            <div class="my-class-join-copy">
                <h2 id="join-class-heading">Gabung kelas baru.<br><span>Belajar bersama guru.</span></h2>

                <form id="join-class-form" method="POST" action="{{ route('classrooms.join') }}" class="my-class-join-form">
                    @csrf
                    <label for="join_code">Kode gabung kelas (8 karakter)</label>
                    <div class="my-class-join-controls">
                        <div class="my-class-code-field">
                            <span class="my-class-code-label" aria-hidden="true"><x-icon name="key-round" /> KODE KELAS</span>
                            <input id="join_code" name="join_code" value="{{ old('join_code', $joinCode) }}" maxlength="8" minlength="8" required autocomplete="off" autocapitalize="characters" placeholder="Contoh: BACA2026" aria-describedby="join-code-count @error('join_code') join-code-error @enderror">
                            <span id="join-code-count" class="my-class-code-count" aria-live="polite">{{ mb_strlen(old('join_code', $joinCode ?? '')) }}/8</span>
                        </div>
                        <button type="submit" class="my-class-join-button">
                            <span>Gabung kelas</span>
                            <x-icon name="arrow-right" />
                        </button>
                    </div>
                    <button type="button" class="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50" data-open-qr-scanner>
                        <x-icon name="qr-code" class="size-4" />
                        <span>Scan QR kelas</span>
                    </button>
                    <div class="mt-3 hidden max-w-sm rounded-xl border border-slate-200 bg-white p-3" data-qr-scanner-panel>
                        <video class="hidden aspect-video w-full rounded-lg bg-slate-950 object-cover" data-qr-scanner-video playsinline muted aria-label="Kamera untuk memindai QR kelas"></video>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <p class="text-xs leading-5 text-slate-500" data-qr-scanner-status aria-live="polite">Arahkan kamera ke QR kelas.</p>
                            <button type="button" class="shrink-0 text-sm text-slate-600 hover:text-slate-900" data-close-qr-scanner>Tutup</button>
                        </div>
                    </div>
                    @error('join_code')
                        <span id="join-code-error" class="my-class-error" role="alert">{{ $message }}</span>
                    @enderror
                </form>
            </div>
            <div class="my-class-mascot-wrap" role="img" aria-label="Ilustrasi tumpukan buku dan dedaunan">
                <div class="my-class-books" aria-hidden="true">
                    <span class="my-class-book my-class-book-one"></span>
                    <span class="my-class-book my-class-book-two"></span>
                    <span class="my-class-book my-class-book-three"></span>
                </div>
            </div>
        </section>

        <section class="my-class-stats" aria-label="Ringkasan progress">
            <article class="my-class-stat">
                <span class="my-class-stat-icon my-class-stat-blue"><x-icon name="book-open-check" /></span>
                <div><p>Total Materi Selesai</p><div class="my-class-stat-value"><strong>{{ $completedReadings }}</strong></div><small>{{ $weeklyReadingCount }} dalam 7 hari terakhir</small></div>
            </article>
            <article class="my-class-stat">
                <span class="my-class-stat-icon my-class-stat-blue"><x-icon name="graduation-cap" /></span>
                <div><p>Kelas yang Diikuti</p><div class="my-class-stat-value"><strong>{{ $classrooms->count() }}</strong></div><small>Kelas belajar yang kamu ikuti</small></div>
            </article>
            <article class="my-class-stat">
                <span class="my-class-stat-icon my-class-stat-blue"><x-icon name="trophy" /></span>
                <div><p>Total Tantangan Selesai</p><div class="my-class-stat-value"><strong>{{ $completedChallenges }}</strong></div><small>Challenge Socrates &amp; Tantangan Guru</small></div>
            </article>
            <article class="my-class-stat">
                <span class="my-class-stat-icon my-class-stat-blue"><x-icon name="library" /></span>
                <div><p>Total Materi Tersedia</p><div class="my-class-stat-value"><strong>{{ $assignedReadingCount }}</strong></div><small>Materi dari kelas yang kamu ikuti</small></div>
            </article>
        </section>

        <section class="my-class-list" aria-labelledby="enrolled-classes-heading">
            <div class="my-class-list-heading">
                <div>
                    <p class="my-class-eyebrow">KELAS AKTIF</p>
                    <h2 id="enrolled-classes-heading">Kelas yang Kamu Ikuti</h2>
                </div>
                <span>{{ $classrooms->count() }} kelas</span>
            </div>

            @if ($classrooms->isEmpty())
                <div class="my-class-empty">
                    <svg class="my-class-empty-stars" aria-hidden="true" width="260" height="120" viewBox="0 0 260 120" fill="none"><circle cx="205" cy="34" r="16" fill="#CFDCC8"/><circle cx="213" cy="30" r="14" fill="#F1F5EE"/><circle cx="40" cy="30" r="2" fill="#8CA9C8"/><circle cx="70" cy="62" r="1.6" fill="#8CA9C8"/><circle cx="150" cy="18" r="1.8" fill="#8CA9C8"/><circle cx="235" cy="78" r="1.6" fill="#8CA9C8"/></svg>
                    <svg class="my-class-sleeping-owl" role="img" aria-label="Burung hantu tidur di atas dahan" width="150" height="130" viewBox="0 0 150 130" fill="none">
                        <path d="M10 108h130" stroke="#6B5A45" stroke-width="7" stroke-linecap="round"/><path d="M112 108c10-2 16-8 20-16" stroke="#6B5A45" stroke-width="4" stroke-linecap="round"/><path d="m45 40 8-14 10 12m42 2-8-14-10 12" stroke="#2C4BA6" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M75 34c-22 0-36 16-36 40 0 24 14 36 36 36s36-12 36-36c0-24-14-40-36-40Z" fill="#2C4BA6"/><path d="M75 62c-14 0-22 10-22 26 0 14 8 22 22 22s22-8 22-22c0-16-8-26-22-26Z" fill="#F6EEDA"/><path d="M60 52c3 3 7 3 10 0m10 0c3 3 7 3 10 0" stroke="#F6EEDA" stroke-width="3" stroke-linecap="round"/><path d="m75 58-4 6h8z" fill="#E9B44C"/><path d="M62 82c4 3 8 4 13 4s9-1 13-4M64 94c4 3 7 4 11 4s7-1 11-4" stroke="#D9CBA5" stroke-width="2" stroke-linecap="round"/><path d="M40 72c-8 6-8 22 0 30m70-30c8 6 8 22 0 30" stroke="#4D7BDB" stroke-width="4" stroke-linecap="round"/><path d="M64 110v-4m22 4v-4" stroke="#E9B44C" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <h3>Belum ada kelas yang diikuti</h3>
                    <p>Masukkan kode kelas dari gurumu pada form di atas untuk mulai belajar bersama.</p>
                </div>
            @else
                <div class="my-class-grid">
                    @foreach ($classrooms as $classroom)
                        <article class="my-class-room-card">
                            <div class="my-class-room-heading">
                                <span class="my-class-room-icon icon-blue"><x-icon name="book-open-text" /></span>
                                <span class="my-class-grade">{{ $classroom->targetGradeLabel() }}</span>
                            </div>
                            <h3>{{ $classroom->name }}</h3>
                            <p class="my-class-teacher">{{ $classroom->teacher?->name ?? 'Guru SocratesRead' }}</p>
                            <span class="my-class-code">Kode kelas · {{ $classroom->join_code }}</span>
                            <div class="my-class-room-footer">
                                <span>{{ $classroom->assigned_readings_count }} bacaan · {{ $classroom->students_count }} siswa</span>
                            </div>
                            <a class="my-class-room-action" href="{{ route('reading', ['teacher' => $classroom->teacher_id, 'category' => $classroom->name]) }}"><span>Buka kelas</span><x-icon name="arrow-right" /></a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
    <script>
        (() => {
            const codeInput = document.getElementById('join_code');
            const counter = document.getElementById('join-code-count');
            if (!codeInput || !counter) return;
            const form = document.getElementById('join-class-form');
            const scannerButton = document.querySelector('[data-open-qr-scanner]');
            const scannerPanel = document.querySelector('[data-qr-scanner-panel]');
            const scannerVideo = document.querySelector('[data-qr-scanner-video]');
            const scannerStatus = document.querySelector('[data-qr-scanner-status]');
            let cameraStream = null;
            let scanning = false;

            const updateCounter = () => {
                codeInput.value = codeInput.value.toUpperCase().slice(0, 8);
                counter.textContent = `${codeInput.value.length}/8`;
            };

            codeInput.addEventListener('input', updateCounter);
            updateCounter();

            const stopScanner = () => {
                scanning = false;
                cameraStream?.getTracks().forEach((track) => track.stop());
                cameraStream = null;
                if (scannerVideo) scannerVideo.srcObject = null;
                if (scannerPanel) scannerPanel.classList.add('hidden');
            };

            document.querySelector('[data-close-qr-scanner]')?.addEventListener('click', stopScanner);
            scannerButton?.addEventListener('click', async () => {
                if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
                    scannerPanel?.classList.remove('hidden');
                    if (scannerStatus) scannerStatus.textContent = 'Pemindaian kamera tidak tersedia di browser ini. Pindai dengan kamera ponsel, lalu buka tautan kelas.';
                    return;
                }

                try {
                    const detector = new BarcodeDetector({ formats: ['qr_code'] });
                    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                    scannerVideo.srcObject = cameraStream;
                    scannerVideo.classList.remove('hidden');
                    scannerPanel.classList.remove('hidden');
                    scannerStatus.textContent = 'Arahkan kamera ke QR kelas.';
                    await scannerVideo.play();
                    scanning = true;

                    const scanFrame = async () => {
                        if (!scanning) return;
                        try {
                            const [result] = await detector.detect(scannerVideo);
                            if (result?.rawValue) {
                                const scannedUrl = new URL(result.rawValue, window.location.origin);
                                const code = scannedUrl.searchParams.get('join_code') || result.rawValue.trim();
                                if (scannedUrl.origin !== window.location.origin || !/^[a-z0-9]{8}$/i.test(code)) {
                                    scannerStatus.textContent = 'QR ini bukan tautan kelas SocratesRead yang valid.';
                                    requestAnimationFrame(scanFrame);
                                    return;
                                }

                                stopScanner();
                                window.location.assign(`/classrooms?join_code=${encodeURIComponent(code.toUpperCase())}&scan=1`);
                                return;
                            }
                        } catch {
                            scannerStatus.textContent = 'Kamera belum dapat membaca QR. Coba atur jarak dan pencahayaan.';
                        }
                        requestAnimationFrame(scanFrame);
                    };

                    requestAnimationFrame(scanFrame);
                } catch {
                    stopScanner();
                    scannerPanel?.classList.remove('hidden');
                    if (scannerStatus) scannerStatus.textContent = 'Kamera tidak dapat dibuka. Izinkan akses kamera atau gunakan kamera ponsel untuk memindai QR.';
                }
            });

            const scanJoin = new URLSearchParams(window.location.search).get('scan') === '1';
            const hasJoinError = @json($errors->has('join_code'));
            if (scanJoin && !hasJoinError && /^[a-z0-9]{8}$/i.test(codeInput.value)) {
                form?.requestSubmit();
            }
        })();
    </script>
@endsection
