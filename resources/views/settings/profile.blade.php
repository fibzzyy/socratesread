@extends('layouts.student')

@section('title', 'Edit Profil Siswa')
@section('breadcrumb', 'Edit Profil')

@section('content')
    <section class="stage-heading" aria-labelledby="profile-title">
        <div>
            <p class="eyebrow">AKUN PRIBADI</p>
            <h1 id="profile-title">Edit Profil Siswa</h1>
            <p class="welcome-subtitle">Perbarui nama, foto, dan informasi belajar Anda.</p>
        </div>
        <a href="{{ route('profile') }}" class="form-cancel-link inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200/90 rounded-xl shadow-xs transition-colors no-underline">
            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
            <span>Kembali ke Profil</span>
        </a>
    </section>

    @if (session('status'))
        <div class="auth-notice" role="status">
            <x-icon name="check" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <section class="profile-edit-panel bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 sm:p-8 mt-4" aria-labelledby="edit-profile-heading">
        <div class="flex items-center justify-between pb-5 border-b border-slate-100">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 m-0">INFORMASI PROFIL</p>
                <h2 id="edit-profile-heading" class="text-xl font-bold text-slate-900 mt-1 m-0">Perbarui Data Pribadi</h2>
                <p class="text-xs text-slate-500 mt-0.5 m-0">Sesuaikan data identitas dan informasi akun siswa Anda.</p>
            </div>
            <span class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shadow-2xs">
                <x-icon name="user" class="w-5 h-5" />
            </span>
        </div>

        <form method="POST" action="{{ route('settings.profile.update') }}" enctype="multipart/form-data" class="profile-form mt-6" id="profileForm">
            @csrf
            @method('PUT')

            <!-- Hidden Input untuk gambar hasil crop (Base64) -->
            <input type="hidden" name="avatar_base64" id="avatar_base64" value="">

            <!-- Bagian Foto Profil & Fitur Crop Sederhana -->
            <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-100 flex flex-col sm:flex-row items-center sm:items-start gap-6">
                <div class="avatar-preview-box shrink-0">
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl overflow-hidden ring-4 ring-white shadow-md bg-slate-100 text-slate-400 flex items-center justify-center" id="formAvatarPreview">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="Foto profil" class="w-full h-full object-cover">
                        @else
                            <x-icon name="user" class="h-10 w-10" />
                        @endif
                    </div>
                </div>

                <div class="flex-1 text-center sm:text-left">
                    <label class="text-sm font-bold text-slate-900 block">Foto Profil</label>
                    <p class="text-xs text-slate-500 mt-1 m-0 leading-relaxed">
                        Pilih foto dari komputer atau HP Anda, lalu sesuaikan bagian yang ingin ditampilkan. Format: JPG, PNG, atau WebP.
                    </p>
                    
                    <div class="mt-4 flex items-center gap-3 flex-wrap justify-center sm:justify-start">
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <button type="button" class="btn-choose-photo inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl shadow-xs transition-all cursor-pointer" id="triggerUploadBtn">
                            <x-icon name="user" class="w-3.5 h-3.5 text-slate-500" />
                            <span>Pilih Foto Baru</span>
                        </button>
                        <span id="cropStatusBadge" class="crop-status-badge inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold rounded-full" style="display: none;">
                            <x-icon name="check" class="w-3 h-3 text-emerald-600" />
                            Foto baru siap disimpan
                        </span>
                    </div>

                    @error('avatar')
                        <small class="text-xs text-rose-600 font-medium mt-2 block">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <!-- Input Form Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-6">
                <!-- Nama Lengkap -->
                <div class="sm:col-span-2">
                    <label for="name" class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Lengkap</label>
                    <input id="name" 
                           name="name" 
                           type="text" 
                           class="w-full px-4 py-2.5 rounded-xl border @error('name') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all" 
                           value="{{ old('name', $user->name) }}" 
                           required 
                           autocomplete="name">
                    @error('name')
                        <small class="text-xs text-rose-600 font-medium mt-1 block">{{ $message }}</small>
                    @enderror
                </div>

                <!-- NISN -->
                <div class="sm:col-span-2">
                    <label for="nisn" class="block text-xs font-semibold text-slate-700 mb-1.5">NISN</label>
                    <input id="nisn" 
                           name="nisn" 
                           type="text" 
                           class="w-full px-4 py-2.5 rounded-xl border @error('nisn') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all" 
                           value="{{ old('nisn', $user->nisn) }}" 
                           maxlength="50" 
                           autocomplete="off"
                           placeholder="Nomor Induk Siswa Nasional">
                    @error('nisn')
                        <small class="text-xs text-rose-600 font-medium mt-1 block">{{ $message }}</small>
                    @enderror
                </div>

                <!-- Bio -->
                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="bio" class="text-xs font-semibold text-slate-700">Bio Belajar</label>
                        <span class="text-[11px] text-slate-400">(opsional)</span>
                    </div>
                    <textarea id="bio" 
                              name="bio" 
                              class="w-full px-4 py-2.5 rounded-xl border @error('bio') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all resize-y min-h-[100px]" 
                              rows="4" 
                              maxlength="1000" 
                              placeholder="Ceritakan minat membaca atau cita-cita Anda...">{{ old('bio', $user->bio) }}</textarea>
                    @error('bio')
                        <small class="text-xs text-rose-600 font-medium mt-1 block">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('profile') }}" class="px-5 py-2.5 text-xs font-semibold text-slate-600 hover:text-slate-900 rounded-xl transition-colors no-underline">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:scale-98 rounded-xl shadow-xs transition-all cursor-pointer">
                    <span>Simpan Perubahan</span>
                    <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                </button>
            </div>
        </form>
    </section>

    {{-- Form Keamanan (Password & Email) --}}
    @include('components.profile-password-form')

    <!-- MODAL CROP SEDERHANA -->
    <div id="cropModal" class="simple-crop-backdrop fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" style="display: none;">
        <div class="simple-crop-card bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 text-center">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-900 m-0">Sesuaikan Posisi Foto</h3>
                <button type="button" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center text-lg leading-none cursor-pointer" id="closeCropModalBtn" aria-label="Tutup">&times;</button>
            </div>

            <p class="text-xs text-slate-500 mt-3 m-0">Geser dan atur perbesaran foto agar pas di dalam lingkaran.</p>

            <div class="simple-crop-canvas-container relative w-[260px] h-[260px] mx-auto my-4 rounded-2xl overflow-hidden bg-slate-950 flex items-center justify-center">
                <canvas id="cropCanvas" width="260" height="260" class="cursor-grab active:cursor-grabbing"></canvas>
                <div class="simple-crop-circle-mask absolute inset-0 pointer-events-none rounded-full border-2 border-white/80 shadow-[0_0_0_9999px_rgba(15,23,42,0.6)]"></div>
            </div>

            <div class="simple-crop-zoom-row flex items-center justify-center gap-3 mb-5">
                <span class="text-xs font-semibold text-slate-500">Zoom:</span>
                <input type="range" id="zoomSlider" min="0.5" max="3" step="0.05" value="1" class="w-48 accent-blue-600 cursor-pointer" aria-label="Atur zoom foto">
            </div>

            <div class="simple-crop-actions flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer" id="cancelCropBtn">Batal</button>
                <button type="button" class="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xs transition-colors cursor-pointer" id="applyCropBtn">Gunakan Foto</button>
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
        formAvatarPreview.innerHTML = `<img src="${cropped}" alt="Foto profil baru" class="w-full h-full object-cover">`;
        cropStatusBadge.style.display = 'inline-flex';
        closeModal();
    });
})();
</script>
@endpush
