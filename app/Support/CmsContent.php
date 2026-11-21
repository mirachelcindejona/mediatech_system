<?php

namespace App\Support;

use App\Models\CMS\CmsField;
use App\Models\CMS\CmsItem;
use App\Models\CMS\CmsMedia;
use App\Models\CMS\CmsPage;
use App\Models\CMS\CmsPartner;
use Illuminate\Support\Facades\Storage;

/**
 * Membaca konten company profile dari tabel CMS (cms_pages, cms_fields, cms_items, cms_media, cms_partners).
 *
 * - Hanya halaman berstatus "Published" yang dibaca. Halaman Draft dianggap kosong,
 *   sehingga view memakai teks bawaan (fallback).
 * - Key mengikuti skema CmsController::PAGES (heroTitle, services, hwImg, dst).
 * - Field gambar & item list yang berisi media_id diubah menjadi URL.
 */
class CmsContent
{
    /** @var array<string, array>|null */
    private static ?array $pages = null;

    /** Konten satu halaman (slug cms_pages) sebagai array; [] jika belum dipublikasikan. */
    public static function page(string $slug): array
    {
        return self::load()[$slug] ?? [];
    }

    /** Teks skalar; memakai $default bila kosong. */
    public static function t(array $page, string $key, string $default = ''): string
    {
        $v = $page[$key] ?? null;

        return is_scalar($v) && trim((string) $v) !== '' ? (string) $v : $default;
    }

    /** List (array of array); memakai $default bila kosong. */
    public static function l(array $page, string $key, array $default = []): array
    {
        $v = $page[$key] ?? null;

        return is_array($v) && count($v) ? array_values($v) : $default;
    }

    /** Tautan tombol hanya boleh anchor pada halaman yang sama (#kontak, #produk, ...). */
    public static function link(?string $value, string $default = '#kontak'): string
    {
        return $value && preg_match('/^#[A-Za-z0-9_-]*$/', $value) ? $value : $default;
    }

    /** Partner aktif untuk section "Partner & brand", urut display_order. */
    public static function partners()
    {
        return CmsPartner::active()->with('media')->get();
    }

    public static function mediaUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        return str_starts_with($path, 'data:') || str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    }

    private static function load(): array
    {
        if (self::$pages !== null) {
            return self::$pages;
        }

        $pages = CmsPage::published()->get();
        $ids = $pages->pluck('page_id');

        $media = CmsMedia::whereNull('deleted_at')->get()->keyBy('media_id');
        $url = fn ($id) => ($id && $media->has($id)) ? self::mediaUrl($media->get($id)->file_path) : '';

        $fields = CmsField::whereIn('page_id', $ids)->whereNull('deleted_at')->get()->groupBy('page_id');
        $items = CmsItem::whereIn('page_id', $ids)->active()->orderBy('sort_order')->get()->groupBy('page_id');

        $result = [];

        foreach ($pages as $page) {
            $content = [];

            foreach ($fields->get($page->page_id, collect()) as $field) {
                if (!empty($field->media_id)) {
                    $content[$field->field_key] = $url($field->media_id);
                    continue;
                }

                $value = $field->field_value ?? '';
                if (is_string($value) && $value !== '' && in_array($value[0], ['[', '{'], true)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }

                if (is_array($value)) {
                    array_walk_recursive($value, function (&$node, $key) use ($url) {
                        if ($key === 'img' && is_numeric($node)) {
                            $node = $url((int) $node);
                        }
                    });
                }

                $content[$field->field_key] = $value;
            }

            // Cadangan: data list lama yang tersimpan di cms_items.
            foreach ($items->get($page->page_id, collect())->groupBy('section_key') as $section => $rows) {
                if (!empty($content[$section]) && is_array($content[$section])) {
                    continue;
                }

                $content[$section] = $rows->map(fn ($i) => [
                    'tag'   => $i->subtitle ?? '',
                    'title' => $i->title ?? '',
                    'meta'  => $i->meta ?? '',
                    'name'  => $i->title ?? '',
                    'role'  => $i->subtitle ?? '',
                    'quote' => $i->description ?? '',
                    'text'  => $i->title ?? '',
                    'desc'  => $i->description ?? '',
                    'value' => $i->item_value ?? '',
                    'label' => $i->subtitle ?? '',
                    'icon'  => $i->meta ?? '',
                    'img'   => $url($i->media_id),
                ])->all();
            }

            $result[$page->slug] = $content;
        }

        return self::$pages = $result;
    }
}