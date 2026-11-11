@props([
    'prefix' => 'hero',
    'class' => 'w-64 h-72 sm:w-72 sm:h-80',
    'flying' => false,
])

<svg class="athena-mascot-svg {{ $class }} {{ $flying ? 'is-flying-mascot' : '' }}" 
     viewBox="{{ $flying ? '-140 -40 580 390' : '0 0 300 340' }}" 
     fill="none" 
     xmlns="http://www.w3.org/2000/svg" 
     aria-hidden="true">
    <defs>
        <!-- Feathers & Body Gradients -->
        <linearGradient id="{{ $prefix }}OwlBodyGrad" x1="50" y1="80" x2="250" y2="280" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#1E293B"/>
            <stop offset="45%" stop-color="#0F172A"/>
            <stop offset="100%" stop-color="#020617"/>
        </linearGradient>
        
        <linearGradient id="{{ $prefix }}OwlWingGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#1E3A8A"/>
            <stop offset="50%" stop-color="#0F172A"/>
            <stop offset="100%" stop-color="#090D16"/>
        </linearGradient>

        <!-- True Raptor Flight Wing Gradients -->
        <linearGradient id="{{ $prefix }}PrimaryFeatherGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#1E293B"/>
            <stop offset="60%" stop-color="#0F172A"/>
            <stop offset="100%" stop-color="#020617"/>
        </linearGradient>

        <linearGradient id="{{ $prefix }}SecondaryFeatherGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#2563EB"/>
            <stop offset="40%" stop-color="#1D4ED8"/>
            <stop offset="100%" stop-color="#0F172A"/>
        </linearGradient>

        <linearGradient id="{{ $prefix }}CovertGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#3B82F6"/>
            <stop offset="60%" stop-color="#1E3A8A"/>
            <stop offset="100%" stop-color="#0F172A"/>
        </linearGradient>

        <linearGradient id="{{ $prefix }}OwlBibGrad" x1="150" y1="140" x2="150" y2="240" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#FDFBF7"/>
            <stop offset="50%" stop-color="#F5EEDB"/>
            <stop offset="100%" stop-color="#E5D6BA"/>
        </linearGradient>

        <linearGradient id="{{ $prefix }}OwlGoldGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#FCD34D"/>
            <stop offset="50%" stop-color="#F59E0B"/>
            <stop offset="100%" stop-color="#B45309"/>
        </linearGradient>

        <radialGradient id="{{ $prefix }}OwlEyeSclera" cx="50%" cy="50%" r="50%">
            <stop offset="70%" stop-color="#FFFFFF"/>
            <stop offset="100%" stop-color="#E2E8F0"/>
        </radialGradient>

        <radialGradient id="{{ $prefix }}OwlIrisGrad" cx="40%" cy="40%" r="60%">
            <stop offset="0%" stop-color="#FBBF24"/>
            <stop offset="65%" stop-color="#D97706"/>
            <stop offset="100%" stop-color="#78350F"/>
        </radialGradient>

        <filter id="{{ $prefix }}FeatherShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="-2" dy="4" stdDeviation="3" flood-color="#000000" flood-opacity="0.45"/>
        </filter>
    </defs>

    @if(!$flying)
    <!-- Perch: Stack of Antique Books (Only when not flying) -->
    <g class="owl-perch-books">
        <!-- Bottom Book (Politeia - Navy) -->
        <rect x="35" y="295" width="230" height="28" rx="4" fill="#0B132B" stroke="#F59E0B" stroke-width="1.5"/>
        <rect x="250" y="298" width="12" height="22" fill="#E2E8F0"/>
        <line x1="50" y1="309" x2="220" y2="309" stroke="#F59E0B" stroke-width="1" stroke-dasharray="3 3"/>
        
        <!-- Middle Book (Kritik Nalar - Crimson/Violet) -->
        <rect x="50" y="272" width="200" height="25" rx="4" fill="#4C1D95" stroke="#A78BFA" stroke-width="1.2"/>
        <rect x="235" y="275" width="10" height="19" fill="#F1F5F9"/>

        <!-- Top Book (Dialektika - Forest/Gold) -->
        <rect x="65" y="252" width="170" height="22" rx="4" fill="#1E3A8A" stroke="#60A5FA" stroke-width="1.2"/>
        <rect x="220" y="255" width="10" height="16" fill="#F8FAFC"/>
    </g>
    @else
    <!-- Fanned Owl Tail Feathers (Authentic Bird Tail in Flight) -->
    <g class="owl-tail-feathers">
        <path d="M142 220 L136 295 Q150 305 164 295 L158 220 Z" fill="#0B132B" stroke="#1E293B" stroke-width="1.5"/>
        <path d="M136 220 L118 288 Q132 298 144 290 L146 220 Z" fill="#0F172A" stroke="#1E293B" stroke-width="1.5"/>
        <path d="M130 220 L100 274 Q114 284 125 276 L138 220 Z" fill="#152033" stroke="#1E293B" stroke-width="1.5"/>
        <path d="M164 220 L182 288 Q168 298 156 290 L154 220 Z" fill="#0F172A" stroke="#1E293B" stroke-width="1.5"/>
        <path d="M170 220 L200 274 Q186 284 175 276 L162 220 Z" fill="#152033" stroke="#1E293B" stroke-width="1.5"/>
        <!-- Gold tail bars -->
        <path d="M108 265 Q150 285 192 265" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" opacity="0.5" fill="none"/>
        <path d="M118 278 Q150 295 182 278" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.4" fill="none"/>
    </g>
    @endif

    <!-- Majestic Raptor Wingspan in Flight (Sweeping avian wings with slotted primaries) -->
    @if($flying)
    <g class="owl-flight-wings-group">
        <!-- LEFT WING -->
        <g id="{{ $prefix }}-flight-wing-left" class="owl-flight-wing owl-flight-wing-left" filter="url(#{{ $prefix }}FeatherShadow)">
            <!-- Primary Feathers (Layered slotted feather blades) -->
            <!-- P1 (Outermost wingtip quill) -->
            <path d="M-30 45 C-65 25 -105 20 -125 32 C-132 36 -130 44 -120 50 C-85 66 -45 80 -5 88 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M-25 48 Q-75 35 -122 36" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.6"/>

            <!-- P2 -->
            <path d="M-20 62 C-60 48 -105 48 -122 62 C-128 67 -124 75 -112 80 C-80 94 -40 102 5 106 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M-15 65 Q-70 55 -118 66" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.5"/>

            <!-- P3 -->
            <path d="M-10 78 C-52 68 -95 72 -110 88 C-115 94 -110 102 -98 106 C-70 118 -30 122 15 120 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M-5 80 Q-60 76 -106 92" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.5"/>

            <!-- P4 -->
            <path d="M5 95 C-38 90 -82 98 -95 114 C-100 120 -94 128 -82 130 C-55 138 -15 138 28 132 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M8 98 Q-45 98 -90 118" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.5"/>

            <!-- P5 -->
            <path d="M18 112 C-25 110 -65 120 -78 138 C-82 144 -75 152 -62 152 C-38 152 2 148 38 140 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#1D4ED8" stroke-width="1.2"/>

            <!-- P6 -->
            <path d="M30 126 C-12 126 -48 140 -58 158 C-62 165 -54 172 -42 172 C-20 168 18 158 48 148 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#1D4ED8" stroke-width="1.2"/>

            <!-- Secondaries (Inner trailing feathers) -->
            <path d="M42 138 C10 142 -25 158 -32 175 C-35 182 -25 188 -14 186 C10 180 35 168 58 154 Z" 
                  fill="url(#{{ $prefix }}SecondaryFeatherGrad)" stroke="#2563EB" stroke-width="1"/>
            <path d="M58 148 C30 155 0 172 -5 188 C-8 194 2 200 14 196 C35 188 60 174 72 158 Z" 
                  fill="url(#{{ $prefix }}SecondaryFeatherGrad)" stroke="#2563EB" stroke-width="1"/>
            <path d="M72 155 C50 165 25 184 22 200 C20 206 32 210 44 204 C65 194 85 178 90 160 Z" 
                  fill="url(#{{ $prefix }}SecondaryFeatherGrad)" stroke="#2563EB" stroke-width="1"/>

            <!-- Greater Coverts (Middle plumage layer) -->
            <path d="M105 138 C75 95 20 60 -30 45 C-50 55 -40 85 0 108 C35 128 75 142 105 138 Z" 
                  fill="url(#{{ $prefix }}CovertGrad)" stroke="#60A5FA" stroke-width="1.2"/>
            <!-- Covert feather scale details -->
            <path d="M25 80 Q-5 95 -20 115" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>
            <path d="M45 92 Q15 110 -2 130" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>
            <path d="M65 105 Q35 125 18 145" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>

            <!-- Leading Edge Arm Bone & Lesser Coverts -->
            <path d="M110 132 C85 92 40 55 -15 38 C-25 42 -20 54 -10 60 C30 78 75 110 110 132 Z" 
                  fill="#1E293B" stroke="#F59E0B" stroke-width="1.5"/>
        </g>

        <!-- RIGHT WING (Symmetrically Mirrored) -->
        <g id="{{ $prefix }}-flight-wing-right" class="owl-flight-wing owl-flight-wing-right" filter="url(#{{ $prefix }}FeatherShadow)">
            <!-- Primary Feathers -->
            <!-- P1 (Outermost wingtip quill) -->
            <path d="M330 45 C365 25 405 20 425 32 C432 36 430 44 420 50 C385 66 345 80 305 88 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M325 48 Q375 35 422 36" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.6"/>

            <!-- P2 -->
            <path d="M320 62 C360 48 405 48 422 62 C428 67 424 75 412 80 C380 94 340 102 295 106 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M315 65 Q370 55 418 66" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.5"/>

            <!-- P3 -->
            <path d="M310 78 C352 68 395 72 410 88 C415 94 410 102 398 106 C370 118 330 122 285 120 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M305 80 Q360 76 406 92" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.5"/>

            <!-- P4 -->
            <path d="M295 95 C338 90 382 98 395 114 C400 120 394 128 382 130 C355 138 315 138 272 132 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#3B82F6" stroke-width="1.2"/>
            <path d="M292 98 Q345 98 390 118" stroke="#F59E0B" stroke-width="1.2" stroke-linecap="round" opacity="0.5"/>

            <!-- P5 -->
            <path d="M282 112 C325 110 365 120 378 138 C382 144 375 152 362 152 C338 152 298 148 262 140 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#1D4ED8" stroke-width="1.2"/>

            <!-- P6 -->
            <path d="M270 126 C312 126 348 140 358 158 C362 165 354 172 342 172 C320 168 282 158 252 148 Z" 
                  fill="url(#{{ $prefix }}PrimaryFeatherGrad)" stroke="#1D4ED8" stroke-width="1.2"/>

            <!-- Secondaries (Inner trailing feathers) -->
            <path d="M258 138 C290 142 325 158 332 175 C335 182 325 188 314 186 C290 180 265 168 242 154 Z" 
                  fill="url(#{{ $prefix }}SecondaryFeatherGrad)" stroke="#2563EB" stroke-width="1"/>
            <path d="M242 148 C270 155 300 172 305 188 C308 194 298 200 286 196 C265 188 240 174 228 158 Z" 
                  fill="url(#{{ $prefix }}SecondaryFeatherGrad)" stroke="#2563EB" stroke-width="1"/>
            <path d="M228 155 C250 165 275 184 278 200 C280 206 268 210 256 204 C235 194 215 178 210 160 Z" 
                  fill="url(#{{ $prefix }}SecondaryFeatherGrad)" stroke="#2563EB" stroke-width="1"/>

            <!-- Greater Coverts (Middle plumage layer) -->
            <path d="M195 138 C225 95 280 60 330 45 C350 55 340 85 300 108 C265 128 225 142 195 138 Z" 
                  fill="url(#{{ $prefix }}CovertGrad)" stroke="#60A5FA" stroke-width="1.2"/>
            <!-- Covert feather scale details -->
            <path d="M275 80 Q305 95 320 115" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>
            <path d="M255 92 Q285 110 302 130" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>
            <path d="M235 105 Q265 125 282 145" stroke="#F59E0B" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>

            <!-- Leading Edge Arm Bone & Lesser Coverts -->
            <path d="M190 132 C215 92 260 55 315 38 C325 42 320 54 310 60 C270 78 225 110 190 132 Z" 
                  fill="#1E293B" stroke="#F59E0B" stroke-width="1.5"/>
        </g>
    </g>
    @endif

    <!-- Owl Talons -->
    @if(!$flying)
    <g class="owl-talons" fill="#F59E0B">
        <!-- Left foot -->
        <rect x="105" y="246" width="7" height="12" rx="3.5"/>
        <rect x="114" y="246" width="7" height="12" rx="3.5"/>
        <rect x="123" y="246" width="7" height="12" rx="3.5"/>
        <!-- Right foot -->
        <rect x="170" y="246" width="7" height="12" rx="3.5"/>
        <rect x="179" y="246" width="7" height="12" rx="3.5"/>
        <rect x="188" y="246" width="7" height="12" rx="3.5"/>
    </g>
    @else
    <!-- Tucked Raptor Talons in Flight (Curled golden claws) -->
    <g class="owl-flight-talons">
        <!-- Left Foot Claws -->
        <path d="M118 225 Q110 238 116 248 Q124 242 124 225 Z" fill="#F59E0B" stroke="#B45309" stroke-width="1.2"/>
        <path d="M125 225 Q122 242 130 250 Q136 244 132 225 Z" fill="#FCD34D" stroke="#B45309" stroke-width="1.2"/>
        <circle cx="116" cy="245" r="2" fill="#78350F"/>
        <circle cx="130" cy="247" r="2" fill="#78350F"/>
        <!-- Right Foot Claws -->
        <path d="M182 225 Q190 238 184 248 Q176 242 176 225 Z" fill="#F59E0B" stroke="#B45309" stroke-width="1.2"/>
        <path d="M175 225 Q178 242 170 250 Q164 244 168 225 Z" fill="#FCD34D" stroke="#B45309" stroke-width="1.2"/>
        <circle cx="184" cy="245" r="2" fill="#78350F"/>
        <circle cx="170" cy="247" r="2" fill="#78350F"/>
    </g>
    @endif

    <!-- Owl Main Body (Torso with feathered breast and chevron markings) -->
    <g class="owl-body-group">
        <!-- Back Body Silhouette: broad shoulders tapering to tail -->
        <path d="M102 135 C88 165 92 210 110 235 C124 248 176 248 190 235 C208 210 212 165 198 135 C178 128 122 128 102 135 Z" 
              fill="#0F172A" stroke="#1E293B" stroke-width="1.5"/>

        @if(!$flying)
        <!-- Left & Right Folded Wings (Only when perched) -->
        <path d="M70 145 C60 180 65 220 85 245 C95 220 100 185 95 155 Z" fill="url(#{{ $prefix }}OwlWingGrad)" class="owl-wing-folded owl-wing-folded-left"/>
        <path d="M230 145 C240 180 235 220 215 245 C205 220 200 185 205 155 Z" fill="url(#{{ $prefix }}OwlWingGrad)" class="owl-wing-folded owl-wing-folded-right"/>
        @endif

        <!-- Feathered Breast (Cream Parchment) -->
        <path d="M115 145 C110 190 122 234 150 236 C178 234 190 190 185 145 C165 138 135 138 115 145 Z" 
              fill="url(#{{ $prefix }}OwlBibGrad)" 
              stroke="#D4C5A9" 
              stroke-width="1.2"/>

        <!-- Chevron Plumage Bars (∧ ∧ ∧ characteristic of owl plumage) -->
        <g stroke="#8C7A5B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.8">
            <path d="M132 162 L136 166 L140 162"/>
            <path d="M146 162 L150 166 L154 162"/>
            <path d="M160 162 L164 166 L168 162"/>

            <path d="M125 178 L130 183 L135 178"/>
            <path d="M139 178 L144 183 L149 178"/>
            <path d="M153 178 L158 183 L163 178"/>
            <path d="M167 178 L172 183 L177 178"/>

            <path d="M130 196 L135 201 L140 196"/>
            <path d="M145 196 L150 201 L155 196"/>
            <path d="M160 196 L165 201 L170 196"/>

            <path d="M140 214 L145 218 L150 214"/>
            <path d="M152 214 L157 218 L162 214"/>
        </g>
    </g>

    <!-- Owl Head Group (Responds to tilt & cursor) -->
    <g id="{{ $prefix }}-owl-head-group" class="owl-head-group">
        <!-- Head Shape -->
        <ellipse cx="150" cy="112" rx="68" ry="52" fill="#0F172A" stroke="#1E293B" stroke-width="1.5"/>

        <!-- Prominent Feathered Ear Tufts / Horns (Plumicorns - Athena's Horns) -->
        <polygon points="90,70 72,30 108,58" fill="#0F172A" stroke="#1E293B" stroke-width="1.5"/>
        <path d="M78 36 L102 58" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>

        <polygon points="210,70 228,30 192,58" fill="#0F172A" stroke="#1E293B" stroke-width="1.5"/>
        <path d="M222 36 L198 58" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>

        <!-- Facial Discs (Left & Right round facial feather discs) -->
        <circle cx="118" cy="112" r="31" fill="#132247" stroke="#2563EB" stroke-width="2"/>
        <circle cx="182" cy="112" r="31" fill="#132247" stroke="#2563EB" stroke-width="2"/>

        <circle cx="118" cy="112" r="27" stroke="#334155" stroke-width="1.2" stroke-dasharray="3 3" fill="none"/>
        <circle cx="182" cy="112" r="27" stroke="#334155" stroke-width="1.2" stroke-dasharray="3 3" fill="none"/>

        <!-- Eye Sclera (White base) -->
        <circle cx="118" cy="112" r="23" fill="url(#{{ $prefix }}OwlEyeSclera)"/>
        <circle cx="182" cy="112" r="23" fill="url(#{{ $prefix }}OwlEyeSclera)"/>

        <!-- Irises (Wise golden-amber gaze) -->
        <circle cx="118" cy="112" r="16" fill="url(#{{ $prefix }}OwlIrisGrad)"/>
        <circle cx="182" cy="112" r="16" fill="url(#{{ $prefix }}OwlIrisGrad)"/>

        <!-- Dynamic Pupils (Move with Cursor) -->
        <g id="{{ $prefix }}-owl-pupil-left" class="owl-pupil">
            <circle cx="118" cy="112" r="9" fill="#090D16"/>
            <circle cx="115" cy="109" r="3" fill="#FFFFFF"/>
            <circle cx="121" cy="115" r="1.5" fill="#FFFFFF" opacity="0.7"/>
        </g>

        <g id="{{ $prefix }}-owl-pupil-right" class="owl-pupil">
            <circle cx="182" cy="112" r="9" fill="#090D16"/>
            <circle cx="179" cy="109" r="3" fill="#FFFFFF"/>
            <circle cx="185" cy="115" r="1.5" fill="#FFFFFF" opacity="0.7"/>
        </g>

        <!-- Eyelids for Natural Blinking -->
        <g class="owl-eyelid" fill="#0F172A">
            <path d="M95 112 A23 23 0 0 1 141 112 Z"/>
            <path d="M159 112 A23 23 0 0 1 205 112 Z"/>
        </g>

        <!-- Scholarly Spectacles (Kacamata Emas Cendekiawan) -->
        <g stroke="url(#{{ $prefix }}OwlGoldGrad)" stroke-width="3" fill="none">
            <!-- Left Lens Rim -->
            <circle cx="118" cy="112" r="26"/>
            <!-- Right Lens Rim -->
            <circle cx="182" cy="112" r="26"/>
            <!-- Bridge connecting rims -->
            <path d="M144 112 Q150 108 156 112" stroke-linecap="round"/>
        </g>

        <!-- Specular Lens Glint -->
        <line x1="108" y1="100" x2="128" y2="124" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" opacity="0.8"/>
        <line x1="172" y1="100" x2="192" y2="124" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" opacity="0.8"/>

        <!-- Authentic Hooked Raptor Beak -->
        <path d="M144 114 Q150 110 156 114 L154 135 Q150 144 146 135 Z" 
              fill="url(#{{ $prefix }}OwlGoldGrad)" stroke="#92400E" stroke-width="1.2"/>
        <line x1="150" y1="112" x2="150" y2="138" stroke="#FDE68A" stroke-width="1" stroke-linecap="round" opacity="0.8"/>

        <!-- Graduation Cap / Scholar Mortarboard -->
        <g class="owl-mortarboard">
            <!-- Cap Diamond -->
            <polygon points="150,26 205,44 150,62 95,44" fill="#0F172A" stroke="#F59E0B" stroke-width="1.5"/>
            <!-- Cap Skull base -->
            <path d="M118 52 C118 66 182 66 182 52" fill="#1E293B"/>
            <!-- Gold Button and Tassel -->
            <circle cx="150" cy="44" r="3" fill="#FBBF24"/>
            <path d="M150 44 Q180 48 188 74" stroke="#F59E0B" stroke-width="2" fill="none"/>
            <rect x="185" y="74" width="6" height="12" rx="2" fill="#D97706"/>
        </g>
    </g>
</svg>
