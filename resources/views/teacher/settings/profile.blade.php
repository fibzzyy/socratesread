@extends('layouts.teacher')

@section('title', 'Halaman Profil Guru')
@section('breadcrumb', 'Profil')

@section('content')
    <section class="stage-heading" aria-labelledby="profile-title">
        <div>
            <p class="eyebrow">AKUN PRIBADI</p>
            <h1 id="profile-title">Profil Guru</h1>
            <p class="welcome-subtitle">Kelola informasi pribadi, foto profil pengajar, dan identitas kelas Anda.</p>
        </div>
    </section>

    @if (session('status'))
        <div class="auth-notice" role="status" style="display: flex; align-items: center; gap: 8px;">
            <x-icon name="check" style="width: 18px; height: 18px; color: #16a34a;" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="profile-layout-grid">
        <!-- Kartu Ringkasan Akun Guru -->
        <aside class="panel profile-summary-card">
            <div class="summary-avatar-wrapper">
                <div class="avatar summary-avatar">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="avatar-image" id="summaryAvatarImg">
                    @else
                        <span id="summaryAvatarPlaceholder" style="font-size: 34px; font-weight: 700;">
                            {{ mb_strtoupper(collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')) }}
                        </span>
                    @endif
                </div>
                <span class="role-badge role-badge-teacher">Guru</span>
            </div>

            <div class="summary-details">
                <h2 class="summary-name">{{ $user->name }}</h2>
                <p class="summary-email">{{ $user->email }}</p>
                <div class="summary-divider"></div>
                <div class="summary-meta-item">
                    <span class="meta-label">Peran</span>
                    <strong class="meta-value">Guru / Pendidik</strong>
                </div>
                <div class="summary-meta-item">
                    <span class="meta-label">Bergabung sejak</span>
                    <strong class="meta-value">{{ $user->created_at?->locale('id')->translatedFormat('d F Y') ?? '-' }}</strong>
                </div>
            </div>
        </aside>

        <!-- Form Edit Profil & Upload Foto -->
        <section class="panel profile-edit-panel" aria-labelledby="edit-profile-heading">
            <div class="section-heading">
                <div>
                    <p class="section-overline">INFORMASI PROFIL</p>
                    <h2 id="edit-profile-heading">Perbarui Data Guru</h2>
                </div>
                <span class="session-icon"><x-icon name="user" /></span>
            </div>

            <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data" class="profile-form" id="profileForm">
                @csrf
                @method('PUT')

                <!-- Hidden Input untuk gambar hasil crop (Base64) -->
                <input type="hidden" name="avatar_base64" id="avatar_base64" value="">

                <!-- Bagian Foto Profil & Fitur Crop Sederhana -->
                <div class="profile-avatar-row">
                    <div class="avatar-preview-box">
                        <div class="avatar-preview" id="formAvatarPreview">
                            @if ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="Foto profil" class="avatar-image">
                            @else
                                <div class="avatar avatar-preview-placeholder">
                                    {{ mb_strtoupper(collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')) }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="avatar-upload-info">
                        <label class="section-sublabel">Foto Profil Guru</label>
                        <p class="upload-hint">Pilih foto dari komputer atau HP Anda, lalu sesuaikan bagian yang ingin ditampilkan.</p>
                        
                        <div class="upload-button-group">
                            <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="upload-file-hidden">
                            <button type="button" class="btn-choose-photo" id="triggerUploadBtn">
                                <span>Pilih Foto</span>
                            </button>
                            <span id="cropStatusBadge" class="crop-status-badge" style="display: none;">Foto baru siap disimpan</span>
                        </div>

                        <small class="upload-help">Format: JPG, PNG, atau WebP.</small>
                        @error('avatar')
                            <small class="upload-field-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="form-divider"></div>

                <!-- Input Nama & Email -->
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nama Lengkap & Gelar</label>
                        <input id="name" name="name" type="text" class="form-input @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autocomplete="name">
                        @error('name')
                            <small class="form-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">Alamat Email</label>
                        <input id="email" name="email" type="email" class="form-input @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="email">
                        @error('email')
                            <small class="form-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="challenge-button">
                        <span>Simpan Perubahan</span>
                        <x-icon name="arrow-right" />
                    </button>
                    <a href="{{ route('teacher.dashboard') }}" class="form-cancel-link">Batal</a>
                </div>
            </form>
        </section>
    </div>

    <!-- MODAL CROP SEDERHANA -->
    <div id="cropModal" class="simple-crop-backdrop" style="display: none;">
        <div class="simple-crop-card">
            <div class="simple-crop-header">
                <h3>Sesuaikan Posisi Foto</h3>
                <button type="button" class="simple-crop-close" id="closeCropModalBtn" aria-label="Tutup">&times;</button>
            </div>

            <p class="simple-crop-instructions">Geser foto untuk menyesuaikan posisinya di dalam lingkaran.</p>

            <div class="simple-crop-canvas-container">
                <canvas id="cropCanvas" width="260" height="260"></canvas>
                <div class="simple-crop-circle-mask"></div>
            </div>

            <div class="simple-crop-zoom-row">
                <span class="zoom-label">Zoom:</span>
                <input type="range" id="zoomSlider" min="0.5" max="3" step="0.05" value="1" class="simple-zoom-slider" aria-label="Atur zoom foto">
            </div>

            <div class="simple-crop-actions">
                <button type="button" class="btn-simple-cancel" id="cancelCropBtn">Batal</button>
                <button type="button" class="btn-simple-apply" id="applyCropBtn">Gunakan Foto</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const fileInput = document.getElementById('avatar');
    const triggerBtn = document.getElementById('triggerUploadBtn');
    const cropModal = document.getElementById('cropModal');
    const closeBtn = document.getElementById('closeCropModalBtn');
    const cancelBtn = document.getElementById('cancelCropBtn');
    const applyBtn = document.getElementById('applyCropBtn');
    const zoomSlider = document.getElementById('zoomSlider');
    const canvas = document.getElementById('cropCanvas');
    const ctx = canvas.getContext('2d');
    const avatarBase64Input = document.getElementById('avatar_base64');
    const formAvatarPreview = document.getElementById('formAvatarPreview');
    const cropStatusBadge = document.getElementById('cropStatusBadge');

    let image = new Image();
    let scale = 1;
    let baseScale = 1;
    let posX = 0;
    let posY = 0;
    let isDragging = false;
    let startX = 0;
    let startY = 0;

    triggerBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            image = new Image();
            image.onload = () => {
                const minDim = Math.min(image.width, image.height);
                baseScale = canvas.width / minDim;
                scale = baseScale;
                posX = 0;
                posY = 0;
                zoomSlider.min = (baseScale * 0.7).toFixed(2);
                zoomSlider.max = (baseScale * 3.5).toFixed(2);
                zoomSlider.value = scale.toFixed(2);
                draw();
                cropModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };
            image.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });

    function closeModal() {
        cropModal.style.display = 'none';
        document.body.style.overflow = '';
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.save();
        ctx.translate(canvas.width / 2 + posX, canvas.height / 2 + posY);
        ctx.scale(scale, scale);
        ctx.drawImage(image, -image.width / 2, -image.height / 2);
        ctx.restore();
    }

    // Drag / Geser dengan Mouse
    canvas.addEventListener('mousedown', (e) => {
        isDragging = true;
        startX = e.clientX - posX;
        startY = e.clientY - posY;
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        posX = e.clientX - startX;
        posY = e.clientY - startY;
        draw();
    });

    window.addEventListener('mouseup', () => { isDragging = false; });

    // Drag / Geser di HP (Touch)
    canvas.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            isDragging = true;
            startX = e.touches[0].clientX - posX;
            startY = e.touches[0].clientY - posY;
        }
    }, { passive: true });

    window.addEventListener('touchmove', (e) => {
        if (!isDragging || e.touches.length !== 1) return;
        posX = e.touches[0].clientX - startX;
        posY = e.touches[0].clientY - startY;
        draw();
    }, { passive: true });

    window.addEventListener('touchend', () => { isDragging = false; });

    // Slider Zoom
    zoomSlider.addEventListener('input', (e) => {
        scale = parseFloat(e.target.value);
        draw();
    });

    // Tombol Gunakan Foto
    applyBtn.addEventListener('click', () => {
        const outCanvas = document.createElement('canvas');
        outCanvas.width = 300;
        outCanvas.height = 300;
        const outCtx = outCanvas.getContext('2d');

        const ratio = 300 / canvas.width;
        outCtx.save();
        outCtx.translate(150 + posX * ratio, 150 + posY * ratio);
        outCtx.scale(scale * ratio, scale * ratio);
        outCtx.drawImage(image, -image.width / 2, -image.height / 2);
        outCtx.restore();

        const cropped = outCanvas.toDataURL('image/jpeg', 0.9);
        avatarBase64Input.value = cropped;
        formAvatarPreview.innerHTML = `<img src="${cropped}" alt="Foto profil baru" class="avatar-image">`;
        cropStatusBadge.style.display = 'inline-block';
        closeModal();
    });
})();
</script>
@endpush

<style>
    .profile-layout-grid {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 24px;
        align-items: start;
        margin-top: 10px;
    }
    @media (max-width: 900px) {
        .profile-layout-grid { grid-template-columns: 1fr; }
    }
    .profile-summary-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 30px 20px;
    }
    .summary-avatar-wrapper {
        position: relative;
        margin-bottom: 16px;
    }
    .summary-avatar {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        border: 4px solid #d1fae5;
        box-shadow: 0 4px 14px rgba(5, 150, 105, 0.15);
        overflow: hidden;
    }
    .role-badge {
        position: absolute;
        bottom: 2px;
        right: 2px;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        border: 2px solid #fff;
    }
    .role-badge-teacher { background: #059669; color: #fff; }
    .summary-name { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0 0 4px; }
    .summary-email { font-size: 13px; color: #64748b; margin: 0 0 16px; word-break: break-all; }
    .summary-divider { width: 100%; height: 1px; background: #e2e8f0; margin: 16px 0; }
    .summary-meta-item { display: flex; justify-content: space-between; width: 100%; font-size: 12px; margin-bottom: 10px; }
    .meta-label { color: #64748b; }
    .meta-value { color: #1e293b; font-weight: 600; }

    .profile-edit-panel { padding: 30px; }
    .profile-avatar-row {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 20px;
        align-items: center;
        margin: 20px 0;
    }
    .avatar-preview-box { display: flex; justify-content: center; }
    .avatar-preview {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid #cbd5e1;
        background: #f8fafc;
        display: grid;
        place-items: center;
    }
    .avatar-preview img, .avatar-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
    .avatar-preview-placeholder { font-size: 28px; font-weight: 700; color: #059669; }
    .section-sublabel { font-size: 13px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; }
    .upload-hint { font-size: 13px; color: #64748b; margin: 4px 0 12px; line-height: 1.5; }
    .upload-button-group { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .upload-file-hidden { display: none; }
    .btn-choose-photo {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        background: #059669;
        color: #fff;
        border-radius: 8px;
        border: none;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .btn-choose-photo:hover { background: #047857; }
    .crop-status-badge {
        padding: 6px 12px;
        background: #dcfce7;
        color: #166534;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }
    .upload-help { display: block; font-size: 12px; color: #94a3b8; margin-top: 8px; }
    .upload-field-error { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }
    .form-divider { height: 1px; background: #e2e8f0; margin: 24px 0; }
    .form-grid { display: grid; gap: 18px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
    .form-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        color: #0f172a;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        box-sizing: border-box;
    }
    .form-input:focus { outline: none; border-color: #059669; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15); }
    .form-input.is-invalid { border-color: #ef4444; }
    .form-error { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }
    .form-actions { display: flex; align-items: center; gap: 14px; margin-top: 26px; }
    .form-cancel-link { font-size: 14px; color: #64748b; text-decoration: none; padding: 10px 16px; border-radius: 8px; }
    .form-cancel-link:hover { background: #f1f5f9; color: #0f172a; }

    /* MODAL CROP SEDERHANA */
    .simple-crop-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(15, 23, 42, 0.6);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .simple-crop-card {
        background: #fff;
        border-radius: 14px;
        width: 100%;
        max-width: 330px;
        box-shadow: 0 16px 30px rgba(0, 0, 0, 0.2);
        padding: 20px;
        text-align: center;
    }
    .simple-crop-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .simple-crop-header h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }
    .simple-crop-close {
        background: none;
        border: none;
        font-size: 22px;
        color: #94a3b8;
        cursor: pointer;
        padding: 0;
    }
    .simple-crop-instructions { font-size: 12px; color: #64748b; margin: 0 0 16px; }
    .simple-crop-canvas-container {
        position: relative;
        width: 260px;
        height: 260px;
        margin: 0 auto 16px;
        border-radius: 10px;
        overflow: hidden;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
    }
    #cropCanvas {
        display: block;
        width: 260px;
        height: 260px;
        cursor: grab;
    }
    #cropCanvas:active { cursor: grabbing; }
    .simple-crop-circle-mask {
        position: absolute;
        inset: 0;
        pointer-events: none;
        border-radius: 50%;
        border: 2px dashed #059669;
        box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.45);
    }
    .simple-crop-zoom-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 18px;
        font-size: 12px;
        color: #475569;
    }
    .simple-zoom-slider {
        flex: 1;
        accent-color: #059669;
        cursor: pointer;
    }
    .simple-crop-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .btn-simple-cancel {
        padding: 8px 14px;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #475569;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-simple-apply {
        padding: 8px 16px;
        border: none;
        background: #059669;
        color: #fff;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-simple-apply:hover { background: #047857; }
</style>
