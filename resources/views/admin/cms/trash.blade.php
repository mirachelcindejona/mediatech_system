@extends('admin.layouts.layout')

@section('title', 'Sampah CMS')

@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    /*
     * Sampah CMS mengambil data soft-deleted dari tabel CMS yang memang
     * memiliki kolom deleted_at. Aksi pemulihan/penghapusan permanen
     * ditampilkan hanya jika route backend terkait sudah tersedia.
     */
    $trashRows = collect();
    $trashTables = [
        'cms_pages' => ['type' => 'Halaman', 'id' => 'page_id', 'title' => ['title', 'slug']],
        'cms_items' => ['type' => 'Item konten', 'id' => 'item_id', 'title' => ['title', 'name', 'item_title']],
        'cms_partners' => ['type' => 'Partner / brand', 'id' => 'partner_id', 'title' => ['partner_name', 'name']],
        'cms_media' => ['type' => 'Media', 'id' => 'media_id', 'title' => ['file_name', 'alt_text']],
        'cms_fields' => ['type' => 'Field halaman', 'id' => 'field_id', 'title' => ['field_key']],
    ];

    foreach ($trashTables as $table => $config) {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'deleted_at')) continue;

        $records = DB::table($table)->whereNotNull('deleted_at')->orderByDesc('deleted_at')->limit(500)->get();
        foreach ($records as $record) {
            $raw = (array) $record;
            $title = 'Item tanpa nama';
            foreach ($config['title'] as $candidate) {
                if (!empty($raw[$candidate])) { $title = (string) $raw[$candidate]; break; }
            }

            $section = $config['type'];
            if ($table === 'cms_items' || $table === 'cms_fields') {
                $page = !empty($raw['page_id']) && Schema::hasTable('cms_pages')
                    ? DB::table('cms_pages')->where('page_id', $raw['page_id'])->first()
                    : null;
                $pageTitle = $page->title ?? $page->slug ?? 'Halaman tidak diketahui';
                $section = $config['type'] . ' · ' . $pageTitle;
                if ($table === 'cms_items' && !empty($raw['section_key'])) $section .= ' / ' . $raw['section_key'];
            }

            $idColumn = $config['id'];
            $trashRows->push([
                'key' => $table . ':' . ($raw[$idColumn] ?? ''),
                'table' => $table,
                'type' => $config['type'],
                'id' => $raw[$idColumn] ?? null,
                'title' => $title,
                'section' => $section,
                'deleted_at' => $raw['deleted_at'] ?? null,
                'deleted_by' => $raw['deleted_by'] ?? $raw['updated_by'] ?? null,
                'description' => $raw['description'] ?? $raw['field_value'] ?? $raw['alt_text'] ?? null,
                'image' => $table === 'cms_media' && !empty($raw['file_path'])
                    ? (str_starts_with($raw['file_path'], 'http') ? $raw['file_path'] : '/storage/' . ltrim($raw['file_path'], '/'))
                    : null,
                'raw' => $raw,
            ]);
        }
    }

    $trashRows = $trashRows->sortByDesc(fn ($row) => $row['deleted_at'] ? strtotime($row['deleted_at']) : 0)->values();
    $trashCount = $trashRows->count();
    $canRestoreTrash = Route::has('cms.trash.restore');
    $canForceDeleteTrash = Route::has('cms.trash.force-delete');
@endphp

@push('styles')
<style>
.cms-trash{--ct-primary:#0d21a1;--ct-ink:#171717;--ct-muted:#737373;--ct-border:#e5e5e5;--ct-bg:#fafafa;color:var(--ct-ink);font-family:Inter,ui-sans-serif,system-ui,sans-serif}
.cms-trash *{box-sizing:border-box}
.cms-trash .trash-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px;flex-wrap:wrap}
.cms-trash .eyebrow{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--ct-primary);font-weight:700;margin-bottom:6px}
.cms-trash h1{font-size:28px;line-height:1.25;font-weight:700;margin:0;color:var(--ct-ink)}
.cms-trash .subtitle{font-size:14px;line-height:1.6;color:var(--ct-muted);margin:8px 0 0;max-width:760px}
.cms-trash .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;border:1px solid #d4d4d4;border-radius:8px;background:#fff;color:#262626;text-decoration:none;font-size:13px;font-weight:600;cursor:pointer}
.cms-trash .btn-primary{background:var(--ct-primary);border-color:var(--ct-primary);color:#fff}
.cms-trash .btn:disabled{opacity:.45;cursor:not-allowed}
.cms-trash .stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
.cms-trash .stat{background:#fff;border:1px solid var(--ct-border);border-radius:12px;padding:16px 18px}
.cms-trash .stat-label{font-size:12px;color:var(--ct-muted);margin-bottom:6px}
.cms-trash .stat-value{font-size:24px;line-height:1.2;font-weight:700}
.cms-trash .stat-note{font-size:11px;color:var(--ct-muted);margin-top:5px}
.cms-trash .panel{background:#fff;border:1px solid var(--ct-border);border-radius:12px;overflow:hidden}
.cms-trash .panel-head{padding:18px 20px;border-bottom:1px solid var(--ct-border);display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.cms-trash .panel-title{font-size:15px;font-weight:700}
.cms-trash .panel-sub{font-size:12px;color:var(--ct-muted);margin-top:4px}
.cms-trash .filters{display:flex;gap:10px;flex-wrap:wrap;padding:16px 20px;border-bottom:1px solid var(--ct-border)}
.cms-trash input,.cms-trash select{height:40px;border:1px solid #d4d4d4;border-radius:8px;padding:0 12px;background:#fff;color:#262626;font:inherit;font-size:13px;min-width:180px}
.cms-trash input{flex:1;min-width:220px}
.cms-trash .table-wrap{overflow-x:auto}
.cms-trash table{width:100%;border-collapse:collapse;min-width:850px}
.cms-trash th{background:#fafafa;color:#737373;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:12px 16px;white-space:nowrap}
.cms-trash td{padding:14px 16px;border-top:1px solid #f0f0f0;font-size:13px;vertical-align:middle}
.cms-trash .item-name{font-weight:650;color:#262626;max-width:280px;overflow-wrap:anywhere}
.cms-trash .item-sub{font-size:11px;color:var(--ct-muted);margin-top:4px}
.cms-trash .type-pill{display:inline-flex;padding:4px 8px;border-radius:999px;background:#f5f5f5;color:#525252;font-size:11px;font-weight:600;white-space:nowrap}
.cms-trash .deleted-time{white-space:nowrap;color:#525252;font-size:12px}
.cms-trash .actions{display:flex;gap:8px;align-items:center;white-space:nowrap}
.cms-trash .text-btn{border:0;background:none;padding:4px 0;color:var(--ct-primary);font-size:12px;font-weight:700;cursor:pointer}
.cms-trash .text-btn.danger{color:#c10007}
.cms-trash .empty{padding:54px 20px;text-align:center;color:var(--ct-muted)}
.cms-trash .empty-icon{width:48px;height:48px;border-radius:14px;background:#f5f5f5;display:grid;place-items:center;margin:0 auto 12px;color:#737373;font-size:22px}
.cms-trash .notice{margin:16px 20px 20px;padding:12px 14px;border-radius:8px;background:#fefce8;color:#854d0e;font-size:12px;line-height:1.6}
.cms-trash .modal{position:fixed;inset:0;background:rgba(23,23,23,.48);display:none;align-items:center;justify-content:center;padding:20px;z-index:10000}
.cms-trash .modal.open{display:flex}
.cms-trash .modal-card{width:min(620px,100%);max-height:85vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.cms-trash .modal-head,.cms-trash .modal-foot{padding:16px 20px;border-bottom:1px solid var(--ct-border);display:flex;align-items:center;justify-content:space-between;gap:12px}
.cms-trash .modal-foot{border-bottom:0;border-top:1px solid var(--ct-border);justify-content:flex-end}
.cms-trash .modal-body{padding:20px}
.cms-trash .modal-title{font-size:16px;font-weight:700}
.cms-trash .close{border:0;background:#f5f5f5;border-radius:8px;width:32px;height:32px;font-size:20px;cursor:pointer}
.cms-trash pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#fafafa;padding:12px;border-radius:8px;font-size:11px;max-height:260px;overflow:auto}
@media(max-width:700px){.cms-trash .stats{grid-template-columns:1fr}.cms-trash h1{font-size:23px}.cms-trash .panel-head,.cms-trash .filters{padding-left:14px;padding-right:14px}}
</style>
@endpush

@section('content')
<div class="cms-trash">
    <div class="trash-head">
        <div>
            <div class="eyebrow">Manajemen konten</div>
            <h1>Sampah CMS</h1>
            <p class="subtitle">Kelola halaman, item konten, partner, field, dan media yang dihapus sementara. Pulihkan data untuk mengaktifkannya kembali atau hapus permanen jika sudah tidak diperlukan.</p>
        </div>
        <a href="{{ url('/cms') }}" class="btn">← Kembali ke CMS</a>
    </div>

    @if(session('success'))
        <div role="status" style="margin:0 0 16px;padding:12px 14px;border:1px solid #bbf7d0;border-radius:8px;background:#f0fdf4;color:#166534;font-size:13px">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div role="alert" style="margin:0 0 16px;padding:12px 14px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;color:#991b1b;font-size:13px">
            {{ session('error') }}
        </div>
    @endif

    <div class="stats">
        <div class="stat"><div class="stat-label">Total item di sampah</div><div class="stat-value">{{ $trashCount }}</div><div class="stat-note">Seluruh jenis data yang terdeteksi</div></div>
        <div class="stat"><div class="stat-label">Halaman & konten</div><div class="stat-value">{{ $trashRows->whereIn('type', ['Halaman','Item konten','Field halaman'])->count() }}</div><div class="stat-note">Data konten website</div></div>
        <div class="stat"><div class="stat-label">Partner & media</div><div class="stat-value">{{ $trashRows->whereIn('type', ['Partner / brand','Media'])->count() }}</div><div class="stat-note">Aset pendukung website</div></div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div><div class="panel-title">Daftar item terhapus</div><div class="panel-sub">Gunakan pencarian dan filter untuk menemukan data tertentu.</div></div>
            <span class="type-pill">{{ $trashCount }} item</span>
        </div>
        <div class="filters">
            <input type="search" id="trash-search" placeholder="Cari nama item atau bagian..." aria-label="Cari item sampah">
            <select id="trash-type" aria-label="Filter jenis data">
                <option value="">Semua jenis data</option>
                @foreach($trashRows->pluck('type')->unique() as $type)<option value="{{ strtolower($type) }}">{{ $type }}</option>@endforeach
            </select>
            <select id="trash-sort" aria-label="Urutkan data">
                <option value="newest">Terbaru dihapus</option><option value="oldest">Terlama dihapus</option><option value="name">Nama A–Z</option>
            </select>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nama item</th><th>Jenis</th><th>Bagian</th><th>Waktu dihapus</th><th>Dihapus oleh</th><th>Aksi</th></tr></thead>
                <tbody id="trash-table-body">
                @forelse($trashRows as $row)
                    <tr data-trash-row data-type="{{ strtolower($row['type']) }}" data-name="{{ strtolower($row['title'].' '.$row['section']) }}" data-time="{{ $row['deleted_at'] ? strtotime($row['deleted_at']) : 0 }}">
                        <td><div class="item-name">{{ $row['title'] }}</div><div class="item-sub">ID: {{ $row['id'] ?? '—' }}</div></td>
                        <td><span class="type-pill">{{ $row['type'] }}</span></td>
                        <td>{{ $row['section'] }}</td>
                        <td><div class="deleted-time">{{ $row['deleted_at'] ? \Carbon\Carbon::parse($row['deleted_at'])->format('d M Y, H:i') : '—' }}</div></td>
                        <td>{{ $row['deleted_by'] ?? 'Tidak tercatat' }}</td>
                        <td><div class="actions">
                            <button type="button" class="text-btn" data-trash-detail='@json($row)'>Detail</button>
                            @if($canRestoreTrash)
                                <form method="POST" action="{{ route('cms.trash.restore', ['type' => $row['table'], 'id' => $row['id']]) }}" onsubmit="return confirm('Pulihkan item ini?')">@csrf<button class="text-btn" type="submit">Pulihkan</button></form>
                            @endif
                            @if($canForceDeleteTrash)
                                <form method="POST" action="{{ route('cms.trash.force-delete', ['type' => $row['table'], 'id' => $row['id']]) }}" onsubmit="return confirm('Hapus permanen? Tindakan ini tidak dapat dibatalkan.')">@csrf @method('DELETE')<button class="text-btn danger" type="submit">Hapus permanen</button></form>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr id="trash-empty-row"><td colspan="6"><div class="empty"><div class="empty-icon">⌫</div><strong>Sampah CMS kosong</strong><div class="item-sub">Belum ada data yang dihapus sementara.</div></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if(!$canRestoreTrash || !$canForceDeleteTrash)
            <div class="notice">Sebagian aksi belum tersedia karena route backend belum terdaftar. Pastikan route Sampah CMS sudah dimuat dan aksesnya dibatasi untuk administrator CMS.</div>
        @endif
    </section>
</div>

<div class="cms-trash">
    <div class="modal" id="trash-detail-modal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="trash-detail-title">
            <div class="modal-head"><div class="modal-title" id="trash-detail-title">Detail item</div><button type="button" class="close" id="trash-detail-close" aria-label="Tutup">×</button></div>
            <div class="modal-body" id="trash-detail-content"></div>
            <div class="modal-foot"><button type="button" class="btn" id="trash-detail-done">Tutup</button></div>
        </div>
    </div>
</div>

<script>
(() => {
    const search = document.getElementById('trash-search');
    const type = document.getElementById('trash-type');
    const sort = document.getElementById('trash-sort');
    const body = document.getElementById('trash-table-body');
    const modal = document.getElementById('trash-detail-modal');
    const detailContent = document.getElementById('trash-detail-content');
    const rows = () => [...body.querySelectorAll('[data-trash-row]')];

    function applyFilters() {
        const q = (search.value || '').trim().toLowerCase();
        const selectedType = type.value;
        rows().forEach(row => {
            const matches = row.dataset.name.includes(q) && (!selectedType || row.dataset.type === selectedType);
            row.hidden = !matches;
        });
        const visible = rows().filter(row => !row.hidden);
        visible.sort((a,b) => {
            if (sort.value === 'oldest') return Number(a.dataset.time) - Number(b.dataset.time);
            if (sort.value === 'name') return a.dataset.name.localeCompare(b.dataset.name, 'id');
            return Number(b.dataset.time) - Number(a.dataset.time);
        }).forEach(row => body.appendChild(row));
    }
    [search,type,sort].forEach(el => el?.addEventListener('input', applyFilters));
    [type,sort].forEach(el => el?.addEventListener('change', applyFilters));

    body?.addEventListener('click', event => {
        const button = event.target.closest('[data-trash-detail]');
        if (!button) return;
        let item;
        try { item = JSON.parse(button.dataset.trashDetail); } catch (_) { return; }
        const safe = value => String(value ?? '—').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        detailContent.innerHTML = `
            ${item.image ? `<img src="${safe(item.image)}" alt="${safe(item.title)}" style="max-width:100%;max-height:180px;object-fit:contain;border:1px solid #e5e5e5;border-radius:8px;margin-bottom:14px">` : ''}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div><div class="item-sub">Nama</div><strong>${safe(item.title)}</strong></div>
                <div><div class="item-sub">Jenis</div><strong>${safe(item.type)}</strong></div>
                <div><div class="item-sub">Bagian</div><strong>${safe(item.section)}</strong></div>
                <div><div class="item-sub">ID</div><strong>${safe(item.id)}</strong></div>
                <div><div class="item-sub">Waktu dihapus</div><strong>${safe(item.deleted_at)}</strong></div>
                <div><div class="item-sub">Dihapus oleh</div><strong>${safe(item.deleted_by)}</strong></div>
            </div>
            ${item.description ? `<div class="item-sub">Ringkasan</div><p>${safe(item.description)}</p>` : ''}
            <div class="item-sub">Snapshot data</div><pre>${safe(JSON.stringify(item.raw, null, 2))}</pre>`;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden','false');
    });
    function closeModal(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); }
    document.getElementById('trash-detail-close')?.addEventListener('click', closeModal);
    document.getElementById('trash-detail-done')?.addEventListener('click', closeModal);
    modal?.addEventListener('click', event => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(); });
})();
</script>
@endsection
