@extends('admin.layouts.layout')

@section('title', 'CMS Content')

@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Storage;

    /*
    |--------------------------------------------------------------------------
    | Normalisasi data CMS dari CmsController yang saat ini digunakan.
    | Controller mengirim $pageData dengan key berupa slug database.
    |--------------------------------------------------------------------------
    */
    $cmsPageDefinitions = [
        ['k' => 'home', 'slug' => 'home', 'label' => 'Beranda', 'icon' => 'globe', 'anchor' => '#top', 'desc' => 'Hero, angka perusahaan, banner ajakan, dan judul tab browser.'],
        ['k' => 'about', 'slug' => 'about-us', 'label' => 'Tentang perusahaan', 'icon' => 'building', 'anchor' => '#tentang', 'desc' => 'Profil perusahaan, kutipan, dan foto.'],
        ['k' => 'vm', 'slug' => 'visi-misi', 'label' => 'Visi & misi', 'icon' => 'eye', 'anchor' => '#visi', 'desc' => 'Pernyataan visi dan daftar misi bernomor.'],
        ['k' => 'products', 'slug' => 'produk-layanan', 'label' => 'Produk & layanan', 'icon' => 'inventory', 'anchor' => '#produk', 'desc' => 'Kartu instalasi jasa, blok hardware, dan maintenance.'],
        ['k' => 'projects', 'slug' => 'proyek-terbaru', 'label' => 'Proyek terbaru', 'icon' => 'order', 'anchor' => '#proyek', 'desc' => 'Kartu proyek yang telah diselesaikan.'],
        ['k' => 'customers', 'slug' => 'sektor-pelanggan', 'label' => 'Sektor pelanggan', 'icon' => 'users', 'anchor' => '#pelanggan', 'desc' => 'Sektor industri yang menjadi pelanggan, lengkap dengan ikon.'],
        ['k' => 'partners', 'slug' => 'partner-brand', 'label' => 'Partner & brand', 'icon' => 'handshake', 'anchor' => '#partner', 'desc' => 'Judul bagian dan logo brand pemasok.'],
        ['k' => 'testi', 'slug' => 'testimoni', 'label' => 'Testimoni', 'icon' => 'user', 'anchor' => '#testimoni', 'desc' => 'Kutipan dari pelanggan.'],
        ['k' => 'contact', 'slug' => 'kontak', 'label' => 'Kontak & footer', 'icon' => 'mail', 'anchor' => '#kontak', 'desc' => 'Alamat, telepon, email, jam operasional, dan footer.'],
        ['k' => 'media', 'slug' => null, 'label' => 'Pustaka media', 'icon' => 'image', 'anchor' => '', 'desc' => ''],
    ];

    $cmsSiteUrl = rtrim(Route::has('home') ? route('home') : url('/'), '/');
    $cmsUpdateUrl = Route::has('cms.pages.update')
        ? route('cms.pages.update', ['slug' => '__SLUG__'], false)
        : null;

    $mediaRows = DB::table('cms_media')->whereNull('deleted_at')->orderByDesc('uploaded_at')->get();
    $mediaById = $mediaRows->keyBy('media_id');
    $mediaUrl = static function ($mediaId) use ($mediaById) {
        if (!$mediaId || !$mediaById->has($mediaId)) return '';
        $path = $mediaById->get($mediaId)->file_path ?? '';
        if (!$path) return '';
        return str_starts_with($path, 'data:') || str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    };

    $cmsNormalized = [];
    $slugToKey = [
        'home' => 'home', 'about-us' => 'about', 'visi-misi' => 'vm',
        'produk-layanan' => 'products', 'proyek-terbaru' => 'projects',
        'sektor-pelanggan' => 'customers', 'partner-brand' => 'partners',
        'testimoni' => 'testi', 'kontak' => 'contact',
    ];

    foreach (($pageData ?? []) as $slug => $record) {
        $key = $slugToKey[$slug] ?? null;
        if (!$key) continue;

        $content = [];
        foreach (($record['fields'] ?? []) as $fieldKey => $field) {
            $value = $field->field_value ?? '';
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
            }
            if (!empty($field->media_id)) {
                $value = $mediaUrl($field->media_id);
            } elseif (is_array($value)) {
                $walkMedia = function (&$node, $nodeKey = null) use (&$walkMedia, $mediaUrl) {
                    if (is_array($node)) {
                        foreach ($node as $childKey => &$child) $walkMedia($child, $childKey);
                        unset($child);
                    } elseif ($nodeKey === 'img' && is_numeric($node)) {
                        $node = $mediaUrl((int) $node);
                    }
                };
                $walkMedia($value);
            }
            $content[$fieldKey] = $value;
        }

        // Fallback untuk data list yang tersimpan pada cms_items.
        foreach (($record['items'] ?? []) as $section => $rows) {
            if (array_key_exists($section, $content) && is_array($content[$section]) && count($content[$section])) continue;
            $content[$section] = collect($rows)->map(function ($item) use ($mediaUrl) {
                return [
                    'tag' => $item->subtitle ?? '',
                    'title' => $item->title ?? '',
                    'meta' => $item->meta ?? '',
                    'name' => $item->title ?? '',
                    'role' => $item->subtitle ?? '',
                    'quote' => $item->description ?? '',
                    'text' => $item->title ?? '',
                    'desc' => $item->description ?? '',
                    'value' => $item->item_value ?? '',
                    'label' => $item->subtitle ?? '',
                    'icon' => $item->meta ?? '',
                    'img' => !empty($item->media_id) ? $mediaUrl($item->media_id) : '',
                ];
            })->values()->all();
        }

        $cmsNormalized[$key] = [
            'content' => $content,
            'status' => $record['status'] ?? 'Draft',
            'updated_at' => $record['updated_at'] ?? null,
            'updated_by' => $record['updated_by'] ?? 'System',
            'page_id' => $record['page']->page_id ?? null,
        ];
    }

    $cmsPartners = DB::table('cms_partners as p')
        ->leftJoin('cms_media as m', 'm.media_id', '=', 'p.media_id')
        ->whereNull('p.deleted_at')
        ->select('p.partner_id', 'p.partner_name', 'p.website_url', 'p.display_order', 'p.is_active', 'p.category_id', 'm.file_path', 'm.file_name')
        ->orderBy('p.display_order')->get()
        ->map(function ($p) {
            $p->logo_url = empty($p->file_path) ? '' : (str_starts_with($p->file_path, 'data:') || str_starts_with($p->file_path, 'http') ? $p->file_path : Storage::disk('public')->url($p->file_path));
            return $p;
        });

    $cmsMedia = $mediaRows->map(function ($m) {
        $m->url = empty($m->file_path) ? '' : (str_starts_with($m->file_path, 'data:') || str_starts_with($m->file_path, 'http') ? $m->file_path : Storage::disk('public')->url($m->file_path));
        return $m;
    });
@endphp

@push('styles')
<style>
/* ==========================================================================
   Meditech design system (Default.tokens.json + Moodboard + Molecules)
   Heading: Montserrat 600 · Body: Inter 400 · Semantic: tingkat 500
   ========================================================================== */
.cms-content {
    --primary-50:#e7e9f6; --primary-100:#b4bae2; --primary-200:#9099d4; --primary-300:#5d6ac0; --primary-400:#3d4db4;
    --primary-500:#0d21a1; --primary-600:#0c1e93; --primary-700:#091772; --primary-800:#071259; --primary-900:#050e44;
    --secondary-50:#fdf9ec; --secondary-100:#faedc5; --secondary-500:#eec643; --secondary-600:#d9b43d; --secondary-700:#a98d30; --secondary-800:#836d25;
    --neutral-50:#fafafa; --neutral-100:#f5f5f5; --neutral-200:#e5e5e5; --neutral-300:#d4d4d4; --neutral-400:#a1a1a1;
    --neutral-500:#737373; --neutral-600:#525252; --neutral-700:#404040; --neutral-800:#262626; --neutral-900:#171717;
    --success-50:#f0fdf4; --success-500:#00c951; --success-700:#008236;
    --warning-50:#fefce8; --warning-500:#efb100; --warning-700:#a65f00;
    --error-50:#fef2f2; --error-500:#fb2c36; --error-700:#c10007;
    --r-sm:4px; --r-md:6px; --r-lg:8px; --r-xl:12px; --r-2xl:16px; --r-full:9999px;
    --shadow-xs:0 1px 2px 0 rgb(0 0 0 / .05);
    --shadow-sm:0 1px 3px 0 rgb(0 0 0 / .1), 0 1px 2px -1px rgb(0 0 0 / .1);
    --shadow-lg:0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1);
    --font-heading:'Montserrat',ui-sans-serif,system-ui,sans-serif;
    --font-body:'Inter',ui-sans-serif,system-ui,sans-serif;
    font-family:var(--font-body); font-weight:400; font-size:14px; line-height:20px; color:var(--neutral-800);
}
.cms-content *,.cms-content *::before,.cms-content *::after { box-sizing:border-box; }
.cms-content button,.cms-content input,.cms-content select,.cms-content textarea { font-family:var(--font-body); }

/* Typography — Headline (Montserrat Semi Bold) & Body (Inter Regular) */
.cms-title { font-family:var(--font-heading); color:var(--neutral-900); font-size:20px; line-height:28px; font-weight:600; }            /* Headline 3 Mobile */
.panel-title,.field-section b { font-family:var(--font-heading); font-size:16px; line-height:21px; font-weight:600; color:var(--neutral-900); } /* Headline 4 Mobile */
.field-section b { color:var(--primary-500); }
.cms-eyebrow { font-family:var(--font-body); color:var(--primary-500); font-size:12px; line-height:16px; font-weight:600; letter-spacing:.8px; text-transform:uppercase; margin-bottom:6px; }
.cms-description { color:var(--neutral-500); font-size:14px; line-height:20px; margin-top:8px; max-width:850px; }   /* Body 3 Desktop */
@media(min-width:1024px){ .cms-title { font-size:31px; line-height:44px; } }                                        /* Headline 4 Desktop */

.cms-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
.cms-actions { display:flex; gap:8px; flex-wrap:wrap; }
.cms-layout { display:block; width:100%; }

/* Dropdown halaman */
.cms-dd { position:relative; width:100%; max-width:320px; margin-bottom:16px; }
.cms-dd-label { font-size:12px; line-height:16px; font-weight:500; color:var(--neutral-600); margin-bottom:6px; }
.cms-dd-btn { width:100%; display:flex; align-items:center; gap:10px; background:var(--neutral-50); border:1px solid var(--neutral-400); border-radius:var(--r-xl); padding:10px 14px; font-size:14px; line-height:20px; font-weight:500; color:var(--neutral-900); cursor:pointer; text-align:left; }
.cms-dd-btn:hover { border-color:var(--primary-300); }
.cms-dd-btn[aria-expanded=true],.cms-dd-btn:focus-visible { border-color:var(--primary-500); box-shadow:0 0 0 3px var(--primary-50); outline:none; }
.cms-dd-cur { display:flex; align-items:center; gap:10px; flex:1; min-width:0; }
.cms-dd-menu { display:none; position:absolute; left:0; right:0; top:calc(100% + 6px); z-index:30; max-height:420px; overflow:auto; box-shadow:var(--shadow-lg); }
.cms-dd.open .cms-dd-menu { display:block; }
.cms-tabs { display:block; padding:8px; }
.cms-tab { display:flex; align-items:center; gap:10px; width:100%; text-align:left; border:0; background:transparent; border-radius:var(--r-lg); padding:10px 12px; color:var(--neutral-600); font-size:14px; line-height:20px; margin:2px 0; cursor:pointer; }
.cms-tab:hover { background:var(--neutral-100); }
.cms-tab.active { background:var(--primary-50); color:var(--primary-500); font-weight:600; }
.cms-tab .ic { width:18px; height:18px; }
.sdot { margin-left:auto; width:8px; height:8px; border-radius:var(--r-full); flex:none; background:var(--warning-500); }
.sdot.published { background:var(--success-500); }

/* Panel / card */
.panel { background:#fff; border:1px solid var(--neutral-200); border-radius:var(--r-2xl); box-shadow:var(--shadow-xs); overflow:hidden; }
.panel-head { display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--neutral-200); gap:12px; }
.panel-sub { font-size:12px; line-height:16px; color:var(--neutral-500); margin-top:4px; }
.panel-body { padding:24px; }
.panel-foot { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:16px 24px; border-top:1px solid var(--neutral-200); background:var(--neutral-50); flex-wrap:wrap; }
.panel-foot-actions { display:flex; gap:8px; }
.panel-head-r { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

/* Form — label, input, select, textarea (Molecules: radius 12, bg neutral-50, border neutral-400) */
.field { min-width:0; margin-bottom:16px; }
.field label,.field-label { display:block; font-size:14px; line-height:20px; font-weight:500; color:var(--neutral-800); margin-bottom:6px; }
.field input:not([type=file]),.field select,.field textarea { border:1px solid var(--neutral-400); border-radius:var(--r-xl); padding:10px 14px; background:var(--neutral-50); color:var(--neutral-900); outline:none; font-size:14px; line-height:20px; width:100%; transition:border-color .15s, box-shadow .15s; }
.field select { appearance:none; -webkit-appearance:none; padding-right:40px; cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23171717' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; background-size:18px; }
.field input:hover,.field select:hover,.field textarea:hover { border-color:var(--neutral-500); }
.field input:focus,.field select:focus,.field textarea:focus { border-color:var(--primary-500); box-shadow:0 0 0 3px var(--primary-50); background:#fff; }
.field input::placeholder,.field textarea::placeholder { color:var(--neutral-400); }
.field textarea { min-height:88px; resize:vertical; }
.field.full { grid-column:1/-1; }
.field-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; }
.rq { color:var(--error-500); font-style:normal; }
.hint { font-size:12px; line-height:16px; color:var(--neutral-500); margin-top:6px; }
.muted { color:var(--neutral-500); font-size:12px; line-height:16px; }
.check { display:flex; gap:8px; align-items:center; font-size:14px; color:var(--neutral-700); }
.field-section { grid-column:1/-1; margin:20px 0 12px; padding-top:20px; border-top:1px solid var(--neutral-200); }
.field-section:first-child { margin-top:0; padding-top:0; border-top:0; }
.field-section span { display:block; font-size:12px; line-height:16px; color:var(--neutral-500); margin-top:4px; }
.lfields .field-section { margin:0; }

/* Dropzone / upload */
.dropzone { border:1.5px dashed var(--primary-100); border-radius:var(--r-xl); background:var(--primary-50); display:flex; align-items:center; justify-content:center; text-align:center; height:180px; min-height:0; cursor:pointer; color:var(--neutral-500); position:relative; overflow:hidden; margin:0; transition:border-color .15s, background .15s; }
.dropzone:hover,.dropzone:focus-visible { border-color:var(--primary-500); background:#f3f4fb; outline:none; }
.dropzone img { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; padding:12px; z-index:1; }
.dropzone.fit-cover img { object-fit:cover; padding:0; }
.dropzone.fit-contain img { object-fit:contain; padding:12px; background:#fff; }
.dz-in { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; width:100%; padding:14px; position:relative; z-index:2; pointer-events:none; }
.dz-in b { color:var(--neutral-800); font-size:14px; line-height:20px; font-weight:600; }
.dz-in small { color:var(--neutral-500); font-size:12px; line-height:16px; }
.dz-upload-btn { margin-top:4px; pointer-events:none; }
.dropzone.has .dz-in { opacity:0; position:absolute; inset:0; background:rgba(5,14,68,.62); color:#fff; transition:opacity .15s; }
.dropzone.has .dz-in b { color:#fff; }
.dropzone.has:hover .dz-in { opacity:1; }
.dropzone.compact .dz-in { gap:4px; padding:6px; }
.dropzone.compact .dz-in b { font-size:12px; }
.dropzone.compact .ic { width:16px; height:16px; }

/* List editor */
.lblock { margin-top:8px; }
.litem { display:flex; gap:16px; align-items:flex-start; padding:16px 0; border-top:1px solid var(--neutral-200); }
.lthumb { width:145px; flex:0 0 145px; }
.lthumb .dropzone { min-height:105px; }
.lfields { flex:1; min-width:0; }
.lfields.two { display:grid; grid-template-columns:1fr 1fr; gap:0 12px; }
.lfields .field { margin-bottom:10px; }
.lctl { display:flex; flex-direction:column; gap:2px; }
.list-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:8px; }
.list-head label { margin:0; }

/* Button — Molecules: Primary (blue 500, hover 700), Secondary/alternative, Danger, radius 12, Montserrat label */
.btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:var(--r-xl); padding:10px 16px; font-family:var(--font-heading); font-size:14px; line-height:20px; font-weight:600; border:1px solid transparent; transition:background .15s, border-color .15s, color .15s; white-space:nowrap; cursor:pointer; text-decoration:none; }
.btn:focus-visible { outline:2px solid var(--primary-300); outline-offset:2px; }
.btn-primary { background:var(--primary-500); color:#fff; }
.btn-primary:hover { background:var(--primary-700); }
.btn-primary:active { background:var(--primary-900); }
.btn-secondary { background:#fff; border-color:var(--primary-500); color:var(--primary-500); }
.btn-secondary:hover { background:var(--primary-50); }
.btn-secondary:active { background:var(--primary-100); }
.btn-accent { background:var(--secondary-500); color:var(--neutral-900); }
.btn-accent:hover { background:var(--secondary-600); }
.btn-danger { background:var(--error-500); color:#fff; }
.btn-danger:hover { background:var(--error-700); }
.btn-sm { padding:6px 12px; font-size:12px; line-height:16px; }
.btn:disabled { background:var(--neutral-200); border-color:var(--neutral-200); color:var(--neutral-400); cursor:not-allowed; }

/* Icons */
.ic { width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:1.7; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
.ic.big { width:26px; height:26px; }
.btn .ic { width:16px; height:16px; }
.icon-act { width:32px; height:32px; border:0; background:transparent; border-radius:var(--r-lg); color:var(--neutral-500); display:inline-grid; place-items:center; cursor:pointer; transition:background .15s, color .15s; }
.icon-act:hover { background:var(--primary-50); color:var(--primary-500); }
.icon-act.danger:hover { background:var(--error-50); color:var(--error-500); }
.icon-act:focus-visible { outline:2px solid var(--primary-300); }

/* Badge / status — pill, tint 50 + semantic 500 (teks tetap tampil, bukan hanya warna) */
.status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:var(--r-full); font-size:12px; line-height:16px; font-weight:600; white-space:nowrap; }
.status:before { content:""; width:6px; height:6px; border-radius:var(--r-full); background:currentColor; }
.s-approved { background:var(--success-50); color:var(--success-700); }
.s-approved:before { background:var(--success-500); }
.s-pending { background:var(--warning-50); color:var(--warning-700); }
.s-pending:before { background:var(--warning-500); }
.s-neutral { background:var(--neutral-100); color:var(--neutral-600); }
.s-neutral:before { background:var(--neutral-400); }

/* Empty state */
.q-empty { display:grid; place-items:center; gap:4px; text-align:center; padding:24px; margin-top:12px; border:1px dashed var(--neutral-300); border-radius:var(--r-xl); background:var(--neutral-50); color:var(--neutral-500); font-size:14px; line-height:20px; }
.sect-title { font-family:var(--font-heading); font-size:16px; line-height:21px; font-weight:600; margin:8px 0 12px; color:var(--neutral-900); }

/* Partner & media */
.prow { display:flex; align-items:center; gap:16px; padding:12px 0; border-bottom:1px solid var(--neutral-200); }
.prow:last-child { border:0; }
.prow .plogo { width:120px; height:34px; object-fit:contain; object-position:left; }
.prow .pinfo { flex:1; min-width:0; }
.prow .pinfo b { font-size:14px; font-weight:600; color:var(--neutral-900); }
.prow .cell-sub { font-size:12px; color:var(--neutral-500); margin-top:2px; }
.prow.off .plogo { opacity:.35; filter:grayscale(1); }
.partner-strip { display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:24px 48px; padding:24px 20px; background:var(--neutral-50); border-radius:var(--r-xl); }
.partner-strip img { height:30px; max-width:130px; width:auto; object-fit:contain; filter:grayscale(1); opacity:.7; }
.partner-grid { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:12px; }
.partner-grid li { height:76px; display:grid; place-items:center; border:1px solid var(--neutral-200); border-radius:var(--r-xl); background:#fff; padding:10px; }
.partner-grid img { max-height:44px; max-width:100%; width:auto; object-fit:contain; }
.mgrid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:20px 16px; }
.mitem img { width:100%; aspect-ratio:4/3; object-fit:cover; border-radius:var(--r-xl); background:var(--neutral-100); display:block; }
.mitem img.logo { object-fit:contain; padding:16px; background:#fff; border:1px solid var(--neutral-200); }
.mitem b { display:block; font-size:12px; line-height:16px; font-weight:600; color:var(--neutral-800); margin-top:8px; }
.mitem span { font-size:12px; line-height:16px; color:var(--neutral-500); }
.sel-icon { display:flex; align-items:center; gap:12px; }
.sel-icon select { flex:1; min-width:0; }
.icon-prev { width:40px; height:40px; flex:none; }
.icon-prev svg { width:100%; height:100%; display:block; }

/* Toast */
.toast { position:fixed; right:24px; bottom:24px; background:var(--neutral-900); color:#fff; padding:12px 16px; border-radius:var(--r-xl); box-shadow:var(--shadow-lg); font-size:14px; line-height:20px; z-index:100; opacity:0; transform:translateY(8px); transition:.2s; pointer-events:none; }
.toast.show { opacity:1; transform:translateY(0); }

@media(max-width:1050px) { .cms-dd { max-width:100%; } }
@media(max-width:720px) { .field-grid { grid-template-columns:1fr; } .litem { flex-wrap:wrap; } .lthumb { width:100%; flex-basis:100%; } .lctl { flex-direction:row; } .panel-head,.panel-body,.panel-foot { padding-left:16px; padding-right:16px; } .toast { left:16px; right:16px; } }
</style>
@endpush

@section('content')
<div class="cms-content">
    <div class="cms-header">
        <div>
            <div class="cms-eyebrow">Konten Website</div>
            <h1 class="cms-title">CMS Company Profile</h1>
            <p class="cms-description">Kelola teks, gambar, layanan, proyek, sektor pelanggan, partner, testimoni, dan kontak yang tampil di website company profile. Urutan halaman di bawah mengikuti urutan section di website. Perubahan draft belum ditampilkan kepada pengunjung sampai dipublikasikan.</p>
        </div>
        <div class="cms-actions">
            <a href="{{ url('/cms/trash') }}" class="btn btn-secondary">Sampah CMS</a>
            <a href="{{ Route::has('home') ? route('home') : url('/') }}" target="_blank" rel="noopener" class="btn btn-secondary">
                <svg class="ic" aria-hidden="true"><use href="#cms-i-eye"></use></svg> Pratinjau Website
            </a>
        </div>
    </div>

    <div class="cms-layout">
        <aside class="cms-dd" id="cms-dd">
            <div class="cms-dd-label">Pilih halaman konten</div>
            <button type="button" class="cms-dd-btn" id="cms-dd-btn" aria-haspopup="listbox" aria-expanded="false">
                <span class="cms-dd-cur" id="cms-dd-cur">Pilih halaman</span>
                <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="panel cms-dd-menu" id="cms-dd-menu" role="listbox">
                <div class="cms-tabs" id="cms-tabs"></div>
            </div>
        </aside>
        <section id="cms-editor" style="min-width:0" aria-live="polite"></section>
    </div>
</div>
<div class="toast" id="cms-toast" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="cms-i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></symbol>
        <symbol id="cms-i-building" viewBox="0 0 24 24"><path d="M3 10 12 4l9 6v10H3z"/><path d="M8 20v-6h8v6"/></symbol>
        <symbol id="cms-i-eye" viewBox="0 0 24 24"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.8"/></symbol>
        <symbol id="cms-i-inventory" viewBox="0 0 24 24"><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="m3.5 7.5 8.5 4.5 8.5-4.5M12 12v9"/></symbol>
        <symbol id="cms-i-swap" viewBox="0 0 24 24"><path d="M7 8h12l-3-3M17 16H5l3 3"/></symbol>
        <symbol id="cms-i-order" viewBox="0 0 24 24"><rect x="5" y="4.5" width="14" height="16.5" rx="3"/><path d="m9 14 2 2 4-4"/></symbol>
        <symbol id="cms-i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.4c2 .7 3.5 2.6 3.5 5.6"/></symbol>
        <symbol id="cms-i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c0-4 3.4-6.5 7.5-6.5s7.5 2.5 7.5 6.5"/></symbol>
        <symbol id="cms-i-handshake" viewBox="0 0 24 24"><path d="m3 12 4-4 4 2 3-2 4 4v4l-4 4-3-1-2 1-6-5z"/><path d="m8 15 2 2M11 14l2 2"/></symbol>
        <symbol id="cms-i-image" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.7"/><path d="m4 18 5-5 4 4 3-3 4 4"/></symbol>
        <symbol id="cms-i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 8 8 5.5L20 8"/></symbol>
        <symbol id="cms-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="cms-i-upload" viewBox="0 0 24 24"><path d="M12 16V5m0 0 4 4m-4-4-4 4M5 20h14"/></symbol>
        <symbol id="cms-i-trash" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6.5 7l1 13h9l1-13M10 11v5M14 11v5"/></symbol>
        <symbol id="cms-i-up" viewBox="0 0 24 24"><path d="M12 19V5m-6 6 6-6 6 6"/></symbol>
        <symbol id="cms-i-down" viewBox="0 0 24 24"><path d="M12 5v14m-6-6 6 6 6-6"/></symbol>
    </defs>
</svg>
<script>
(() => {
    'use strict';

    const CMS_PAGES = @json($cmsPageDefinitions);
    const CMS_DATA = Object.assign({}, @json($cmsNormalized));
    const CMS_PARTNERS = @json($cmsPartners);
    const CMS_MEDIA = @json($cmsMedia);
    const CMS_UPDATE_URL = @json($cmsUpdateUrl);
    const CMS_SITE_URL = @json($cmsSiteUrl);
    const CMS_HAS_UPDATE_ROUTE = !!CMS_UPDATE_URL;
    let cmsCur = 'home';

    // Konten kosong dari PHP bisa berupa [] (bukan {}); samakan agar aman di-JSON-kan.
    Object.keys(CMS_DATA).forEach(key => {
        if (!CMS_DATA[key].content || Array.isArray(CMS_DATA[key].content)) CMS_DATA[key].content = {};
    });
    const cmsDraft = JSON.parse(JSON.stringify(CMS_DATA));

    const $ = (selector, root = document) => root.querySelector(selector);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    })[char]);
    const ic = name => `<svg class="ic" aria-hidden="true"><use href="#cms-i-${esc(name)}"></use></svg>`;
    const toast = message => {
        const el = $('#cms-toast');
        if (!el) return;
        el.textContent = message;
        el.classList.add('show');
        clearTimeout(window.__cmsToastTimer);
        window.__cmsToastTimer = setTimeout(() => el.classList.remove('show'), 3500);
    };
    const statusBadge = status => {
        const published = String(status).toLowerCase() === 'published';
        return `<span class="status ${published ? 's-approved' : 's-pending'}">${esc(status || 'Draft')}</span>`;
    };

    /* ------------------------------------------------------------------
     | Opsi bersama
     ------------------------------------------------------------------ */
    // Tombol di website hanya menuju section pada halaman yang sama (anchor).
    const LINK_OPTS = [
        {v:'#kontak',l:'Hubungi kami (formulir kontak)'}, {v:'#produk',l:'Produk & layanan'},
        {v:'#tentang',l:'Tentang perusahaan'}, {v:'#visi',l:'Visi & misi'}, {v:'#proyek',l:'Proyek terbaru'},
        {v:'#pelanggan',l:'Sektor pelanggan'}, {v:'#partner',l:'Partner & brand'}, {v:'#testimoni',l:'Testimoni'}
    ];

    // Ikon kartu sektor pelanggan (sama dengan SVG pada company profile).
    const SECTOR_ICONS = {
        bank:      {l:'Perbankan',        svg:'<path d="M10 17l12-6 12 6M12 17v0h20M14 20v9M20 20v9M24 20v9M30 20v9M11 32h22"/>'},
        oilgas:    {l:'Oli & gas',        svg:'<path d="M22 10c5 6 8 10 8 14a8 8 0 0 1-16 0c0-4 3-8 8-14z M22 32c-2 0-3-1.5-3-3"/>'},
        factory:   {l:'Manufaktur',       svg:'<path d="M10 32V20l8 5v-5l8 5v-5l8 5v7z M14 32v-4M22 32v-4M30 32v-4"/>'},
        logistics: {l:'Distribusi',       svg:'<path d="M9 14h16v14H9z M25 19h6l4 5v4H25z"/><circle cx="16" cy="30" r="2.5"/><circle cx="30" cy="30" r="2.5"/>'},
        hospital:  {l:'Rumah sakit',      svg:'<path d="M12 12h20v20H12z M22 17v10M17 22h10"/>'},
        retail:    {l:'Retail',           svg:'<path d="M12 17h20l-1.5 15h-17z M17 17v-2a5 5 0 0 1 10 0v2"/>'},
        education: {l:'Pendidikan',       svg:'<path d="M22 12l13 6-13 6-13-6z M15 21v6c0 2 3.5 4 7 4s7-2 7-4v-6 M35 18v8"/>'},
        general:   {l:'Umum / lainnya',   svg:'<path d="M12 32V14h12v18M24 32V22h8v10M10 32h24"/>'}
    };
    const SECTOR_ICON_OPTS = Object.keys(SECTOR_ICONS).map(k => ({v:k,l:SECTOR_ICONS[k].l}));
    const iconSvg = key => {
        const icon = SECTOR_ICONS[key] || SECTOR_ICONS.general;
        return `<svg viewBox="0 0 44 44" fill="none" aria-hidden="true"><rect width="44" height="44" rx="14" fill="#E7E9F6"/><g stroke="#0D21A1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${icon.svg}</g></svg>`;
    };

    /* ------------------------------------------------------------------
     | Definisi field per halaman — mengikuti section pada company profile.
     |
     | t     : text (default) | textarea | select | image | list | section
     | req   : wajib diisi sebelum publish
     | ph    : placeholder (contoh isi dari company profile)
     | hint  : petunjuk singkat di bawah field
     | max   : batas karakter (text) / jumlah item (list)
     | full  : lebar penuh (default: setengah lebar, textarea selalu penuh)
     | ratio : rasio pratinjau gambar sesuai tampilan di website
     | w     : lebar maksimum pratinjau gambar (px)
     | fit   : cover (dipotong mengikuti kotak) | contain (utuh)
     ------------------------------------------------------------------ */
    const CMS_FIELDS = {
        home: [
            {t:'section',l:'Hero (bagian paling atas)',d:'Judul besar, tombol, dan gambar di sisi kanan.'},
            {k:'heroTitle',l:'Judul utama',req:1,full:1,max:90,ph:'Mitra instalasi dan perlengkapan teknologi Anda'},
            {k:'heroSub',t:'textarea',l:'Sub judul',max:240,ph:'Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, …'},
            {k:'ctaLabel',l:'Teks tombol utama (kuning)',max:30,ph:'Minta penawaran'},
            {k:'ctaLink',t:'select',l:'Tombol utama menuju',opts:LINK_OPTS,def:'#kontak'},
            {k:'cta2Label',l:'Teks tombol kedua (garis biru)',max:30,ph:'Lihat produk'},
            {k:'cta2Link',t:'select',l:'Tombol kedua menuju',opts:LINK_OPTS,def:'#produk'},
            {k:'heroImg',t:'image',l:'Gambar hero',ratio:'1 / 1',w:300,hint:'Persegi (1:1), tampil dengan sudut membulat. JPG, PNG, atau WebP · maks. 2 MB.'},
            {k:'heroImgAlt',l:'Deskripsi gambar hero',full:1,max:120,ph:'Gedung perkantoran modern',hint:'Dibaca oleh pembaca layar dan mesin pencari.'},

            {t:'section',l:'Angka perusahaan',d:'Kartu angka yang menempel di bawah hero. Maksimal 4 (2 kolom di ponsel, 4 kolom di desktop).'},
            {k:'stats',t:'list',l:'Daftar angka',add:'Tambah angka',max:4,item:[
                {k:'value',l:'Angka',max:12,ph:'150+'},
                {k:'label',l:'Keterangan',max:40,ph:'Proyek terselesaikan'}
            ]},

            {t:'section',l:'Banner ajakan (biru, sebelum kontak)',d:'Banner penutup sebelum bagian kontak.'},
            {k:'ctaTitle',l:'Judul banner',full:1,max:80,ph:'Butuh instalasi atau perangkat baru?'},
            {k:'ctaSub',t:'textarea',l:'Teks pendukung',max:200,ph:'Ceritakan kebutuhan Anda, tim kami siap menyiapkan penawaran.'},
            {k:'ctaBtnLabel',l:'Teks tombol banner',max:30,ph:'Hubungi kami'},
            {k:'ctaBtnLink',t:'select',l:'Tombol banner menuju',opts:LINK_OPTS,def:'#kontak'},

            {t:'section',l:'Judul tab browser & mesin pencari',d:'Tidak tampil di halaman, tetapi muncul di tab browser dan hasil Google.'},
            {k:'seoTitle',l:'Judul website',full:1,max:70,ph:'PT Meditech Cipta Inovasi'},
            {k:'seoDesc',t:'textarea',l:'Deskripsi website',max:160,hint:'Disarankan 120–160 karakter.',ph:'Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, …'}
        ],
        about: [
            {t:'section',l:'Teks',d:'Kolom kiri pada bagian “Tentang perusahaan”.'},
            {k:'title',l:'Judul bagian',full:1,max:60,ph:'Tentang perusahaan'},
            {k:'body',t:'textarea',rows:5,l:'Profil perusahaan (paragraf utama)',req:1,hint:'Cantumkan tanggal berdiri dan bidang usaha di paragraf ini.',ph:'PT Meditech Cipta Inovasi didirikan pada …'},
            {k:'body2',t:'textarea',l:'Paragraf kedua',ph:'Kami berkomitmen memberikan pelayanan terbaik, …'},
            {k:'quote',t:'textarea',l:'Kutipan (kotak dengan garis kuning)',rows:2,max:160,ph:'Great things are never done by one person. They’re done by a team.'},
            {t:'section',l:'Foto',d:'Kolom kanan.'},
            {k:'img',t:'image',l:'Foto perusahaan',ratio:'4 / 3',w:420,hint:'Rasio 4:3. JPG, PNG, atau WebP · maks. 2 MB.'},
            {k:'imgAlt',l:'Deskripsi foto',full:1,max:120,ph:'Staf tersenyum sambil memeriksa dokumen'}
        ],
        vm: [
            {t:'section',l:'Visi',d:'Tampil dalam tanda kutip pada kartu di sisi kiri.'},
            {k:'visi',t:'textarea',l:'Pernyataan visi',req:1,rows:4,ph:'Menjadi perusahaan yang memiliki integritas, …'},
            {t:'section',l:'Misi',d:'Tampil sebagai daftar bernomor di sisi kanan. Nomor mengikuti urutan di bawah.'},
            {k:'misi',t:'list',l:'Poin misi',add:'Tambah poin misi',item:[{k:'text',t:'textarea',l:'Isi poin misi',req:1,rows:2}]}
        ],
        products: [
            {t:'section',l:'Judul bagian'},
            {k:'title',l:'Judul',full:1,max:60,ph:'Produk & layanan'},
            {k:'intro',t:'textarea',l:'Pengantar',rows:2,ph:'Instalasi jasa untuk fasilitas kesehatan dan perkantoran, …'},

            {t:'section',l:'Instalasi jasa',d:'Deretan kartu bergambar (4 kolom di desktop).'},
            {k:'serviceTag',l:'Label kartu (lencana kuning)',max:30,ph:'Instalasi jasa',hint:'Sama untuk semua kartu instalasi.'},
            {k:'services',t:'list',l:'Daftar layanan instalasi',add:'Tambah layanan instalasi',item:[
                {k:'img',t:'image',l:'Foto',ratio:'16 / 10',hint:'Rasio 16:10'},
                {k:'title',l:'Nama layanan',max:70,ph:'Jaringan gas medis & nurse call'}
            ]},

            {t:'section',l:'Hardware',d:'Kartu besar di kiri bawah: gambar brand dan daftar kategori.'},
            {k:'hwTitle',l:'Judul blok',max:40,ph:'Hardware'},
            {k:'hwImg',t:'image',l:'Gambar logo brand hardware',ratio:'5 / 2',w:560,fit:'contain',hint:'Kolase logo brand, ditampilkan utuh (tidak dipotong) selebar maks. 560 px. JPG, PNG, atau WebP · maks. 2 MB.'},
            {k:'hwImgAlt',l:'Deskripsi gambar',max:120,ph:'Logo brand perangkat keras'},
            {k:'hardware',t:'list',l:'Kategori hardware',add:'Tambah kategori hardware',item:[
                {k:'title',l:'Nama kategori',full:1,max:50,ph:'Paket hardware'},
                {k:'desc',t:'textarea',l:'Isi / brand',rows:2,ph:'PC, laptop, notebook, dan server (HP, Dell, Lenovo, …).'}
            ]},

            {t:'section',l:'Maintenance',d:'Kartu biru di kanan bawah.'},
            {k:'mtTitle',l:'Judul',max:40,ph:'Maintenance'},
            {k:'mtDesc',t:'textarea',l:'Deskripsi',rows:2,ph:'Layanan perawatan berkala agar perangkat dan instalasi tetap berfungsi optimal.'}
        ],
        projects: [
            {t:'section',l:'Judul bagian'},
            {k:'title',l:'Judul',full:1,max:60,ph:'Proyek terbaru'},
            {k:'intro',t:'textarea',l:'Pengantar',rows:2,ph:'Beberapa pekerjaan yang telah kami selesaikan.'},
            {t:'section',l:'Kartu proyek',d:'Tampil 3 kolom di desktop, urutan mengikuti daftar.'},
            {k:'items',t:'list',l:'Daftar proyek',add:'Tambah proyek',item:[
                {k:'tag',l:'Kategori (lencana kuning)',max:30,ph:'Gas medis & nurse call'},
                {k:'title',l:'Judul proyek',max:70,ph:'Instalasi ruang rawat inap'},
                {k:'meta',l:'Klien · lokasi · tahun',full:1,max:90,ph:'RS Contoh Sehat · Tangerang · 2025',hint:'Pisahkan dengan titik tengah ( · ).'}
            ]}
        ],
        customers: [
            {t:'section',l:'Judul bagian'},
            {k:'title',l:'Judul',full:1,max:60,ph:'Dipercaya berbagai sektor'},
            {k:'intro',t:'textarea',l:'Pengantar',rows:2,ph:'Kami membangun kemitraan yang erat dengan pelanggan …'},
            {t:'section',l:'Kartu sektor',d:'Setiap kartu menampilkan ikon dan nama sektor.'},
            {k:'sectors',t:'list',l:'Daftar sektor pelanggan',add:'Tambah sektor',item:[
                {k:'name',l:'Nama sektor',max:40,ph:'Perbankan'},
                {k:'icon',t:'select',l:'Ikon',opts:SECTOR_ICON_OPTS,def:'general',icons:true}
            ]}
        ],
        partners: [
            {t:'section',l:'Judul bagian'},
            {k:'title',l:'Judul',full:1,max:70,ph:'Brand yang bekerja sama dengan kami'},
            {k:'intro',t:'textarea',l:'Pengantar',rows:2,ph:'Kami memasok perangkat dari brand ternama dan mitra resmi.'}
        ],
        testi: [
            {t:'section',l:'Judul bagian'},
            {k:'title',l:'Judul',full:1,max:60,ph:'Kata pelanggan kami'},
            {t:'section',l:'Kartu testimoni',d:'Tampil 3 kolom di desktop dengan bintang 5.'},
            {k:'items',t:'list',l:'Testimoni pelanggan',add:'Tambah testimoni',item:[
                {k:'name',l:'Nama',max:60,ph:'Budi Santoso'},
                {k:'role',l:'Jabatan & instansi',max:80,ph:'Manajer Fasilitas, RS Contoh Sehat'},
                {k:'quote',t:'textarea',l:'Kutipan',rows:2,max:220,ph:'Instalasi selesai tepat waktu dan timnya responsif saat ada kendala.'}
            ]}
        ],
        contact: [
            {t:'section',l:'Bagian “Hubungi kami”',d:'Judul di samping formulir pesan.'},
            {k:'title',l:'Judul',max:40,ph:'Hubungi kami'},
            {k:'intro',t:'textarea',l:'Pengantar',rows:2,ph:'Kirim pesan atau datang langsung ke kantor kami.'},

            {t:'section',l:'Informasi kontak',d:'Dipakai di bagian kontak dan di footer.'},
            {k:'address',t:'textarea',l:'Alamat kantor',req:1,rows:3,ph:'Jl. Neo Paloma II Blok S10 No 26 …'},
            {k:'phone',l:'Telepon',req:1,ph:'021 - 8908 - 8898',hint:'Tautan telepon dibuat otomatis dari angka ini.'},
            {k:'email',l:'Email',req:1,type:'email',ph:'info@meditech.co.id',hint:'Juga menjadi tujuan formulir pesan.'},
            {k:'hours',l:'Jam operasional',full:1,ph:'Senin–Jumat, 08.00–17.00 WIB'},

            {t:'section',l:'Footer',d:'Kolom perusahaan dan baris hak cipta di bagian paling bawah.'},
            {k:'companyName',l:'Nama perusahaan',ph:'PT Meditech Cipta Inovasi'},
            {k:'tagline',l:'Tagline',ph:'Motivation Creative Innovation With Technology'},
            {k:'copyright',l:'Teks hak cipta',full:1,ph:'© 2026 PT Meditech Cipta Inovasi'}
        ]
    };

    /* ------------------------------------------------------------------
     | Data & path (path relatif terhadap konten halaman, mis. "stats.0.label")
     ------------------------------------------------------------------ */
    function getPage(key) { return CMS_PAGES.find(page => page.k === key); }
    function getContent(key) {
        if (!cmsDraft[key]) cmsDraft[key] = {content:{},status:'Draft'};
        if (!cmsDraft[key].content || Array.isArray(cmsDraft[key].content)) cmsDraft[key].content = {};
        return cmsDraft[key].content;
    }
    function setAtPath(path, value) {
        const parts = path.split('.');
        let node = getContent(cmsCur);
        for (let i=0;i<parts.length-1;i++) {
            const part = parts[i];
            if (node[part] == null) node[part] = /^\d+$/.test(parts[i+1]) ? [] : {};
            node = node[part];
        }
        node[parts[parts.length-1]] = value;
    }
    function applyDefaults(pageKey) {
        const content = getContent(pageKey);
        (CMS_FIELDS[pageKey]||[]).forEach(field => {
            if (field.def && !content[field.k]) content[field.k] = field.def;
        });
    }

    /* ------------------------------------------------------------------
     | Render field
     ------------------------------------------------------------------ */
    const hintHtml = field => field.hint ? `<div class="hint">${esc(field.hint)}</div>` : '';
    const labelHtml = field => `<label>${esc(field.l)}${field.req?' <i class="rq">*</i>':''}</label>`;

    function mediaField(field, path, value, compact = false) {
        const hasImage = !!value;
        const fit = field.fit === 'contain' ? 'contain' : 'cover';
        const ratio = field.ratio || '4 / 3';
        const maxW = !compact && field.w ? `max-width:${field.w}px;` : '';
        const inner = compact
            ? `<span class="dz-in">${ic('image')}<b>${hasImage?'Ganti':'Pilih foto'}</b></span>`
            : `<span class="dz-in">
                    ${ic('image')}
                    <b>${hasImage?'Gambar sudah diunggah':'Unggah gambar'}</b>
                    <small>${esc(field.hint || 'Format JPG, PNG, WebP · Maks. 2 MB')}</small>
                    <span class="btn btn-primary dz-upload-btn">${ic('upload')} ${hasImage?'Ganti gambar':'Pilih gambar'}</span>
                </span>`;
        return `<div class="field full"><label>${esc(field.l)}</label>
            <label class="dropzone fit-${fit} ${hasImage?'has':''} ${compact?'compact':''}" style="height:auto;aspect-ratio:${ratio};${maxW}" tabindex="0">
                ${hasImage?`<img src="${esc(value)}" alt="Pratinjau ${esc(field.l)}">`:''}
                ${inner}
                <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-cms-image="${esc(path)}">
            </label></div>`;
    }
    function selectField(field, path, value) {
        const current = value || field.def || '';
        const opts = field.opts.slice();
        if (current && !opts.some(opt => opt.v === current)) opts.push({v:current,l:`Kustom: ${current}`});
        const select = `<select data-cms-path="${esc(path)}" ${field.icons?'data-icon-select':''}>${opts.map(opt=>`<option value="${esc(opt.v)}" ${opt.v===current?'selected':''}>${esc(opt.l)}</option>`).join('')}</select>`;
        const body = field.icons ? `<div class="sel-icon"><span class="icon-prev">${iconSvg(current)}</span>${select}</div>` : select;
        return `<div class="field${field.full?' full':''}">${labelHtml(field)}${body}${hintHtml(field)}</div>`;
    }
    function renderField(field, path, value) {
        if (field.t === 'section') return `<div class="field-section"><b>${esc(field.l)}</b>${field.d?`<span>${esc(field.d)}</span>`:''}</div>`;
        if (field.t === 'image') return mediaField(field,path,value,!!field.compact);
        if (field.t === 'select') return selectField(field,path,value);
        const max = field.max ? ` maxlength="${field.max}"` : '';
        const ph = field.ph ? ` placeholder="${esc(field.ph)}"` : '';
        if (field.t === 'textarea') return `<div class="field full">${labelHtml(field)}<textarea rows="${field.rows || 3}"${max}${ph} data-cms-path="${esc(path)}">${esc(value)}</textarea>${hintHtml(field)}</div>`;
        return `<div class="field${field.full?' full':''}">${labelHtml(field)}<input type="${field.type || 'text'}" value="${esc(value)}"${max}${ph} data-cms-path="${esc(path)}">${hintHtml(field)}</div>`;
    }
    function renderBlock(field, content) {
        if (field.t !== 'list') return renderField(field, field.k, content[field.k]);
        const rows = Array.isArray(content[field.k]) ? content[field.k] : [];
        const imageField = field.item.find(sub => sub.t === 'image');
        const otherFields = field.item.filter(sub => sub !== imageField);
        const atMax = field.max && rows.length >= field.max;
        return `<div class="field full lblock">
            <div class="list-head"><label>${esc(field.l)}${field.max?` <span class="muted">· ${rows.length}/${field.max}</span>`:''}</label>
                <button type="button" class="btn btn-secondary btn-sm" data-list-add="${esc(field.k)}" ${atMax?'disabled':''}>${ic('plus')}${esc(field.add || 'Tambah item')}</button></div>
            ${rows.map((item,index)=>`<div class="litem">
                ${imageField?`<div class="lthumb">${mediaField({...imageField,compact:true},`${field.k}.${index}.${imageField.k}`,item?.[imageField.k]||'',true)}</div>`:''}
                <div class="lfields ${otherFields.length>1?'two':''}">
                    ${otherFields.map(sub=>renderField(sub,`${field.k}.${index}.${sub.k}`,item?.[sub.k] ?? '')).join('')}
                </div>
                <div class="lctl">
                    <button type="button" class="icon-act" data-list-up="${esc(field.k)}:${index}" title="Naikkan" aria-label="Naikkan">${ic('up')}</button>
                    <button type="button" class="icon-act" data-list-down="${esc(field.k)}:${index}" title="Turunkan" aria-label="Turunkan">${ic('down')}</button>
                    <button type="button" class="icon-act danger" data-list-remove="${esc(field.k)}:${index}" title="Hapus item" aria-label="Hapus item">${ic('trash')}</button>
                </div>
            </div>`).join('') || `<div class="q-empty">Belum ada item. Klik “${esc(field.add || 'Tambah item')}”.</div>`}
        </div>`;
    }

    function renderTabs() {
        $('#cms-tabs').innerHTML = CMS_PAGES.map(page=>{
            const status = CMS_DATA[page.k]?.status || 'Draft';
            return `<button type="button" class="cms-tab ${page.k===cmsCur?'active':''}" data-cms-page="${page.k}" role="option" aria-selected="${page.k===cmsCur?'true':'false'}">${ic(page.icon)}<span>${esc(page.label)}</span>${page.k!=='media'?`<i class="sdot ${status==='Published'?'published':''}" title="${esc(status)}"></i>`:''}</button>`;
        }).join('');
    }
    function partnersBlock() {
        const active = CMS_PARTNERS.filter(p=>Number(p.is_active)===1);
        return `<div class="field full" style="margin-top:6px">
            <div class="field-section"><b>Logo brand</b><span>Daftar logo yang tampil di website (hanya yang berstatus aktif). Urutan mengikuti display order.</span></div>
            <ul class="partner-grid" style="margin-top:12px">${active.map(p=>p.logo_url?`<li><img src="${esc(p.logo_url)}" alt="${esc(p.partner_name)}"></li>`:`<li class="muted">${esc(p.partner_name)}</li>`).join('') || '<li class="muted" style="grid-column:1/-1;border:0">Belum ada logo yang ditampilkan.</li>'}</ul>
            <div class="sect-title" style="margin-top:20px">Semua partner <span class="muted">· ${CMS_PARTNERS.length} terdaftar</span></div>
            ${CMS_PARTNERS.map(p=>`<div class="prow ${Number(p.is_active)!==1?'off':''}">${p.logo_url?`<img class="plogo" src="${esc(p.logo_url)}" alt="Logo ${esc(p.partner_name)}">`:'<div class="plogo muted">Tanpa logo</div>'}<div class="pinfo"><b>${esc(p.partner_name)}</b><div class="cell-sub">${esc(p.website_url || 'Tanpa website')}</div></div><span class="status ${Number(p.is_active)===1?'s-approved':'s-neutral'}">${Number(p.is_active)===1?'Aktif':'Nonaktif'}</span></div>`).join('') || '<div class="q-empty">Belum ada partner pada database.</div>'}
            <p class="muted" style="margin-top:12px">Tambah, ubah, atau hapus logo partner memerlukan endpoint partner tersendiri; judul dan pengantar di atas dapat disimpan dari halaman ini.</p>
        </div>`;
    }
    function renderMedia() {
        $('#cms-editor').innerHTML = `<div class="panel"><div class="panel-head"><div><div class="panel-title">Pustaka media</div><div class="panel-sub">${CMS_MEDIA.length} file media tersimpan. Ganti gambar melalui field pada halaman terkait.</div></div></div><div class="panel-body"><div class="mgrid">
            ${CMS_MEDIA.map(media=>`<div class="mitem">${media.url?`<img src="${esc(media.url)}" alt="${esc(media.alt_text || media.file_name)}" class="${String(media.media_type||'').includes('svg')?'logo':''}">`:'<div class="q-empty">Pratinjau tidak tersedia</div>'}<b>${esc(media.file_name)}</b><span>${esc(media.alt_text || 'Media website')}</span></div>`).join('') || '<div class="q-empty">Pustaka media masih kosong.</div>'}
        </div></div></div>`;
    }
    function renderPage() {
        const page = getPage(cmsCur);
        if (!page) return;
        renderTabs();
        const status = CMS_DATA[cmsCur]?.status || 'Draft';
        $('#cms-dd-cur').innerHTML = `${ic(page.icon)}<span>${esc(page.label)}</span><i class="sdot ${status==='Published'?'published':''}"></i>`;
        $('#cms-dd').classList.remove('open');
        $('#cms-dd-btn').setAttribute('aria-expanded','false');
        if (cmsCur === 'media') return renderMedia();

        applyDefaults(cmsCur);
        const content = getContent(cmsCur);
        const fields = CMS_FIELDS[cmsCur] || [];
        const updated = CMS_DATA[cmsCur]?.updated_at ? new Date(CMS_DATA[cmsCur].updated_at).toLocaleString('id-ID') : 'belum pernah';
        const editor = fields.map(field=>renderBlock(field,content)).join('') + (cmsCur === 'partners' ? partnersBlock() : '');
        const viewLink = page.anchor ? `<a class="btn btn-secondary btn-sm" href="${esc(CMS_SITE_URL + page.anchor)}" target="_blank" rel="noopener">${ic('eye')}Lihat di website</a>` : '';
        $('#cms-editor').innerHTML = `<div class="panel">
            <div class="panel-head"><div><div class="panel-title">${esc(page.label)}</div><div class="panel-sub">${esc(page.desc || '')}</div><div class="panel-sub">Diperbarui ${esc(updated)} · ${esc(CMS_DATA[cmsCur]?.updated_by || 'System')}</div></div><div class="panel-head-r">${viewLink}${statusBadge(status)}</div></div>
            <div class="panel-body"><div class="field-grid">${editor || '<div class="q-empty">Belum ada definisi field untuk halaman ini.</div>'}</div></div>
            <div class="panel-foot"><span class="muted">Kolom bertanda * wajib diisi sebelum publish.</span><div class="panel-foot-actions">
                <button type="button" class="btn btn-secondary" id="cms-save" ${CMS_HAS_UPDATE_ROUTE?'':'disabled'}>Simpan draft</button>
                <button type="button" class="btn btn-primary" id="cms-publish" ${CMS_HAS_UPDATE_ROUTE?'':'disabled'}>${ic('upload')}Publish</button>
            </div></div>
            ${CMS_HAS_UPDATE_ROUTE?'':'<div style="padding:0 24px 16px"><p class="muted">Penyimpanan belum aktif karena route <code>cms.pages.update</code> belum terdaftar. Lihat <code>routes-cms-snippet.php</code>.</p></div>'}
        </div>`;
    }

    /* ------------------------------------------------------------------
     | List
     ------------------------------------------------------------------ */
    function listField(listKey) { return (CMS_FIELDS[cmsCur]||[]).find(f=>f.k===listKey && f.t==='list'); }
    function addListItem(listKey) {
        const field = listField(listKey);
        if (!field) return;
        const content = getContent(cmsCur);
        const rows = Array.isArray(content[listKey]) ? content[listKey] : (content[listKey]=[]);
        if (field.max && rows.length >= field.max) return toast(`Maksimal ${field.max} item.`);
        const item = {};
        field.item.forEach(sub=>item[sub.k]=sub.def || '');
        rows.push(item);
        renderPage();
    }
    function moveListItem(listKey,index,direction) {
        const rows = getContent(cmsCur)[listKey];
        const next = index + direction;
        if (!Array.isArray(rows) || next<0 || next>=rows.length) return;
        [rows[index],rows[next]]=[rows[next],rows[index]];
        renderPage();
    }
    function removeListItem(listKey,index) {
        const rows = getContent(cmsCur)[listKey];
        if (!Array.isArray(rows) || !rows[index]) return;
        rows.splice(index,1);
        renderPage();
        toast('Item dihapus. Klik Simpan draft atau Publish untuk menyimpan perubahan.');
    }

    /* ------------------------------------------------------------------
     | Validasi & simpan
     ------------------------------------------------------------------ */
    function validateRequired() {
        const content = getContent(cmsCur);
        for (const field of (CMS_FIELDS[cmsCur]||[])) {
            if (field.t === 'section') continue;
            if (field.t === 'list') {
                const rows = Array.isArray(content[field.k]) ? content[field.k] : [];
                for (let i=0;i<rows.length;i++) {
                    for (const sub of field.item) {
                        if (sub.req && !String(rows[i]?.[sub.k] ?? '').trim()) {
                            toast(`Lengkapi “${sub.l}” pada ${field.l} nomor ${i+1}.`);
                            $(`[data-cms-path="${field.k}.${i}.${sub.k}"]`)?.focus();
                            return false;
                        }
                    }
                }
                continue;
            }
            if (field.req && !String(content[field.k] ?? '').trim()) {
                toast(`Lengkapi “${field.l}” sebelum publish.`);
                $(`[data-cms-path="${field.k}"]`)?.focus();
                return false;
            }
        }
        return true;
    }
    async function saveCMS(publish) {
        if (!CMS_HAS_UPDATE_ROUTE) return toast('Route penyimpanan CMS belum tersedia di backend.');
        const slug = getPage(cmsCur)?.slug;
        if (!slug) return toast('Halaman ini dikelola melalui modul khusus.');
        if (publish && !validateRequired()) return;

        const buttons = document.querySelectorAll('#cms-save,#cms-publish');
        buttons.forEach(button => button.disabled = true);
        try {
            const response = await fetch(CMS_UPDATE_URL.replace('__SLUG__', encodeURIComponent(slug)), {
                method:'PUT',
                credentials:'same-origin',
                headers:{
                    'Content-Type':'application/json',
                    'Accept':'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                    'X-CSRF-TOKEN':$('meta[name="csrf-token"]')?.content || ''
                },
                body:JSON.stringify({content:getContent(cmsCur),status:publish?'Published':'Draft'})
            });
            const result = await response.json().catch(()=>({}));
            if (!response.ok) throw new Error(result.message || 'Gagal menyimpan konten.');
            toast(result.message || (publish?'Konten dipublikasikan.':'Draft tersimpan.'));
            setTimeout(() => window.location.reload(), 600);
        } catch (error) {
            toast(error.message || 'Terjadi kesalahan saat menyimpan.');
            buttons.forEach(button => button.disabled = false);
        }
    }

    /* ------------------------------------------------------------------
     | Event
     ------------------------------------------------------------------ */
    document.addEventListener('click', event => {
        const pageButton = event.target.closest('[data-cms-page]');
        if (pageButton) { cmsCur = pageButton.dataset.cmsPage; renderPage(); return; }
        const addButton = event.target.closest('[data-list-add]');
        if (addButton) { addListItem(addButton.dataset.listAdd); return; }
        const upButton = event.target.closest('[data-list-up]');
        if (upButton) { const [key,index]=upButton.dataset.listUp.split(':'); moveListItem(key,Number(index),-1); return; }
        const downButton = event.target.closest('[data-list-down]');
        if (downButton) { const [key,index]=downButton.dataset.listDown.split(':'); moveListItem(key,Number(index),1); return; }
        const removeButton = event.target.closest('[data-list-remove]');
        if (removeButton) { const [key,index]=removeButton.dataset.listRemove.split(':'); removeListItem(key,Number(index)); return; }
        if (event.target.closest('#cms-save')) { saveCMS(false); return; }
        if (event.target.closest('#cms-publish')) { saveCMS(true); return; }
        if (event.target.closest('#cms-dd-btn')) {
            const open = $('#cms-dd').classList.toggle('open');
            $('#cms-dd-btn').setAttribute('aria-expanded',String(open));
            return;
        }
        if (!event.target.closest('#cms-dd')) {
            $('#cms-dd')?.classList.remove('open');
            $('#cms-dd-btn')?.setAttribute('aria-expanded','false');
        }
    });
    document.addEventListener('input', event => {
        const path = event.target.dataset.cmsPath;
        if (!path) return;
        setAtPath(path,event.target.value);
        if (event.target.matches('[data-icon-select]')) {
            const preview = event.target.closest('.sel-icon')?.querySelector('.icon-prev');
            if (preview) preview.innerHTML = iconSvg(event.target.value);
        }
    });
    document.addEventListener('change', event => {
        const input = event.target.closest('input[type="file"][data-cms-image]');
        if (!input) return;
        const file = input.files?.[0];
        if (!file) return;
        if (!['image/jpeg','image/png','image/webp'].includes(file.type)) { toast('Format gambar harus JPG, PNG, atau WebP.'); input.value=''; return; }
        if (file.size > 2*1024*1024) { toast('Ukuran gambar maksimal 2 MB.'); input.value=''; return; }
        const reader = new FileReader();
        reader.onload = () => {
            setAtPath(input.dataset.cmsImage,reader.result);
            const wrapper = input.closest('.dropzone');
            let img = wrapper.querySelector('img');
            if (!img) { img=document.createElement('img'); img.alt='Pratinjau gambar'; wrapper.prepend(img); }
            img.src=reader.result;
            wrapper.classList.add('has');
            toast('Gambar siap diunggah. Klik Simpan draft atau Publish.');
        };
        reader.readAsDataURL(file);
    });
    document.addEventListener('keydown', event => {
        if (event.key==='Escape') { $('#cms-dd')?.classList.remove('open'); $('#cms-dd-btn')?.setAttribute('aria-expanded','false'); }
    });

    renderPage();
})();
</script>
@endpush