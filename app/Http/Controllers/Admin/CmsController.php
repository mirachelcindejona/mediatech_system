<?php

namespace App\Http\Controllers\Admin;

use App\Models\CMS\CmsField;
use App\Models\CMS\CmsItem;
use App\Models\CMS\CmsMedia;
use App\Models\CMS\CmsPage;
use App\Models\CMS\CmsPartner;
use App\Services\Cms\CmsContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsController
{
    /**
     * Skema field per halaman (key = slug di tabel cms_pages).
     *
     * Harus sejalan dengan CMS_FIELDS di resources/views/admin/cms/index.blade.php
     * dan dengan section pada company profile:
     *   text | textarea | select | image  => satu nilai (image disimpan di cms_fields.media_id)
     *   list                              => array item, disimpan sebagai JSON di cms_fields.field_value
     *
     * Halaman "Cara kerja" dan blok "Mengapa memilih kami" sudah dihapus dari website,
     * jadi tidak ada lagi di skema ini.
     */
    private const PAGES = [
        'home' => [
            'title' => 'Beranda',
            'fields' => [
                'seoTitle'    => ['type' => 'text'],
                'seoDesc'     => ['type' => 'textarea'],
                'heroTitle'   => ['type' => 'text', 'req' => 'Judul utama'],
                'heroSub'     => ['type' => 'textarea'],
                'ctaLabel'    => ['type' => 'text'],
                'ctaLink'     => ['type' => 'text'],
                'cta2Label'   => ['type' => 'text'],
                'cta2Link'    => ['type' => 'text'],
                'heroImg'     => ['type' => 'image'],
                'heroImgAlt'  => ['type' => 'text'],
                'stats'       => ['type' => 'list', 'max' => 4, 'item' => ['value', 'label']],
                'ctaTitle'    => ['type' => 'text'],
                'ctaSub'      => ['type' => 'textarea'],
                'ctaBtnLabel' => ['type' => 'text'],
                'ctaBtnLink'  => ['type' => 'text'],
            ],
        ],
        'about-us' => [
            'title' => 'Tentang perusahaan',
            'fields' => [
                'title'  => ['type' => 'text'],
                'body'   => ['type' => 'textarea', 'req' => 'Profil perusahaan'],
                'body2'  => ['type' => 'textarea'],
                'quote'  => ['type' => 'textarea'],
                'img'    => ['type' => 'image'],
                'imgAlt' => ['type' => 'text'],
            ],
        ],
        'visi-misi' => [
            'title' => 'Visi & misi',
            'fields' => [
                'visi' => ['type' => 'textarea', 'req' => 'Visi'],
                'misi' => ['type' => 'list', 'item' => ['text']],
            ],
        ],
        'produk-layanan' => [
            'title' => 'Produk & layanan',
            'fields' => [
                'title'      => ['type' => 'text'],
                'intro'      => ['type' => 'textarea'],
                'serviceTag' => ['type' => 'text'],
                'services'   => ['type' => 'list', 'item' => ['img', 'title']],
                'hwTitle'    => ['type' => 'text'],
                'hwImg'      => ['type' => 'image'],
                'hwImgAlt'   => ['type' => 'text'],
                'hardware'   => ['type' => 'list', 'item' => ['title', 'desc']],
                'mtTitle'    => ['type' => 'text'],
                'mtDesc'     => ['type' => 'textarea'],
            ],
        ],
        'proyek-terbaru' => [
            'title' => 'Proyek terbaru',
            'fields' => [
                'title' => ['type' => 'text'],
                'intro' => ['type' => 'textarea'],
                'items' => ['type' => 'list', 'item' => ['tag', 'title', 'meta']],
            ],
        ],
        'sektor-pelanggan' => [
            'title' => 'Sektor pelanggan',
            'fields' => [
                'title'   => ['type' => 'text'],
                'intro'   => ['type' => 'textarea'],
                'sectors' => ['type' => 'list', 'item' => ['name', 'icon']],
            ],
        ],
        'partner-brand' => [
            'title' => 'Partner & brand',
            'fields' => [
                'title' => ['type' => 'text'],
                'intro' => ['type' => 'textarea'],
            ],
        ],
        'testimoni' => [
            'title' => 'Testimoni',
            'fields' => [
                'title' => ['type' => 'text'],
                'items' => ['type' => 'list', 'item' => ['name', 'role', 'quote']],
            ],
        ],
        'kontak' => [
            'title' => 'Kontak & footer',
            'fields' => [
                'title'       => ['type' => 'text'],
                'intro'       => ['type' => 'textarea'],
                'address'     => ['type' => 'textarea', 'req' => 'Alamat kantor'],
                'phone'       => ['type' => 'text', 'req' => 'Telepon'],
                'email'       => ['type' => 'text', 'req' => 'Email'],
                'hours'       => ['type' => 'text'],
                'companyName' => ['type' => 'text'],
                'tagline'     => ['type' => 'text'],
                'copyright'   => ['type' => 'text'],
                'siteName'      => ['type' => 'text'],
                'headerLogo'    => ['type' => 'image'],
                'headerLogoAlt' => ['type' => 'text'],
            ],
        ],
    ];

    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function index()
    {
        $pages = CmsPage::whereNull('deleted_at')->orderBy('page_id')->get();
        $pageData = $this->getPageData();

        return view('admin.cms.index', compact('pages', 'pageData'));
    }

    public function partners()
    {
        $partners = CmsPartner::with('media')->whereNull('deleted_at')->orderBy('display_order')->get();
        return view('admin.cms.partners', compact('partners'));
    }

    public function media()
    {
        $media = CmsMedia::whereNull('deleted_at')->orderByDesc('uploaded_at')->paginate(50);
        return view('admin.cms.media', compact('media'));
    }

    public function trash()
    {
        return view('admin.cms.trash');
    }


    /**
     * Memulihkan satu record dari Sampah CMS.
     *
     * Tipe dibatasi ke tabel CMS yang memang memiliki mekanisme soft delete.
     * Record yang sudah aktif tidak dapat dipulihkan ulang.
     */
    public function restoreTrash(string $type, int $id)
    {
        $tables = [
            'cms_pages'    => 'page_id',
            'cms_items'    => 'item_id',
            'cms_partners' => 'partner_id',
            'cms_media'    => 'media_id',
            'cms_fields'   => 'field_id',
        ];

        abort_unless(isset($tables[$type]), 404, 'Jenis data sampah tidak dikenal.');
        abort_unless(Schema::hasTable($type) && Schema::hasColumn($type, 'deleted_at'), 404);

        $idColumn = $tables[$type];

        try {
            $restored = DB::transaction(function () use ($type, $id, $idColumn) {
                $query = DB::table($type)->where($idColumn, $id)->whereNotNull('deleted_at');
                $record = $query->lockForUpdate()->first();

                if (!$record) {
                    return false;
                }

                $updates = ['deleted_at' => null];

                if (Schema::hasColumn($type, 'deleted_by')) {
                    $updates['deleted_by'] = null;
                }
                if (Schema::hasColumn($type, 'deletion_reason')) {
                    $updates['deletion_reason'] = null;
                }

                DB::table($type)->where($idColumn, $id)->update($updates);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Gagal memulihkan item Sampah CMS', [
                'table' => $type,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Item gagal dipulihkan. Silakan periksa relasi data atau hubungi administrator.');
        }

        if (!$restored) {
            return back()->with('error', 'Item tidak ditemukan di Sampah CMS atau sudah dipulihkan.');
        }

        return back()->with('success', 'Item berhasil dipulihkan.');
    }

    /**
     * Menghapus permanen satu record dari Sampah CMS.
     *
     * Halaman akan menghapus field, item, dan draft milik halaman tersebut.
     * Media tidak boleh dihapus jika masih direferensikan oleh data CMS lain.
     */
    public function forceDeleteTrash(string $type, int $id)
    {
        $tables = [
            'cms_pages'    => 'page_id',
            'cms_items'    => 'item_id',
            'cms_partners' => 'partner_id',
            'cms_media'    => 'media_id',
            'cms_fields'   => 'field_id',
        ];

        abort_unless(isset($tables[$type]), 404, 'Jenis data sampah tidak dikenal.');
        abort_unless(Schema::hasTable($type) && Schema::hasColumn($type, 'deleted_at'), 404);

        $idColumn = $tables[$type];
        $mediaPath = null;

        try {
            DB::transaction(function () use ($type, $id, $idColumn, &$mediaPath) {
                $record = DB::table($type)
                    ->where($idColumn, $id)
                    ->whereNotNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$record) {
                    abort(404, 'Item tidak ditemukan di Sampah CMS.');
                }

                if ($type === 'cms_media') {
                    // Jangan hapus file yang masih dipakai, termasuk oleh record yang
                    // sedang berada di sampah. Ini mencegah referensi media menjadi yatim.
                    foreach (['cms_fields', 'cms_items', 'cms_partners'] as $referenceTable) {
                        if (
                            Schema::hasTable($referenceTable)
                            && Schema::hasColumn($referenceTable, 'media_id')
                            && DB::table($referenceTable)->where('media_id', $id)->exists()
                        ) {
                            abort(409, 'Media masih digunakan oleh data CMS. Hapus atau ganti referensi media terlebih dahulu.');
                        }
                    }

                    $mediaPath = $record->file_path ?? null;
                }

                if ($type === 'cms_pages') {
                    $pageId = $id;

                    foreach (['cms_fields', 'cms_items'] as $childTable) {
                        if (Schema::hasTable($childTable) && Schema::hasColumn($childTable, 'page_id')) {
                            DB::table($childTable)->where('page_id', $pageId)->delete();
                        }
                    }

                    if (Schema::hasTable('cms_page_drafts') && Schema::hasColumn('cms_page_drafts', 'page_id')) {
                        DB::table('cms_page_drafts')->where('page_id', $pageId)->delete();
                    }
                }

                DB::table($type)->where($idColumn, $id)->delete();
            });

            // File fisik dihapus setelah transaksi database berhasil.
            if (
                $type === 'cms_media'
                && is_string($mediaPath)
                && $mediaPath !== ''
                && !str_starts_with($mediaPath, 'http://')
                && !str_starts_with($mediaPath, 'https://')
            ) {
                Storage::disk('public')->delete($mediaPath);
            }
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            if (in_array($e->getStatusCode(), [404, 409], true)) {
                return back()->with('error', $e->getMessage());
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Gagal menghapus permanen item Sampah CMS', [
                'table' => $type,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Item gagal dihapus permanen. Periksa relasi data dan konfigurasi database.');
        }

        return back()->with('success', 'Item berhasil dihapus permanen.');
    }

    /**
     * Simpan konten satu halaman. Payload: {content: {...}, status: "Draft"|"Published"}.
     */
    public function update(Request $request, string $slug)
    {
        $definition = self::PAGES[$slug] ?? null;
        if (!$definition) {
            return response()->json(['message' => 'Halaman CMS tidak dikenal.'], 404);
        }

        $content = $request->input('content', []);
        if (!is_array($content)) {
            return response()->json(['message' => 'Format konten tidak valid.'], 422);
        }

        $requestedStatus = $request->input('status', 'Draft');
        if (!in_array($requestedStatus, ['Draft', 'Published'], true)) {
            return response()->json(['message' => 'Status konten tidak valid.'], 422);
        }
        $status = $requestedStatus;

        // Draft memerlukan tabel cms_page_drafts. Migration-nya sudah tersedia
        // di database/migrations; tampilkan pesan eksplisit jika belum dijalankan.
        if ($status === 'Draft' && !Schema::hasTable('cms_page_drafts')) {
            return response()->json([
                'message' => 'Tabel draft CMS belum tersedia. Jalankan php artisan migrate terlebih dahulu.',
            ], 503);
        }

        if ($status === 'Published') {
            foreach ($definition['fields'] as $key => $rule) {
                if (!empty($rule['req']) && trim((string) ($content[$key] ?? '')) === '') {
                    return response()->json(['message' => 'Lengkapi “' . $rule['req'] . '” sebelum publish.'], 422);
                }
            }
        }

        try {
            DB::transaction(function () use ($slug, $definition, $content, $status) {
                $userId = auth()->id();

                $page = CmsPage::where('slug', $slug)->whereNull('deleted_at')->first();
                if (!$page) {
                    $page = CmsPage::create([
                        'slug'   => $slug,
                        'title'  => $definition['title'],
                        'status' => 'Draft',
                    ]);
                }

                // SIMPAN DRAFT: hanya ke tabel draft. Konten & status yang sedang tayang
                // di website tidak disentuh sampai tombol Publish diklik.
                if ($status === 'Draft') {
                    DB::table('cms_page_drafts')->updateOrInsert(
                        ['page_id' => $page->page_id],
                        [
                            'content'    => json_encode($this->sanitizeDraft($content, $definition, $userId), JSON_UNESCAPED_UNICODE),
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]
                    );

                    return;
                }

                // PUBLISH: tulis ke cms_fields, lalu hapus draft.
                foreach ($definition['fields'] as $key => $rule) {
                    if (!array_key_exists($key, $content)) {
                        continue;
                    }

                    $value = $content[$key];
                    $field = CmsField::where('page_id', $page->page_id)
                        ->where('field_key', $key)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$field) {
                        $field = new CmsField([
                            'page_id'            => $page->page_id,
                            'field_key'          => $key,
                            'field_value'        => '',
                            'media_aspect_ratio' => '16:9',
                            'media_fit_mode'     => 'cover',
                        ]);
                    }

                    switch ($rule['type']) {
                        case 'image':
                            $mediaId = $this->resolveMedia($value, $userId);
                            if ($mediaId) {
                                $field->media_id = $mediaId;
                                $field->field_value = '';
                            } elseif (!$field->exists) {
                                continue 2; // belum ada gambar dan tidak ada yang diunggah
                            }
                            // Jika kosong tetapi sudah ada gambar: gambar lama dipertahankan.
                            break;

                        case 'list':
                            $field->field_value = json_encode(
                                $this->cleanList($value, $rule, $userId),
                                JSON_UNESCAPED_UNICODE
                            );
                            $field->media_id = null;
                            break;

                        default:
                            $field->field_value = is_scalar($value) ? trim((string) $value) : '';
                            $field->media_id = null;
                    }

                    $field->save();
                }

                $page->status = 'Published';
                $page->updated_by = $userId;
                $page->updated_at = now();
                $page->save();

                if (Schema::hasTable('cms_page_drafts')) {
                    DB::table('cms_page_drafts')->where('page_id', $page->page_id)->delete();
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // Detail teknis (SQL, path) hanya masuk log, tidak dikirim ke browser.
            Log::error('Gagal menyimpan konten CMS', ['slug' => $slug, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Gagal menyimpan konten. Silakan coba lagi atau hubungi administrator.'], 500);
        }

        return response()->json([
            'message' => $status === 'Published'
                ? 'Konten dipublikasikan.'
                : 'Draft tersimpan. Website belum berubah sampai Anda klik Publish.',
            'status'  => $status,
        ]);
    }

    /**
     * Data untuk admin.cms.index. Struktur per slug:
     *   page, fields (keyBy field_key), items (group by section_key), status, updated_at, updated_by
     * Konversi media_id => URL dan JSON => array dilakukan di view.
     */
    private function getPageData(): array
    {
        $pages = CmsPage::with('updatedBy')->whereNull('deleted_at')->get();
        $pageIds = $pages->pluck('page_id');

        $fieldsByPage = CmsField::whereIn('page_id', $pageIds)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('page_id');

        $itemsByPage = CmsItem::whereIn('page_id', $pageIds)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('page_id');

        $drafts = Schema::hasTable('cms_page_drafts')
            ? DB::table('cms_page_drafts')->whereIn('page_id', $pageIds)->get()->keyBy('page_id')
            : collect();

        $data = [];

        foreach ($pages as $page) {
            $draftRow = $drafts->get($page->page_id);
            $draftContent = $draftRow ? json_decode($draftRow->content, true) : null;

            $data[$page->slug] = [
                'draft'      => is_array($draftContent) ? $draftContent : null,
                'draft_at'   => $draftRow->updated_at ?? null,
                'page'       => $page,
                'fields'     => ($fieldsByPage->get($page->page_id) ?? collect())->keyBy('field_key'),
                'items'      => ($itemsByPage->get($page->page_id) ?? collect())->groupBy('section_key'),
                'status'     => $page->status,
                'updated_at' => $page->updated_at,
                'updated_by' => $page->updatedBy ? $page->updatedBy->name : 'System',
            ];
        }

        return $data;
    }

    /**
     * Susun konten draft: hanya key yang ada di skema, unggahan baru (data URI)
     * langsung disimpan sebagai file dan diganti URL-nya agar JSON draft tetap kecil.
     */
    private function sanitizeDraft(array $content, array $definition, $userId): array
    {
        $draft = [];

        foreach ($definition['fields'] as $key => $rule) {
            if (!array_key_exists($key, $content)) {
                continue;
            }

            $value = $content[$key];

            if ($rule['type'] === 'list') {
                $rows = [];
                foreach ((is_array($value) ? $value : []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $item = [];
                    foreach ($rule['item'] as $itemKey) {
                        $raw = $row[$itemKey] ?? '';
                        $item[$itemKey] = $itemKey === 'img'
                            ? $this->draftImage($raw, $userId)
                            : (is_scalar($raw) ? trim((string) $raw) : '');
                    }
                    $rows[] = $item;
                }

                $draft[$key] = !empty($rule['max']) ? array_slice($rows, 0, (int) $rule['max']) : $rows;
                continue;
            }

            $draft[$key] = $rule['type'] === 'image'
                ? $this->draftImage($value, $userId)
                : (is_scalar($value) ? trim((string) $value) : '');
        }

        return $draft;
    }

    private function draftImage($value, $userId): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        if (str_starts_with($value, 'data:image/')) {
            $media = CmsMedia::find($this->storeDataUri($value, $userId));
            return $media ? CmsContentService::mediaUrl($media->file_path) : '';
        }

        return $value; // URL gambar yang sudah tersimpan
    }

    /**
     * Bersihkan list: hanya key yang diizinkan, gambar diubah menjadi media_id,
     * baris yang seluruhnya kosong dibuang.
     */
    private function cleanList($rows, array $rule, $userId): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = [];
            $filled = false;

            foreach ($rule['item'] as $key) {
                $raw = $row[$key] ?? '';

                if ($key === 'img') {
                    $mediaId = $this->resolveMedia($raw, $userId);
                    $item[$key] = $mediaId;
                    $filled = $filled || (bool) $mediaId;
                    continue;
                }

                $text = is_scalar($raw) ? trim((string) $raw) : '';
                $item[$key] = $text;
                $filled = $filled || $text !== '';
            }

            if ($filled) {
                $clean[] = $item;
            }
        }

        if (!empty($rule['max'])) {
            $clean = array_slice($clean, 0, (int) $rule['max']);
        }

        return $clean;
    }

    /**
     * Terima media_id, URL media yang sudah ada, atau data URI (unggahan baru).
     * Mengembalikan media_id atau null.
     */
    private function resolveMedia($value, $userId): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $id = (int) $value;
            return CmsMedia::where('media_id', $id)->whereNull('deleted_at')->exists() ? $id : null;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        // Unggahan baru (data URI).
        if (str_starts_with($value, 'data:image/')) {
            return $this->storeDataUri($value, $userId);
        }

        // URL gambar yang sudah tersimpan: cari kembali media_id-nya.
        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $prefix = parse_url(Storage::disk('public')->url(''), PHP_URL_PATH) ?: '';
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }
        $path = ltrim(rawurldecode($path), '/');

        $media = CmsMedia::whereNull('deleted_at')
            ->where(function ($query) use ($value, $path) {
                $query->where('file_path', $value)->orWhere('file_path', $path);
            })
            ->first();

        return $media ? (int) $media->media_id : null;
    }

    private function storeDataUri(string $dataUri, $userId): int
    {
        if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,(.+)$#s', $dataUri, $m)) {
            throw new \InvalidArgumentException('Format gambar harus JPG, PNG, atau WebP.');
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('File gambar tidak dapat dibaca.');
        }
        if (strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new \InvalidArgumentException('Ukuran gambar maksimal 2 MB.');
        }

        $info = @getimagesizefromstring($binary);
        if (!$info || !isset(self::IMAGE_MIMES[$info['mime']])) {
            throw new \InvalidArgumentException('File yang diunggah bukan gambar yang valid.');
        }

        $mime = $info['mime'];
        $fileName = Str::uuid() . '.' . self::IMAGE_MIMES[$mime];
        $filePath = 'cms/' . $fileName;

        Storage::disk('public')->put($filePath, $binary);

        $media = CmsMedia::create([
            'file_name'    => $fileName,
            'file_path'    => $filePath,
            'media_type'   => $mime,
            'alt_text'     => '',
            'file_size_kb' => (int) ceil(strlen($binary) / 1024),
            'width_px'     => $info[0] ?? null,
            'height_px'    => $info[1] ?? null,
            'uploaded_by'  => $userId,
            'uploaded_at'  => now(),
        ]);

        return (int) $media->media_id;
    }
}