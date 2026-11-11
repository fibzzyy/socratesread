import React from 'react';
import SpotlightCard from './SpotlightCard';

/**
 * ParadigmaComparison (Inspired by 21st.dev & ReactBits.dev)
 * Side-by-side comparison of "Membaca Pasif" vs "Membaca Reflektif Sokratik"
 * with interactive cursor spotlight, preserving 2-column comparison on all screen sizes.
 */
export default function ParadigmaComparison() {
    return (
        <div className="grid grid-cols-2 gap-2 sm:gap-6 lg:gap-8 items-stretch w-full">
            {/* Card 1: Membaca Pasif & Hafalan */}
            <SpotlightCard
                className="p-3 sm:p-6 lg:p-8 bg-[#0F172A]/90 border border-slate-800 text-slate-200 shadow-lg hover:border-slate-700 transition-colors"
                spotlightColor="rgba(239, 68, 68, 0.12)"
                borderColor="rgba(239, 68, 68, 0.35)"
                size={300}
            >
                <div>
                    {/* Header */}
                    <div className="flex items-center gap-1.5 sm:gap-3 mb-2.5 sm:mb-4">
                        <div className="w-6 h-6 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-red-950/80 border border-red-800/60 text-red-400 flex items-center justify-center font-bold text-xs sm:text-base shrink-0">
                            ✕
                        </div>
                        <div>
                            <h3 className="font-serif-reading text-xs sm:text-lg lg:text-xl font-bold text-white leading-tight">
                                Membaca Pasif & Hafalan
                            </h3>
                            <p className="text-[9px] sm:text-xs text-slate-400 hidden sm:block mt-0.5">
                                Menerima teks apa adanya tanpa pengujian
                            </p>
                        </div>
                    </div>

                    {/* Bullet Points */}
                    <ul className="space-y-1.5 sm:space-y-3.5 text-[10px] sm:text-sm text-slate-300 my-2 sm:my-4">
                        <li className="flex items-start gap-1 sm:gap-2.5">
                            <span className="text-red-400 font-bold shrink-0 text-[11px] sm:text-base">•</span>
                            <span>Menelan premis mentah-mentah tanpa verifikasi validitas.</span>
                        </li>
                        <li className="flex items-start gap-1 sm:gap-2.5">
                            <span className="text-red-400 font-bold shrink-0 text-[11px] sm:text-base">•</span>
                            <span>Fokus hafal kata kunci demi nilai ujian pilihan ganda.</span>
                        </li>
                        <li className="flex items-start gap-1 sm:gap-2.5">
                            <span className="text-red-400 font-bold shrink-0 text-[11px] sm:text-base">•</span>
                            <span>Mudah terperdaya sesat pikir (fallacy) dan bias narasi.</span>
                        </li>
                    </ul>
                </div>

                {/* Outcome Footer */}
                <div className="p-2 sm:p-4 rounded-lg sm:rounded-xl bg-slate-900/90 border border-slate-800 text-[9px] sm:text-xs text-slate-400 italic mt-2.5 sm:mt-5">
                    <strong className="text-slate-200 not-italic block sm:inline font-semibold">Hasil: </strong>
                    Membaca banyak buku, namun tidak melatih kemandirian berpikir saat menghadapi masalah nyata.
                </div>
            </SpotlightCard>

            {/* Card 2: Membaca Reflektif Sokratik (SocratesRead) */}
            <SpotlightCard
                className="p-3 sm:p-6 lg:p-8 bg-gradient-to-br from-[#0c1a3b]/95 via-[#0F172A]/90 to-[#1e1b4b]/80 border-2 border-blue-500/40 text-slate-100 shadow-xl shadow-blue-950/40 hover:border-blue-400 transition-colors"
                spotlightColor="rgba(59, 130, 246, 0.25)"
                borderColor="rgba(96, 165, 250, 0.6)"
                size={340}
            >
                <div>
                    {/* Header */}
                    <div className="flex items-center gap-1.5 sm:gap-3 mb-2.5 sm:mb-4">
                        <div className="w-6 h-6 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xs sm:text-base shrink-0 shadow-md shadow-blue-500/20">
                            ✓
                        </div>
                        <div>
                            <h3 className="font-serif-reading text-xs sm:text-lg lg:text-xl font-bold text-white leading-tight">
                                Membaca Reflektif Sokratik
                            </h3>
                            <p className="text-[9px] sm:text-xs text-blue-300 font-medium hidden sm:block mt-0.5">
                                Berdialog dan menguji logika argumen
                            </p>
                        </div>
                    </div>

                    {/* Bullet Points */}
                    <ul className="space-y-1.5 sm:space-y-3.5 text-[10px] sm:text-sm text-slate-200 my-2 sm:my-4">
                        <li className="flex items-start gap-1 sm:gap-2.5">
                            <span className="text-blue-400 font-bold shrink-0 text-[11px] sm:text-base">•</span>
                            <span>Mengidentifikasi asumsi tersembunyi yang tidak diungkap penulis.</span>
                        </li>
                        <li className="flex items-start gap-1 sm:gap-2.5">
                            <span className="text-blue-400 font-bold shrink-0 text-[11px] sm:text-base">•</span>
                            <span>Uji konsistensi penalaran dengan pertanyaan dialektis bertingkat.</span>
                        </li>
                        <li className="flex items-start gap-1 sm:gap-2.5">
                            <span className="text-blue-400 font-bold shrink-0 text-[11px] sm:text-base">•</span>
                            <span>Mampu merumuskan argumen tandingan adil berbasis bukti kuat.</span>
                        </li>
                    </ul>
                </div>

                {/* Outcome Footer */}
                <div className="p-2 sm:p-4 rounded-lg sm:rounded-xl bg-blue-950/70 border border-blue-800/60 text-[9px] sm:text-xs text-blue-100 font-medium shadow-xs mt-2.5 sm:mt-5">
                    <strong className="text-blue-300 block sm:inline font-bold">Hasil: </strong>
                    Membangun pikiran berdaulat yang tangguh menyaring misinformasi dan cerdas memecahkan persoalan.
                </div>
            </SpotlightCard>
        </div>
    );
}
