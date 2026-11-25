@php
    // Kunci ikon sama dengan SECTOR_ICONS pada CMS (admin.cms.index).
    $sectorIcons = [
        'bank'      => '<path d="M10 17l12-6 12 6M12 17v0h20M14 20v9M20 20v9M24 20v9M30 20v9M11 32h22"/>',
        'oilgas'    => '<path d="M22 10c5 6 8 10 8 14a8 8 0 0 1-16 0c0-4 3-8 8-14z M22 32c-2 0-3-1.5-3-3"/>',
        'factory'   => '<path d="M10 32V20l8 5v-5l8 5v-5l8 5v7z M14 32v-4M22 32v-4M30 32v-4"/>',
        'logistics' => '<path d="M9 14h16v14H9z M25 19h6l4 5v4H25z"/><circle cx="16" cy="30" r="2.5"/><circle cx="30" cy="30" r="2.5"/>',
        'hospital'  => '<path d="M12 12h20v20H12z M22 17v10M17 22h10"/>',
        'retail'    => '<path d="M12 17h20l-1.5 15h-17z M17 17v-2a5 5 0 0 1 10 0v2"/>',
        'education' => '<path d="M22 12l13 6-13 6-13-6z M15 21v6c0 2 3.5 4 7 4s7-2 7-4v-6 M35 18v8"/>',
        'general'   => '<path d="M12 32V14h12v18M24 32V22h8v10M10 32h24"/>',
    ];
    $iconSvg = $sectorIcons[$icon ?? 'general'] ?? $sectorIcons['general'];
@endphp
<svg class="mx-auto mb-4 block h-[60px] w-[60px]" viewBox="0 0 44 44" fill="none" aria-hidden="true"><rect width="44" height="44" rx="14" fill="#e7e9f6"/><g stroke="#0D21A1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{!! $iconSvg !!}</g></svg>