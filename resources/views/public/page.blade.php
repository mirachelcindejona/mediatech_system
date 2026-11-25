@php
    // $cms dibagikan oleh View composer (AppServiceProvider). Teks bawaan = isi company profile.
    $contact = $cms['contact'] ?? [];
    $home    = $cms['home'] ?? [];
    $fill = static fn ($value, $default) => filled($value) ? $value : $default;

    $siteTitle     = $fill($home['seoTitle'] ?? null, 'PT Meditech Cipta Inovasi');
    $siteDesc      = $fill($home['seoDesc'] ?? null, 'Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, dan komputer.');
    $companyName   = $fill($contact['companyName'] ?? null, 'PT Meditech Cipta Inovasi');
    $tagline       = $fill($contact['tagline'] ?? null, 'Motivation Creative Innovation With Technology');
    $address       = $fill($contact['address'] ?? null, 'Jl. Neo Paloma II Blok S10 No 26 Cluster Garden Ville, Desa Ciakar, Kec. Panongan, Kab. Tangerang, Banten.');
    $phone         = $fill($contact['phone'] ?? null, '021 - 8908 - 8898');
    $email         = $fill($contact['email'] ?? null, 'info@meditech.co.id');
    $copyright     = $fill($contact['copyright'] ?? null, '© ' . date('Y') . ' ' . $companyName);
    // Header (navbar) - bisa diatur via CMS (halaman Kontak)
    $siteName      = $fill($contact['siteName'] ?? null, 'MediTech');
    $headerLogo    = $fill($contact['headerLogo'] ?? null, asset('images/meditech-logo.jpg'));
    $headerLogoAlt = $fill($contact['headerLogoAlt'] ?? null, 'Logo Meditech');
@endphp
<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', $siteTitle)</title>
    <meta name="description" content="@yield('meta_description', $siteDesc)">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-white font-sans text-base leading-relaxed text-neutral-900 antialiased">
    <header class="sticky top-0 z-20 border-b border-neutral-200 bg-white">
        <div class="mx-auto max-w-[1180px] px-5 flex h-[68px] items-center justify-between gap-4">
            <a href="#top" class="flex items-center gap-2.5">
                <img src="{{ $headerLogo }}" alt="{{ $headerLogoAlt }}" class="h-[42px]">
                <span class="font-heading text-[20px] leading-7 font-bold tracking-[-0.5px] text-neutral-900">{{ $siteName }}</span>
            </a>
            <nav aria-label="Menu utama" class="hidden gap-5 text-sm font-medium text-neutral-600 lg:flex">
                <a href="#tentang" class="hover:text-primary-500">{{ $cmsFields["nav_tentang"] ?? "Tentang" }}</a>
                <a href="#visi" class="hover:text-primary-500">{{ $cmsFields["nav_visi"] ?? "Visi & Misi" }}</a>
                <a href="#produk" class="hover:text-primary-500">{{ $cmsFields["nav_produk"] ?? "Produk" }}</a>
                <a href="#proyek" class="hover:text-primary-500">{{ $cmsFields["nav_proyek"] ?? "Proyek" }}</a>
                <a href="#partner" class="hover:text-primary-500">{{ $cmsFields["nav_partner"] ?? "Partner" }}</a>
                <a href="#testimoni" class="hover:text-primary-500">{{ $cmsFields["nav_testimoni"] ?? "Testimoni" }}</a>
            </nav>
            <a href="#kontak" class="inline-block rounded-xl bg-secondary-500 px-6 py-3 font-heading text-[15px] font-semibold text-neutral-900 transition hover:bg-secondary-300">{{ $cmsFields["header_cta"] ?? "Hubungi kami" }}</a>
        </div>
    </header>

    <main id="top">
        @yield("content")
    </main>

    <footer class="bg-[#011638] pb-6 pt-14 text-sm text-primary-100">
        <div class="mx-auto max-w-[1180px] px-5">
            <div class="grid gap-10 md:grid-cols-[1.4fr_1fr_1.4fr]">
                <div>
                    <h4 class="mb-3.5 font-heading font-semibold text-white">{{ $companyName }}</h4>
                    <p>{{ $tagline }}</p>
                </div>
                <div>
                    <h4 class="mb-3.5 font-heading font-semibold text-white">Menu</h4>
                    <a href="#tentang" class="block py-0.5 hover:text-white">Tentang</a>
                    <a href="#visi" class="block py-0.5 hover:text-white">Visi & Misi</a>
                    <a href="#produk" class="block py-0.5 hover:text-white">Produk</a>
                    <a href="#proyek" class="block py-0.5 hover:text-white">Proyek</a>
                    <a href="#partner" class="block py-0.5 hover:text-white">Partner</a>
                    <a href="#kontak" class="block py-0.5 hover:text-white">Kontak</a>
                </div>
                <div>
                    <h4 class="mb-3.5 font-heading font-semibold text-white">Kontak</h4>
                    <p>{{ $address }}</p>
                    <p class="mt-2">{{ $phone }} &middot; {{ $email }}</p>
                </div>
            </div>
            <div class="mt-9 flex flex-wrap justify-between gap-3 border-t border-white/15 pt-5">
                <span>{{ $copyright }}</span>
                <span>Hak cipta dilindungi</span>
            </div>
        </div>
    </footer>

    @stack("scripts")
</body>
</html>