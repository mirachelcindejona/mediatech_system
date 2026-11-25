<?php

namespace App\Services\Cms;

use App\Models\CMS\CmsField;
use App\Models\CMS\CmsItem;
use App\Models\CMS\CmsMedia;
use App\Models\CMS\CmsPage;
use App\Models\CMS\CmsPartner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membaca konten CMS untuk website publik (company profile).
 *
 * Hasil all():
 *   [
 *     'home' => [...], 'about' => [...], 'vm' => [...], 'products' => [...],
 *     'projects' => [...], 'customers' => [...], 'partners' => [...],
 *     'testi' => [...], 'contact' => [...],
 *     '_visible' => ['home' => true, ...],
 *   ]
 *
 * Aturan tampil per halaman (section):
 *   - halaman berstatus Published                => konten dibaca dari database
 *   - halaman belum ada di database              => tampil memakai teks bawaan di view
 *   - halaman ada tetapi bukan Published (Draft) => section disembunyikan
 */
class CmsContentService
{
    public const SLUG_KEYS = [
        'home'             => 'home',
        'about-us'         => 'about',
        'visi-misi'        => 'vm',
        'produk-layanan'   => 'products',
        'proyek-terbaru'   => 'projects',
        'sektor-pelanggan' => 'customers',
        'partner-brand'    => 'partners',
        'testimoni'        => 'testi',
        'kontak'           => 'contact',
    ];

    private ?array $content = null;

    public function all(): array
    {
        return $this->content ??= $this->build();
    }

    /**
     * Konten khusus pratinjau admin: gunakan Published sebagai dasar,
     * lalu timpa hanya field yang memiliki Draft tersimpan.
     * Metode ini tidak mengubah cache all() maupun data Published.
     */
    public function allWithDrafts(): array
    {
        $result = $this->build();

        if (!\Illuminate\Support\Facades\Schema::hasTable('cms_page_drafts')) {
            // Preview tetap dapat dibuka dengan konten Published/fallback.
            $result['_preview'] = true;
            return $result;
        }

        $pages = CmsPage::whereNull('deleted_at')
            ->whereIn('slug', array_keys(self::SLUG_KEYS))
            ->get(['page_id', 'slug']);

        if ($pages->isEmpty()) {
            $result['_preview'] = true;
            return $result;
        }

        $drafts = DB::table('cms_page_drafts')
            ->whereIn('page_id', $pages->pluck('page_id'))
            ->get(['page_id', 'content'])
            ->keyBy('page_id');

        foreach ($pages as $page) {
            $draftRow = $drafts->get($page->page_id);
            if (!$draftRow) {
                continue; // Tidak ada Draft: pertahankan Published/fallback.
            }

            $key = self::SLUG_KEYS[$page->slug] ?? null;
            if (!$key) {
                continue;
            }

            $draftContent = json_decode($draftRow->content, true);
            if (!is_array($draftContent)) {
                continue;
            }

            $result[$key] = array_replace($result[$key] ?? [], $draftContent);
            $result['_visible'][$key] = true;
        }

        // Di mode Preview, section yang belum pernah dipublikasikan tetap
        // ditampilkan memakai fallback bawaan Blade jika belum ada Draft.
        foreach (array_values(self::SLUG_KEYS) as $key) {
            $result['_visible'][$key] = true;
        }

        $result['_preview'] = true;
        return $result;
    }

    /**
     * Logo partner/brand yang aktif, urut display_order.
     */
    public function partners(): Collection
    {
        return CmsPartner::active()->with('media')->get()->map(fn ($partner) => [
            'name' => $partner->partner_name,
            'logo' => $partner->media ? self::mediaUrl($partner->media->file_path) : '',
            'url'  => $partner->website_url,
        ]);
    }

    public static function mediaUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        // URL relatif (/storage/...) agar selalu mengikuti host yang sedang dibuka
        // (meditech.local / app.meditech.local), bukan APP_URL.
        return str_starts_with($path, 'data:') || str_starts_with($path, 'http')
            ? $path
            : '/storage/' . ltrim($path, '/');
    }

    private function build(): array
    {
        $result = ['_visible' => array_fill_keys(array_values(self::SLUG_KEYS), true)];
        foreach (self::SLUG_KEYS as $key) {
            $result[$key] = [];
        }

        $pages = CmsPage::whereNull('deleted_at')
            ->whereIn('slug', array_keys(self::SLUG_KEYS))
            ->get()
            ->keyBy('slug');

        $published = [];
        foreach ($pages as $slug => $page) {
            $key = self::SLUG_KEYS[$slug];
            if ($page->status === 'Published') {
                $published[$page->page_id] = $key;
            } else {
                $result['_visible'][$key] = false;
            }
        }

        if (!$published) {
            return $result;
        }

        $pageIds = array_keys($published);

        $fieldsByPage = CmsField::whereIn('page_id', $pageIds)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('page_id');

        $itemsByPage = CmsItem::whereIn('page_id', $pageIds)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('page_id');

        // 1) Decode nilai dan kumpulkan semua media_id agar cukup satu query media.
        $decoded = [];
        $mediaIds = [];

        foreach ($published as $pageId => $key) {
            foreach ($fieldsByPage->get($pageId, collect()) as $field) {
                if (!empty($field->media_id)) {
                    $decoded[$key][$field->field_key] = ['__media' => (int) $field->media_id];
                    $mediaIds[] = (int) $field->media_id;
                    continue;
                }

                $value = $field->field_value;
                if (is_string($value) && preg_match('/^\s*[\[{]/', $value)) {
                    $json = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $json;
                    }
                }

                if (is_array($value)) {
                    foreach ($value as $row) {
                        if (is_array($row) && isset($row['img']) && is_numeric($row['img'])) {
                            $mediaIds[] = (int) $row['img'];
                        }
                    }
                }

                $decoded[$key][$field->field_key] = $value;
            }

            foreach ($itemsByPage->get($pageId, collect()) as $item) {
                if (!empty($item->media_id)) {
                    $mediaIds[] = (int) $item->media_id;
                }
            }
        }

        $media = $mediaIds
            ? CmsMedia::whereIn('media_id', array_unique($mediaIds))->whereNull('deleted_at')->get()->keyBy('media_id')
            : collect();

        $url = fn ($id) => ($id && $media->has((int) $id)) ? self::mediaUrl($media->get((int) $id)->file_path) : '';

        // 2) Susun konten per halaman.
        foreach ($published as $pageId => $key) {
            $content = [];

            foreach ($decoded[$key] ?? [] as $fieldKey => $value) {
                if (is_array($value) && array_key_exists('__media', $value)) {
                    $content[$fieldKey] = $url($value['__media']);
                    continue;
                }

                if (is_array($value)) {
                    $value = array_values(array_map(function ($row) use ($url) {
                        if (is_array($row) && array_key_exists('img', $row)) {
                            $row['img'] = is_numeric($row['img']) ? $url($row['img']) : (string) $row['img'];
                        }
                        return $row;
                    }, $value));
                }

                $content[$fieldKey] = $value;
            }

            // Cadangan: list yang tersimpan sebagai baris cms_items.
            foreach ($itemsByPage->get($pageId, collect())->groupBy('section_key') as $section => $rows) {
                if (!empty($content[$section]) && is_array($content[$section])) {
                    continue;
                }

                $content[$section] = $rows->map(fn ($item) => [
                    'tag'   => $item->subtitle ?? '',
                    'title' => $item->title ?? '',
                    'meta'  => $item->meta ?? '',
                    'name'  => $item->title ?? '',
                    'role'  => $item->subtitle ?? '',
                    'quote' => $item->description ?? '',
                    'text'  => $item->title ?? '',
                    'desc'  => $item->description ?? '',
                    'value' => $item->item_value ?? '',
                    'label' => $item->subtitle ?? '',
                    'icon'  => $item->meta ?? '',
                    'img'   => $url($item->media_id),
                ])->values()->all();
            }

            $result[$key] = $content;
        }

        return $result;
    }
}