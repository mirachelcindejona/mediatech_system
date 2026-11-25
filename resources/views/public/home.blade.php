@extends('layouts.public')

@php
    /*
    | Semua teks & gambar dibaca dari CMS ($cms). Teks setelah "??" / argumen ketiga $t()
    | hanya cadangan bila halaman CMS belum diisi (sama dengan isi company profile).
    | Section disembunyikan bila halaman CMS-nya berstatus Draft ($show).
    */
    $t     = static fn (array $src, string $key, string $default = '') => filled($src[$key] ?? null) ? $src[$key] : $default;
    $list  = static fn (array $src, string $key) => is_array($src[$key] ?? null) ? $src[$key] : [];
    $show  = static fn (string $key) => $cms['_visible'][$key] ?? true;
    $link  = static fn ($value, string $default) => preg_match('/^#[a-z0-9\-]+$/', (string) $value) ? $value : $default;

    $home = $cms['home'] ?? [];
    $about = $cms['about'] ?? [];
    $vm = $cms['vm'] ?? [];
    $products = $cms['products'] ?? [];
    $projects = $cms['projects'] ?? [];
    $customers = $cms['customers'] ?? [];
    $partnerPage = $cms['partners'] ?? [];
    $testi = $cms['testi'] ?? [];
    $contact = $cms['contact'] ?? [];

    $stats = $list($home, 'stats');

    $phone = $t($contact, 'phone', '021 - 8908 - 8898');
    $phoneDigits = preg_replace('/\D+/', '', $phone);
    $telHref = $phoneDigits === '' ? null : (str_starts_with($phoneDigits, '0') ? '+62' . substr($phoneDigits, 1) : '+' . $phoneDigits);
    $email = $t($contact, 'email', 'info@meditech.co.id');
    $contactErrors = $errors->getBag('contact');
@endphp

@section('title', $t($home, 'seoTitle', 'PT Meditech Cipta Inovasi'))
@section('meta_description', $t($home, 'seoDesc', 'Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, dan komputer.'))

@section('content')

{{-- HERO + ANGKA PERUSAHAAN --}}
@if($show('home'))
<div class="pt-16">
    <div class="mx-auto max-w-[1180px] px-5 grid items-center gap-12 md:grid-cols-[1.05fr_.95fr]">
        <div>
            <h1 class="font-heading text-4xl font-semibold leading-[1.18] text-primary-500 md:text-5xl lg:text-6xl">{{ $t($home, 'heroTitle', 'Mitra instalasi dan perlengkapan teknologi Anda') }}</h1>
            <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mb-8 mt-5">{{ $t($home, 'heroSub', 'Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, dan komputer.') }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $link($home['ctaLink'] ?? null, '#kontak') }}" class="inline-block rounded-xl bg-secondary-500 px-6 py-3 font-heading text-[15px] font-semibold text-neutral-900 transition hover:bg-secondary-300">{{ $t($home, 'ctaLabel', 'Minta penawaran') }}</a>
                <a href="{{ $link($home['cta2Link'] ?? null, '#produk') }}" class="inline-block rounded-xl bg-white px-6 py-3 font-heading text-[15px] font-semibold text-primary-500 ring-2 ring-inset ring-primary-500 transition hover:bg-primary-50">{{ $t($home, 'cta2Label', 'Lihat produk') }}</a>
            </div>
        </div>
        <img src="{{ $t($home, 'heroImg', asset('images/meditech-logo.jpg')) }}" alt="{{ $t($home, 'heroImgAlt', 'Gedung perkantoran modern') }}" class="aspect-square w-full rounded-[32px] object-cover">
    </div>

    @if(count($stats))
    <div class="mx-auto max-w-[1180px] px-5">
        <div class="relative z-[2] -mb-14 mt-14 grid grid-cols-2 gap-y-5 rounded-3xl border border-neutral-200 bg-white px-2 py-7 shadow-[0_16px_40px_-16px_rgba(13,33,161,.25)] md:grid-cols-4">
            @foreach($stats as $stat)
                <div class="border-neutral-200 px-3 text-center md:border-r {{ $loop->odd ? 'border-r' : '' }} {{ $loop->last ? 'md:border-r-0' : '' }}">
                    <b class="block font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl">{{ $stat['value'] ?? '' }}</b>
                    <span class="text-sm text-neutral-600">{{ $stat['label'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endif

{{-- TENTANG --}}
@if($show('about'))
<section id="tentang" class="pb-[88px] {{ count($stats) ? 'pt-36' : 'pt-[88px]' }}">
    <div class="mx-auto max-w-[1180px] px-5 grid items-center gap-14 md:grid-cols-2">
        <div>
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl mb-4">{{ $t($about, 'title', 'Tentang perusahaan') }}</h2>
            @if(filled($about['body'] ?? null))
                <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg">{{ $about['body'] }}</p>
            @endif
            @if(filled($about['body2'] ?? null))
                <p class="mt-3.5 text-neutral-600">{{ $about['body2'] }}</p>
            @endif
            @if(filled($about['quote'] ?? null))
                <div class="mt-7 rounded-r-2xl border-l-4 border-secondary-500 bg-secondary-50 px-5 py-4 font-heading font-medium leading-normal text-primary-500">{{ $about['quote'] }}</div>
            @endif
        </div>
        @if(filled($about['img'] ?? null))
            <img src="{{ $about['img'] }}" alt="{{ $t($about, 'imgAlt', 'Foto perusahaan') }}" class="aspect-[4/3] w-full rounded-[32px] object-cover">
        @endif
    </div>
</section>
@endif

{{-- VISI & MISI --}}
@if($show('vm'))
<section id="visi" class="bg-primary-500 py-[88px] text-white">
    <div class="mx-auto max-w-[1180px] px-5 grid items-start gap-9 md:grid-cols-[.9fr_1.1fr] md:gap-14">
        <div class="rounded-[28px] border-t-4 border-secondary-500 bg-white/10 p-7 md:p-9">
            <h2 class="font-heading text-3xl font-semibold text-white md:text-4xl lg:text-5xl">Visi</h2>
            <p class="mt-5 font-heading text-xl font-medium leading-snug md:text-3xl">“{{ $t($vm, 'visi') }}”</p>
        </div>
        <div>
            <h2 class="font-heading text-3xl font-semibold text-white md:text-4xl lg:text-5xl">Misi</h2>
            <ol class="mt-5">
                @foreach($list($vm, 'misi') as $misi)
                    <li class="grid grid-cols-[40px_1fr] items-start gap-4 border-t border-white/20 py-4 {{ $loop->last ? 'border-b' : '' }}">
                        <b class="grid h-10 w-10 place-items-center rounded-full bg-secondary-500 font-heading font-semibold text-neutral-900">{{ $loop->iteration }}</b>
                        <p class="pt-1.5 text-primary-50">{{ $misi['text'] ?? '' }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
@endif

{{-- PRODUK & LAYANAN --}}
@if($show('products'))
@php
    $services = $list($products, 'services');
    $hardware = $list($products, 'hardware');
@endphp
<section id="produk" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div class="mb-11 max-w-2xl">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ $t($products, 'title', 'Produk & layanan') }}</h2>
            @if(filled($products['intro'] ?? null))
                <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3">{{ $products['intro'] }}</p>
            @endif
        </div>

        @if(count($services))
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($services as $service)
                <article class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white">
                    @if(filled($service['img'] ?? null))
                        <img src="{{ $service['img'] }}" alt="{{ $service['title'] ?? '' }}" class="aspect-[16/10] w-full object-cover">
                    @endif
                    <div class="p-5">
                        <span class="mb-2 inline-block rounded-full bg-secondary-500 px-2.5 py-0.5 font-heading text-xs font-semibold text-neutral-900">{{ $t($products, 'serviceTag', 'Instalasi jasa') }}</span>
                        <h3 class="font-heading text-xl font-semibold leading-snug text-primary-500">{{ $service['title'] ?? '' }}</h3>
                    </div>
                </article>
            @endforeach
        </div>
        @endif

        <div class="mt-5 grid gap-5 md:grid-cols-[1.2fr_.8fr]">
            <div class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white p-7">
                @if(filled($products['hwImg'] ?? null))
                    <img src="{{ $products['hwImg'] }}" alt="{{ $t($products, 'hwImgAlt', 'Logo brand perangkat keras') }}" class="mb-5 w-full max-w-[560px] rounded-xl">
                @endif
                <h3 class="mb-4 font-heading text-xl font-semibold text-primary-500">{{ $t($products, 'hwTitle', 'Hardware') }}</h3>
                <dl class="grid gap-3">
                    @foreach($hardware as $item)
                        <div>
                            <dt class="font-heading text-sm font-semibold text-primary-500">{{ $item['title'] ?? '' }}</dt>
                            <dd class="text-[15px] text-neutral-600">{{ $item['desc'] ?? '' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="rounded-[20px] border border-primary-50 bg-primary-50 p-7">
                <svg class="mb-3 h-12 w-12" viewBox="0 0 48 48" fill="none" stroke="#0D21A1" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M30 8a9 9 0 0 0-7 12L9 34l5 5 14-14a9 9 0 0 0 12-7l-6 5-4-1-1-4z"/></svg>
                <h3 class="font-heading text-2xl font-semibold text-primary-500">{{ $t($products, 'mtTitle', 'Maintenance') }}</h3>
                <p class="mt-1.5 text-[15px] text-neutral-600">{{ $t($products, 'mtDesc', 'Layanan perawatan berkala agar perangkat dan instalasi tetap berfungsi optimal.') }}</p>
            </div>
        </div>
    </div>
</section>
@endif

{{-- PROYEK TERBARU --}}
@if($show('projects') && count($list($projects, 'items')))
<section id="proyek" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div class="mb-11 max-w-2xl">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ $t($projects, 'title', 'Proyek terbaru') }}</h2>
            @if(filled($projects['intro'] ?? null))
                <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3">{{ $projects['intro'] }}</p>
            @endif
        </div>
        <div class="grid gap-5 md:grid-cols-3">
            @foreach($list($projects, 'items') as $project)
                <div class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white p-6">
                    @if(filled($project['tag'] ?? null))
                        <span class="mb-2 inline-block rounded-full bg-secondary-500 px-2.5 py-0.5 font-heading text-xs font-semibold text-neutral-900">{{ $project['tag'] }}</span>
                    @endif
                    <h3 class="font-heading text-xl font-semibold leading-snug text-primary-500">{{ $project['title'] ?? '' }}</h3>
                    @if(filled($project['meta'] ?? null))
                        <small class="mt-2.5 block text-[13px] text-neutral-600">{{ $project['meta'] }}</small>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- SEKTOR PELANGGAN --}}
@if($show('customers') && count($list($customers, 'sectors')))
<section id="pelanggan" class="bg-neutral-50 py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div class="mb-11 max-w-2xl mx-auto text-center">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ $t($customers, 'title', 'Dipercaya berbagai sektor') }}</h2>
            @if(filled($customers['intro'] ?? null))
                <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3 mx-auto">{{ $customers['intro'] }}</p>
            @endif
        </div>
        <div class="flex flex-wrap justify-center gap-5">
            @foreach($list($customers, 'sectors') as $sector)
                <div class="min-w-0 basis-[calc(50%-10px)] rounded-[20px] border border-neutral-200 bg-white px-3 py-5 text-center transition hover:border-secondary-500 hover:shadow-[0_12px_28px_-12px_rgba(13,33,161,.25)] sm:min-w-[200px] sm:basis-[calc(25%-15px)] sm:px-5 sm:py-7">
                    @include('public.partials.sector-icon', ['icon' => $sector['icon'] ?? 'general'])
                    <h4 class="font-heading text-base font-semibold leading-snug text-primary-500">{{ $sector['name'] ?? '' }}</h4>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- PARTNER & BRAND --}}
@if($show('partners') && $partners->isNotEmpty())
<section id="partner" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div class="mb-11 max-w-2xl mx-auto text-center">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ $t($partnerPage, 'title', 'Brand yang bekerja sama dengan kami') }}</h2>
            @if(filled($partnerPage['intro'] ?? null))
                <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3 mx-auto">{{ $partnerPage['intro'] }}</p>
            @endif
        </div>
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach($partners as $partner)
                <li class="grid h-24 place-items-center rounded-2xl border border-neutral-200 bg-white p-4 transition hover:border-secondary-500">
                    @if($partner['logo'])
                        <img src="{{ $partner['logo'] }}" alt="{{ $partner['name'] }}" class="max-h-12 w-auto">
                    @else
                        <span class="font-heading font-semibold text-primary-500">{{ $partner['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- TESTIMONI --}}
@if($show('testi') && count($list($testi, 'items')))
<section id="testimoni" class="bg-neutral-50 py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5">
        <div class="mb-11 max-w-2xl mx-auto text-center">
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ $t($testi, 'title', 'Kata pelanggan kami') }}</h2>
        </div>
        <div class="grid gap-5 md:grid-cols-3">
            @foreach($list($testi, 'items') as $item)
                <div class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white p-[26px]">
                    <svg viewBox="0 0 80 14" width="80" height="14" fill="#EEC643" role="img" aria-label="5 bintang">
                        @foreach([0, 16, 32, 48, 64] as $x)
                            <path transform="translate({{ $x }},0)" d="M7 0l2 5 5 .5-4 3.5 1.2 5L7 11 2.8 14 4 9 0 5.5 5 5z"/>
                        @endforeach
                    </svg>
                    <p class="mb-4 mt-3 text-[15px] text-neutral-600">“{{ $item['quote'] ?? '' }}”</p>
                    <b class="font-heading text-sm font-semibold text-primary-500">{{ $item['name'] ?? '' }}</b>
                    <small class="block text-[13px] text-neutral-600">{{ $item['role'] ?? '' }}</small>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- BANNER AJAKAN (dikelola di halaman Beranda) --}}
@if($show('home'))
<section class="bg-primary-500 py-[88px] text-center text-white">
    <div class="mx-auto max-w-[1180px] px-5">
        <h2 class="mb-3 font-heading text-3xl font-semibold text-white md:text-4xl lg:text-5xl">{{ $t($home, 'ctaTitle', 'Butuh instalasi atau perangkat baru?') }}</h2>
        <p class="mx-auto mb-7 max-w-[58ch] text-base text-primary-100 md:text-lg">{{ $t($home, 'ctaSub', 'Ceritakan kebutuhan Anda, tim kami siap menyiapkan penawaran.') }}</p>
        <a href="{{ $link($home['ctaBtnLink'] ?? null, '#kontak') }}" class="inline-block rounded-xl bg-secondary-500 px-6 py-3 font-heading text-[15px] font-semibold text-neutral-900 transition hover:bg-secondary-300">{{ $t($home, 'ctaBtnLabel', 'Hubungi kami') }}</a>
    </div>
</section>
@endif

{{-- KONTAK --}}
@if($show('contact'))
<section id="kontak" class="py-[88px]">
    <div class="mx-auto max-w-[1180px] px-5 grid items-start gap-14 md:grid-cols-2">
        <div>
            <h2 class="font-heading text-3xl font-semibold leading-tight text-primary-500 md:text-4xl lg:text-5xl">{{ $t($contact, 'title', 'Hubungi kami') }}</h2>
            <p class="max-w-[58ch] text-base text-neutral-600 md:text-lg mt-3">{{ $t($contact, 'intro', 'Kirim pesan atau datang langsung ke kantor kami.') }}</p>
            <div class="mt-7 grid gap-4 text-neutral-600">
                <div class="border-l-4 border-secondary-500 pl-3.5">
                    <b class="block font-heading text-sm font-semibold text-primary-500">Alamat</b>
                    {{ $t($contact, 'address', 'Jl. Neo Paloma II Blok S10 No 26 Cluster Garden Ville, Desa Ciakar, Kec. Panongan, Kab. Tangerang, Banten.') }}
                </div>
                <div class="border-l-4 border-secondary-500 pl-3.5">
                    <b class="block font-heading text-sm font-semibold text-primary-500">Telepon</b>
                    @if($telHref)
                        <a href="tel:{{ $telHref }}">{{ $phone }}</a>
                    @else
                        {{ $phone }}
                    @endif
                </div>
                <div class="border-l-4 border-secondary-500 pl-3.5">
                    <b class="block font-heading text-sm font-semibold text-primary-500">Email</b>
                    <a href="mailto:{{ $email }}">{{ $email }}</a>
                </div>
                <div class="border-l-4 border-secondary-500 pl-3.5">
                    <b class="block font-heading text-sm font-semibold text-primary-500">Jam operasional</b>
                    {{ $t($contact, 'hours', 'Senin–Jumat, 08.00–17.00 WIB') }}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('contact.send') }}" class="grid gap-3.5 rounded-3xl border border-neutral-200 bg-white p-7 shadow-[0_16px_40px_-16px_rgba(13,33,161,.2)]">
            @csrf
            {{-- Honeypot anti-spam: dibiarkan kosong oleh pengunjung --}}
            <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <label class="grid gap-1.5 text-sm font-medium">Nama
                <input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" class="rounded-xl border border-neutral-200 bg-neutral-50 px-3.5 py-3 text-base text-neutral-900">
                @if($contactErrors->has('name'))<span class="text-xs font-normal text-red-600">{{ $contactErrors->first('name') }}</span>@endif
            </label>
            <label class="grid gap-1.5 text-sm font-medium">Email
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" class="rounded-xl border border-neutral-200 bg-neutral-50 px-3.5 py-3 text-base text-neutral-900">
                @if($contactErrors->has('email'))<span class="text-xs font-normal text-red-600">{{ $contactErrors->first('email') }}</span>@endif
            </label>
            <label class="grid gap-1.5 text-sm font-medium">Kebutuhan Anda
                <textarea name="message" rows="4" required maxlength="2000" class="rounded-xl border border-neutral-200 bg-neutral-50 px-3.5 py-3 text-base text-neutral-900">{{ old('message') }}</textarea>
                @if($contactErrors->has('message'))<span class="text-xs font-normal text-red-600">{{ $contactErrors->first('message') }}</span>@endif
            </label>
            <button type="submit" class="rounded-xl bg-primary-500 px-6 py-3 font-heading text-[15px] font-semibold text-white transition hover:bg-primary-700">Kirim pesan</button>

            <div role="status" class="min-h-5 text-sm {{ session('contact_status') ? 'text-emerald-700' : 'text-red-600' }}">
                {{ session('contact_status') ?? $contactErrors->first('send') }}
            </div>
        </form>
    </div>
</section>
@endif

@endsection