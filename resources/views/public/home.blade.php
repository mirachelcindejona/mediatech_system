@extends("layouts.public")

@php
    use App\Support\CmsContent as Cms;

    /*
    |--------------------------------------------------------------------------
    | Konten dibaca dari CMS (halaman berstatus Published). Bila kosong / Draft,
    | teks bawaan di bawah dipakai. Skema key: CmsController::PAGES.
    |--------------------------------------------------------------------------
    */
    $home     = Cms::page("home");
    $about    = Cms::page("about-us");
    $vm       = Cms::page("visi-misi");
    $produk   = Cms::page("produk-layanan");
    $proyek   = Cms::page("proyek-terbaru");
    $sektor   = Cms::page("sektor-pelanggan");
    $partnerP = Cms::page("partner-brand");
    $testi    = Cms::page("testimoni");
    $kontak   = Cms::page("kontak");
    $logo     = asset("images/meditech-logo.jpg");

    $defaultDesc = "Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, dan komputer.";

    // Ikon sektor (sama dengan pilihan ikon di CMS).
    $sectorIcons = [
        "bank"      => "M10 17l12-6 12 6M12 17v0h20M14 20v9M20 20v9M24 20v9M30 20v9M11 32h22",
        "oilgas"    => "M22 10c5 6 8 10 8 14a8 8 0 0 1-16 0c0-4 3-8 8-14z M22 32c-2 0-3-1.5-3-3",
        "factory"   => "M10 32V20l8 5v-5l8 5v-5l8 5v7z M14 32v-4M22 32v-4M30 32v-4",
        "logistics" => "M9 14h16v14H9z M25 19h6l4 5v4H25z M18.5 30.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z M32.5 30.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z",
        "hospital"  => "M12 12h20v20H12z M22 17v10M17 22h10",
        "retail"    => "M12 17h20l-1.5 15h-17z M17 17v-2a5 5 0 0 1 10 0v2",
        "education" => "M22 12l13 6-13 6-13-6z M15 21v6c0 2 3.5 4 7 4s7-2 7-4v-6 M35 18v8",
        "general"   => "M12 32V14h12v18M24 32V22h8v10M10 32h24",
    ];
    // Jika ikon belum dipilih di CMS, tebak dari nama sektor.
    $guessIcon = function (string $name) {
        $n = mb_strtolower($name);
        foreach (["bank" => "bank", "oli" => "oilgas", "gas" => "oilgas", "manufaktur" => "factory", "pabrik" => "factory",
                  "distribusi" => "logistics", "logistik" => "logistics", "rumah sakit" => "hospital", "kesehatan" => "hospital",
                  "retail" => "retail", "pendidikan" => "education", "sekolah" => "education", "universitas" => "education"] as $needle => $key) {
            if (str_contains($n, $needle)) return $key;
        }
        return "general";
    };

    $defaultStats = [
        ["value" => "150+", "label" => "Proyek terselesaikan"],
        ["value" => "60+",  "label" => "Klien yang percaya"],
        ["value" => "25+",  "label" => "Tim teknisi profesional"],
        ["value" => "15+",  "label" => "Brand mitra resmi"],
    ];
    $defaultSectors = array_map(fn ($n) => ["name" => $n, "icon" => ""], ["Perbankan", "Perusahaan oli & gas", "Manufaktur", "Perusahaan distribusi", "Rumah sakit", "Retail", "Pendidikan"]);

    $stats    = array_slice(Cms::l($home, "stats", $defaultStats), 0, 4);
    $services = Cms::l($produk, "services");
    $hardware = Cms::l($produk, "hardware");
    $projects = Cms::l($proyek, "items");
    $sectors  = Cms::l($sektor, "sectors", $defaultSectors);
    $testis   = Cms::l($testi, "items");
    $misi     = Cms::l($vm, "misi");
    $partners = Cms::partners();
@endphp

@section("title", Cms::t($home, "seoTitle", "PT Meditech Cipta Inovasi"))
@section("meta_description", Cms::t($home, "seoDesc", $defaultDesc))

@push("styles")
    {{-- Motion: elemen disembunyikan HANYA bila JS aktif dan pengguna tidak memilih "reduce motion".
         Bila Motion gagal dimuat dalam 4 detik, kelas dilepas agar konten tetap tampil. --}}
    <script>
        if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            document.documentElement.classList.add("js-motion");
            setTimeout(function () { if (!window.__motionReady) document.documentElement.classList.remove("js-motion"); }, 4000);
        }
    </script>
    <style>
        .js-motion [data-motion],
        .js-motion [data-motion-stagger] > * { opacity: 0; }
    </style>
@endpush

@section("content")
{{-- HERO --}}
<div class="pt-16">
    <div class="mx-auto max-w-[1180px] px-5 grid items-center gap-12 md:grid-cols-[1.05fr_.95fr]">
        <div>
            <h1 data-motion data-delay="0.05" class="font-heading text-4xl font-semibold leading-[1.18] text-primary-500 md:text-5xl lg:text-6xl">{{ Cms::t($home, "heroTitle", "Mitra instalasi dan perlengkapan teknologi Anda") }}</h1>
            <p data-motion data-delay="0.15" class="max-w-[58ch] text-base text-neutral-600 md:text-lg mb-8 mt-5">{{ Cms::t($home, "heroSub", $defaultDesc) }}</p>
            <div data-motion data-delay="0.25" class="flex flex-wrap gap-3">
                <a href="{{ Cms::link($home["ctaLink"] ?? null, "#kontak") }}" class="inline-block rounded-xl bg-secondary-500 px-6 py-3 font-heading text-[15px] font-semibold text-neutral-900 transition hover:bg-secondary-300">{{ Cms::t($home, "ctaLabel", "Minta penawaran") }}</a>
                <a href="{{ Cms::link($home["cta2Link"] ?? null, "#produk") }}" class="inline-block rounded-xl bg-white px-6 py-3 font-heading text-[15px] font-semibold text-primary-500 ring-2 ring-inset ring-primary-500 transition hover:bg-primary-50">{{ Cms::t($home, "cta2Label", "Lihat produk") }}</a>
            </div>
        </div>
        <img src="{{ Cms::t($home, "heroImg", $logo) }}" alt="{{ Cms::t($home, "heroImgAlt", "Gedung perkantoran modern") }}" data-motion="scale" data-delay="0.2" class="aspect-square w-full rounded-[32px] object-cover">
    </div>
    {{-- STATS CARD (overlap) --}}
    <div class="mx-auto max-w-[1180px] px-5">
        <div data-motion data-delay="0.35" data-stats class="relative z-[2] -mb-14 mt-14 grid grid-cols-2 gap-y-5 rounded-3xl border border-neutral-200 bg-white px-2 py-7 shadow-[0_16px_40px_-16px_rgba(13,33,161,.25)] md:grid-cols-4">
            @foreach($stats as $stat)
                <div class="border-neutral-200 px-3 text-center md:border-r {{ $loop->last ? "md:border-r-0" : "" }}">
                    <b class="block font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl">{{ $stat["value"] ?? "" }}</b>
                    <span class="text-sm text-neutral-600">{{ $stat["label"] ?? "" }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- TENTANG --}}
<section id="tentang" class="pb-[88px] pt-36">
    <div class="mx-auto max-w-[1180px] px-5 grid items-center gap-14 md:grid-cols-2">
        <div data-motion="left">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl mb-4">{{ Cms::t($about, "title", "Tentang perusahaan") }}</h2>
            <p class="max-w-[58ch] whitespace-pre-line text-base text-neutral-600 md:text-lg">{{ Cms::t($about, "body", "PT Meditech Cipta Inovasi bergerak di bidang jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, dan komputer.") }}</p>
            @if(Cms::t($about, "body2") !== "")
                <p class="mt-3.5 whitespace-pre-line text-neutral-600">{{ Cms::t($about, "body2") }}</p>
            @endif
            @if(Cms::t($about, "quote") !== "")
                <div class="mt-7 rounded-r-2xl border-l-4 border-secondary-500 bg-secondary-50 px-5 py-4 font-heading font-medium leading-normal text-primary-500">{{ Cms::t($about, "quote") }}</div>
            @endif
        </div>
        <img src="{{ Cms::t($about, "img", $logo) }}" alt="{{ Cms::t($about, "imgAlt", Cms::t($about, "title", "Tentang perusahaan")) }}" data-motion="right" class="aspect-[4/3] w-full rounded-[32px] object-cover">
    </div>
</section>

{{-- VISI & MISI --}}
<section id="visi" class="bg-primary-500 py-[88px] text-white">
    <div class="mx-auto max-w-[1180px] px-5 grid items-start gap-9 md:grid-cols-[.9fr_1.1fr] md:gap-14">
        <div data-motion="left" class="rounded-[28px] border-t-4 border-secondary-500 bg-white/10 p-7 md:p-9">
            <h2 class="font-heading text-3xl font-semibold text-white md:text-4xl lg:text-5xl">Visi</h2>
            <p class="mt-5 font-heading text-xl font-medium leading-snug md:text-3xl">"{{ Cms::t($vm, "visi", "Menjadi perusahaan yang memiliki integritas, mitra terpercaya, profesional, dan memberikan pelayanan purna jual terbaik.") }}"</p>
        </div>
        <div data-motion="right">
            <h2 class="font-heading text-3xl font-semibold text-white md:text-4xl lg:text-5xl">Misi</h2>
            <ol data-motion-stagger="right" class="mt-5">
                @forelse($misi as $m)
                <li class="grid grid-cols-[40px_1fr] items-start gap-4 border-t border-white/20 py-4 {{ $loop->last ? "border-b" : "" }}">
                    <b class="grid h-10 w-10 place-items-center rounded-full bg-secondary-500 font-heading font-semibold text-neutral-900">{{ $loop->iteration }}</b>
                    <p class="pt-1.5 text-primary-50">{{ $m["text"] ?? "" }}</p>
                </li>
                @empty
                <li class="border-t border-white/20 py-4 text-primary-50">Belum ada misi.</li>
                @endforelse
            </ol>
        </div>
    </div>
</section>

{{-- PRODUK & LAYANAN --}}
<section id="produk" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div data-motion class="mb-11 max-w-2xl">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ Cms::t($produk, "title", "Produk & layanan") }}</h2>
            <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3">{{ Cms::t($produk, "intro", "Instalasi jasa untuk fasilitas kesehatan dan perkantoran, ditambah perangkat keras dan layanan perawatan.") }}</p>
        </div>

        {{-- Instalasi jasa --}}
        <div data-motion-stagger class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($services as $service)
            <article data-lift class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white">
                <img src="{{ Cms::t($service, "img", $logo) }}" alt="{{ $service["title"] ?? "" }}" class="aspect-[16/10] w-full object-cover">
                <div class="p-5">
                    <span class="mb-2 inline-block rounded-full bg-secondary-500 px-2.5 py-0.5 font-heading text-xs font-semibold text-neutral-900">{{ Cms::t($produk, "serviceTag", "Instalasi jasa") }}</span>
                    <h3 class="font-heading text-xl font-semibold leading-snug text-primary-500">{{ $service["title"] ?? "" }}</h3>
                </div>
            </article>
            @empty
            <div class="col-span-full text-center text-neutral-500 text-sm py-8">Belum ada layanan.</div>
            @endforelse
        </div>

        {{-- Hardware & Maintenance --}}
        @if(count($hardware) || Cms::t($produk, "hwImg") !== "" || Cms::t($produk, "mtTitle") !== "" || Cms::t($produk, "mtDesc") !== "")
        <div data-motion-stagger class="mt-5 grid gap-5 lg:grid-cols-[1.6fr_1fr]">
            <div data-lift class="rounded-[20px] border border-neutral-200 bg-white p-6 md:p-8">
                <h3 class="font-heading text-2xl font-semibold leading-snug text-primary-500">{{ Cms::t($produk, "hwTitle", "Hardware") }}</h3>
                @if(Cms::t($produk, "hwImg") !== "")
                    <img src="{{ Cms::t($produk, "hwImg") }}" alt="{{ Cms::t($produk, "hwImgAlt", "Logo brand perangkat keras") }}" class="mt-5 max-h-40 w-full max-w-[560px] object-contain object-left">
                @endif
                <ul class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach($hardware as $hw)
                    <li class="border-l-4 border-secondary-500 pl-3.5">
                        <b class="block font-heading text-sm font-semibold text-primary-500">{{ $hw["title"] ?? "" }}</b>
                        <span class="text-sm text-neutral-600">{{ $hw["desc"] ?? "" }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @if(Cms::t($produk, "mtTitle") !== "" || Cms::t($produk, "mtDesc") !== "")
            <div data-lift class="rounded-[20px] bg-primary-500 p-6 text-white md:p-8">
                <h3 class="font-heading text-2xl font-semibold leading-snug text-white">{{ Cms::t($produk, "mtTitle", "Maintenance") }}</h3>
                <p class="mt-3 text-primary-50">{{ Cms::t($produk, "mtDesc") }}</p>
            </div>
            @endif
        </div>
        @endif
    </div>
</section>

{{-- PROYEK TERBARU --}}
<section id="proyek" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div data-motion class="mb-11 max-w-2xl">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ Cms::t($proyek, "title", "Proyek terbaru") }}</h2>
            <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3">{{ Cms::t($proyek, "intro", "Beberapa pekerjaan yang telah kami selesaikan.") }}</p>
        </div>
        <div data-motion-stagger class="grid gap-5 md:grid-cols-3">
            @forelse($projects as $project)
            <div data-lift class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white p-6">
                @if(($project["tag"] ?? "") !== "")
                <span class="mb-2 inline-block rounded-full bg-secondary-500 px-2.5 py-0.5 font-heading text-xs font-semibold text-neutral-900">{{ $project["tag"] }}</span>
                @endif
                <h3 class="font-heading text-xl font-semibold leading-snug text-primary-500">{{ $project["title"] ?? "" }}</h3>
                <small class="mt-2.5 block text-[13px] text-neutral-600">{{ $project["meta"] ?? "" }}</small>
            </div>
            @empty
            <div class="col-span-full text-center text-neutral-500 text-sm py-8">Belum ada proyek.</div>
            @endforelse
        </div>
    </div>
</section>

{{-- DIPERCAYA BERBAGAI SEKTOR --}}
<section id="pelanggan" class="bg-neutral-50 py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div data-motion class="mb-11 max-w-2xl mx-auto text-center">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ Cms::t($sektor, "title", "Dipercaya berbagai sektor") }}</h2>
            <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3 mx-auto">{{ Cms::t($sektor, "intro", "Kami membangun kemitraan yang erat dengan pelanggan dalam mencapai tujuan mereka, terutama di bidang teknologi.") }}</p>
        </div>
        <div data-motion-stagger="scale" class="flex flex-wrap justify-center gap-5">
            @foreach($sectors as $sector)
            @php
                $iconKey = $sector["icon"] ?? "";
                if (!isset($sectorIcons[$iconKey])) $iconKey = $guessIcon($sector["name"] ?? "");
            @endphp
            <div data-lift class="min-w-0 basis-[calc(50%-10px)] rounded-[20px] border border-neutral-200 bg-white px-3 py-5 text-center transition hover:border-secondary-500 hover:shadow-[0_12px_28px_-12px_rgba(13,33,161,.25)] sm:min-w-[200px] sm:basis-[calc(25%-15px)] sm:px-5 sm:py-7">
                <svg class="mx-auto mb-4 block h-[60px] w-[60px]" viewBox="0 0 44 44" fill="none" aria-hidden="true">
                    <rect width="44" height="44" rx="14" fill="#e7e9f6"/>
                    <g stroke="#0D21A1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $sectorIcons[$iconKey] }}"/>
                    </g>
                </svg>
                <h4 class="font-heading text-base font-semibold leading-snug text-primary-500">{{ $sector["name"] ?? "" }}</h4>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- PARTNER --}}
<section id="partner" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div data-motion class="mb-11 max-w-2xl mx-auto text-center">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ Cms::t($partnerP, "title", "Brand yang bekerja sama dengan kami") }}</h2>
            <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3 mx-auto">{{ Cms::t($partnerP, "intro", "Kami memasok perangkat dari brand ternama dan mitra resmi.") }}</p>
        </div>
        <ul data-motion-stagger="scale" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @forelse($partners as $partner)
            <li data-lift class="grid h-24 place-items-center rounded-2xl border border-neutral-200 bg-white p-4 transition hover:border-secondary-500">
                @if($partner->media)
                    <img src="{{ Cms::mediaUrl($partner->media->file_path) }}" alt="{{ $partner->partner_name }}" class="max-h-12 w-auto">
                @else
                    <span class="font-heading text-lg font-semibold text-primary-500">{{ $partner->partner_name }}</span>
                @endif
            </li>
            @empty
            <li class="col-span-full text-center text-neutral-500 text-sm py-8">Belum ada partner.</li>
            @endforelse
        </ul>
    </div>
</section>

{{-- TESTIMONI --}}
<section id="testimoni" class="bg-neutral-50 py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div data-motion class="mb-11 max-w-2xl mx-auto text-center">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ Cms::t($testi, "title", "Kata pelanggan kami") }}</h2>
        </div>
        <div data-motion-stagger class="grid gap-5 md:grid-cols-3">
            @forelse($testis as $t)
            <div data-lift class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white p-[26px]">
                <svg viewBox="0 0 80 14" width="80" height="14" fill="#EEC643" role="img" aria-label="5 bintang"><path transform="translate(0,0)" d="M7 0l2 5 5 .5-4 3.5 1.2 5L7 11 2.8 14 4 9 0 5.5 5 5z"/><path transform="translate(16,0)" d="M7 0l2 5 5 .5-4 3.5 1.2 5L7 11 2.8 14 4 9 0 5.5 5 5z"/><path transform="translate(32,0)" d="M7 0l2 5 5 .5-4 3.5 1.2 5L7 11 2.8 14 4 9 0 5.5 5 5z"/><path transform="translate(48,0)" d="M7 0l2 5 5 .5-4 3.5 1.2 5L7 11 2.8 14 4 9 0 5.5 5 5z"/><path transform="translate(64,0)" d="M7 0l2 5 5 .5-4 3.5 1.2 5L7 11 2.8 14 4 9 0 5.5 5 5z"/></svg>
                <p class="mb-4 mt-3 text-[15px] text-neutral-600">"{{ $t["quote"] ?? "" }}"</p>
                <b class="font-heading text-sm font-semibold text-primary-500">{{ $t["name"] ?? "" }}</b>
                <small class="block text-[13px] text-neutral-600">{{ $t["role"] ?? "" }}</small>
            </div>
            @empty
            <div class="col-span-full text-center text-neutral-500 text-sm py-8">Belum ada testimoni.</div>
            @endforelse
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-primary-500 py-[88px] text-center text-white">
    <div data-motion class="mx-auto max-w-[1180px] px-5">
        <h2 class="mb-3 font-heading text-3xl font-semibold text-white md:text-4xl lg:text-5xl">{{ Cms::t($home, "ctaTitle", "Butuh instalasi atau perangkat baru?") }}</h2>
        <p class="mx-auto mb-7 max-w-[58ch] text-base text-primary-100 md:text-lg">{{ Cms::t($home, "ctaSub", "Ceritakan kebutuhan Anda, tim kami siap menyiapkan penawaran.") }}</p>
        <a href="{{ Cms::link($home["ctaBtnLink"] ?? null, "#kontak") }}" class="inline-block rounded-xl bg-secondary-500 px-6 py-3 font-heading text-[15px] font-semibold text-neutral-900 transition hover:bg-secondary-300">{{ Cms::t($home, "ctaBtnLabel", "Hubungi kami") }}</a>
    </div>
</section>

{{-- KONTAK --}}
<section id="kontak" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5 grid items-start gap-14 md:grid-cols-2">
        <div data-motion="left">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ Cms::t($kontak, "title", "Hubungi kami") }}</h2>
            <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3">{{ Cms::t($kontak, "intro", "Kirim pesan atau datang langsung ke kantor kami.") }}</p>
            <div class="mt-7 grid gap-4 text-neutral-600">
                <div class="border-l-4 border-secondary-500 pl-3.5"><b class="block font-heading text-sm font-semibold text-primary-500">Alamat</b>{{ Cms::t($kontak, "address", "-") }}</div>
                <div class="border-l-4 border-secondary-500 pl-3.5"><b class="block font-heading text-sm font-semibold text-primary-500">Telepon</b><a href="tel:{{ preg_replace("/[^\d+]/", "", Cms::t($kontak, "phone")) }}" class="hover:text-primary-500">{{ Cms::t($kontak, "phone", "-") }}</a></div>
                <div class="border-l-4 border-secondary-500 pl-3.5"><b class="block font-heading text-sm font-semibold text-primary-500">Email</b><a href="mailto:{{ Cms::t($kontak, "email") }}" class="hover:text-primary-500">{{ Cms::t($kontak, "email", "-") }}</a></div>
                <div class="border-l-4 border-secondary-500 pl-3.5"><b class="block font-heading text-sm font-semibold text-primary-500">Jam operasional</b>{{ Cms::t($kontak, "hours", "-") }}</div>
            </div>
        </div>
        <form id="contact-form" data-motion="right" class="grid gap-3.5 rounded-3xl border border-neutral-200 bg-white p-7 shadow-[0_16px_40px_-16px_rgba(13,33,161,.2)]" action="#" method="POST">
            @csrf
            <label class="grid gap-1.5 text-sm font-medium">Nama<input name="name" required autocomplete="name" class="rounded-xl border border-neutral-200 bg-neutral-50 px-3.5 py-3 text-base text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-50"></label>
            <label class="grid gap-1.5 text-sm font-medium">Email<input type="email" name="email" required autocomplete="email" class="rounded-xl border border-neutral-200 bg-neutral-50 px-3.5 py-3 text-base text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-50"></label>
            <label class="grid gap-1.5 text-sm font-medium">Kebutuhan Anda<textarea name="message" rows="4" required class="rounded-xl border border-neutral-200 bg-neutral-50 px-3.5 py-3 text-base text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-50"></textarea></label>
            <button type="submit" class="rounded-xl bg-primary-500 px-6 py-3 font-heading text-[15px] font-semibold text-white transition hover:bg-primary-700">Kirim pesan</button>
            <div id="contact-ok" role="status" class="min-h-5 text-sm text-neutral-600"></div>
        </form>
    </div>
</section>
@endsection

@push("scripts")
    <script>
        document.querySelectorAll("a[href^=\"#\"]").forEach(a => {
            a.addEventListener("click", e => {
                const id = a.getAttribute("href");
                if (id.length > 1) {
                    const el = document.getElementById(id.slice(1));
                    if (el) { e.preventDefault(); el.scrollIntoView({ behavior: "smooth", block: "start" }); }
                }
            });
        });
    </script>

    {{-- Animasi: Motion (https://motion.dev). Dilewati otomatis bila pengguna memilih "reduce motion". --}}
    <script type="module">
        import { animate, inView, hover, stagger } from "https://cdn.jsdelivr.net/npm/motion@11.18.2/+esm";

        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            document.documentElement.classList.remove("js-motion");
        } else {
            window.__motionReady = true;

            const ease = [0.22, 1, 0.36, 1];
            const from = {
                up: { y: [28, 0] },
                left: { x: [-36, 0] },
                right: { x: [36, 0] },
                scale: { scale: [0.92, 1] },
            };
            const variant = name => from[name] || from.up;
            // Hapus transform sisa agar tidak membuat stacking context / bentrok dengan hover.
            const clean = els => [].concat(els).forEach(el => { el.style.transform = ""; });

            // Header turun saat halaman dimuat
            const header = document.querySelector("header");
            if (header) animate(header, { opacity: [0, 1], y: [-24, 0] }, { duration: 0.5, ease }).then(() => clean(header));

            // Elemen tunggal
            document.querySelectorAll("[data-motion]").forEach(el => {
                inView(el, () => {
                    animate(el, { opacity: [0, 1], ...variant(el.dataset.motion) },
                        { duration: 0.7, delay: parseFloat(el.dataset.delay || 0), ease }).then(() => clean(el));
                }, { margin: "0px 0px -12% 0px" });
            });

            // Grup: anak-anaknya muncul berurutan
            document.querySelectorAll("[data-motion-stagger]").forEach(group => {
                const kids = [...group.children];
                inView(group, () => {
                    animate(kids, { opacity: [0, 1], ...variant(group.dataset.motionStagger) },
                        { duration: 0.6, delay: stagger(0.08), ease }).then(() => clean(kids));
                }, { margin: "0px 0px -10% 0px" });
            });

            // Angka perusahaan menghitung naik, mis. "150+" → 0…150+
            document.querySelectorAll("[data-stats] b").forEach(el => {
                const m = el.textContent.trim().match(/^(\D*)([\d.,]+)(.*)$/);
                if (!m) return;
                const target = parseFloat(m[2].replace(/\./g, "").replace(",", "."));
                if (!isFinite(target)) return;
                const original = el.textContent;
                const fmt = n => m[1] + Math.round(n).toLocaleString("id-ID") + m[3];
                el.textContent = fmt(0);
                inView(el, () => {
                    animate(0, target, {
                        duration: 1.4, delay: 0.4, ease,
                        onUpdate: v => { el.textContent = fmt(v); },
                        onComplete: () => { el.textContent = original; }, // teks akhir selalu = nilai asli
                    });
                }, { margin: "0px 0px -10% 0px" });
            });

            // Kartu terangkat saat di-hover
            document.querySelectorAll("[data-lift]").forEach(card => {
                hover(card, () => {
                    animate(card, { y: -6 }, { duration: 0.25, ease });
                    return () => animate(card, { y: 0 }, { duration: 0.25, ease });
                });
            });
        }
    </script>
@endpush