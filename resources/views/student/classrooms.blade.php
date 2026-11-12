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
                <p class="my-class-eyebrow">KOMUNITAS BELAJAR</p>
                <h1 id="classrooms-title"><span>Kelas saya</span></h1>
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
            <div class="my-class-join-copy">
                <div class="my-class-join-eyebrow">
                    <svg aria-hidden="true" width="30" height="30" viewBox="0 0 30 30" fill="none" stroke="currentColor" stroke-width="2"><circle cx="15" cy="15" r="12"/><circle cx="15" cy="15" r="7"/><circle cx="15" cy="15" r="2.4" fill="currentColor" stroke="none"/></svg>
                    <span>GABUNG BERSAMA GURU</span>
                </div>
                <h2 id="join-class-heading">Gabung kelas baru. Belajar bersama guru.</h2>

                <form method="POST" action="{{ route('classrooms.join') }}" class="my-class-join-form">
                    @csrf
                    <label for="join_code">Kode gabung kelas (8 karakter)</label>
                    <div class="my-class-join-controls">
                        <div class="my-class-code-field">
                            <input id="join_code" name="join_code" value="{{ old('join_code', $joinCode) }}" maxlength="8" minlength="8" required autocomplete="off" autocapitalize="characters" placeholder="CONTOH: BACA2026" aria-describedby="join-code-help join-code-count @error('join_code') join-code-error @enderror">
                            <span id="join-code-count" class="my-class-code-count" aria-live="polite">{{ mb_strlen(old('join_code', $joinCode ?? '')) }}/8</span>
                        </div>
                        <button type="submit" class="my-class-join-button">
                            <span>Gabung kelas</span>
                            <x-icon name="arrow-right" />
                        </button>
                    </div>
                    @error('join_code')
                        <span id="join-code-error" class="my-class-error" role="alert">{{ $message }}</span>
                    @enderror
                </form>
                <p id="join-code-help" class="my-class-privacy">Guru dapat melihat keterlibatan dan progres sesi pada bacaan yang mereka unggah, bukan isi jawaban pribadimu.</p>
            </div>
            <div class="my-class-mascot-wrap" aria-hidden="true">
                <span class="my-class-mascot-glow"></span>
                <img class="my-class-mascot" src="{{ asset('images/burhan.png') }}" alt="Burung hantu SocratesRead dengan topi wisuda dan buku">
            </div>
        </section>

        <section class="my-class-stats" aria-label="Ringkasan aktivitas kelas">
            <article class="my-class-stat">
                <div class="my-class-stat-top">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2.4"/><path d="M16.5 14a5 5 0 0 1 4.5 5"/></svg>
                    <span>Aktif</span>
                </div>
                <div class="my-class-stat-value"><strong>{{ $classrooms->count() }}</strong><span>kelas</span></div>
                <p>Kelas diikuti</p>
            </article>
            <article class="my-class-stat">
                <div class="my-class-stat-top">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M2 5h7a3 3 0 0 1 3 3v12a2 2 0 0 0-2-2H2z"/><path d="M22 5h-7a3 3 0 0 0-3 3v12a2 2 0 0 1 2-2h8z"/></svg>
                    <span>Dari guru</span>
                </div>
                <div class="my-class-stat-value"><strong>{{ $assignedReadingCount }}</strong><span>bacaan</span></div>
                <p>Bacaan ditugaskan</p>
            </article>
            <article class="my-class-stat">
                <div class="my-class-stat-top my-class-stat-gold">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M9 11h6M9 14.5h3.5"/></svg>
                    <span>Minggu ini</span>
                </div>
                <div class="my-class-stat-value"><strong>{{ $discussionSessionCount }}</strong><span>sesi</span></div>
                <p>Sesi diskusi</p>
            </article>
        </section>

        <section class="my-class-list" aria-labelledby="enrolled-classes-heading">
            <div class="my-class-list-heading">
                <div>
                    <p class="my-class-eyebrow">KELAS AKTIF</p>
                    <h2 id="enrolled-classes-heading">Kelas yang kamu ikuti</h2>
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

            const updateCounter = () => {
                codeInput.value = codeInput.value.toUpperCase().slice(0, 8);
                counter.textContent = `${codeInput.value.length}/8`;
            };

            codeInput.addEventListener('input', updateCounter);
            updateCounter();
        })();
    </script>
@endsection
