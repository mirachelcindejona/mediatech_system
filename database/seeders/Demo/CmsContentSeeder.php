<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Support\SeederSupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsContentSeeder extends Seeder
{
    use SeederSupport;

    public function run(): void
    {
        if (!$this->isDemoEnvironment()) return;

        $adminId = $this->userId('anaqi@meditech.co.id');
        $now = now();

        // Ensure storage directory exists
        Storage::disk('public')->makeDirectory('cms');

        // ============================================
        // 1. CMS PAGES
        // ============================================
        $pages = [
            ['slug' => 'home', 'title' => 'Beranda', 'status' => 'Published'],
            ['slug' => 'about-us', 'title' => 'Tentang Perusahaan', 'status' => 'Published'],
            ['slug' => 'visi-misi', 'title' => 'Visi & Misi', 'status' => 'Published'],
            ['slug' => 'produk-layanan', 'title' => 'Produk & Layanan', 'status' => 'Published'],
            ['slug' => 'proyek-terbaru', 'title' => 'Proyek Terbaru', 'status' => 'Published'],
            ['slug' => 'sektor-pelanggan', 'title' => 'Sektor Pelanggan', 'status' => 'Published'],
            ['slug' => 'testimoni', 'title' => 'Testimoni', 'status' => 'Published'],
            ['slug' => 'partner-brand', 'title' => 'Partner & Brand', 'status' => 'Published'],
            ['slug' => 'kontak', 'title' => 'Kontak & Footer', 'status' => 'Published'],
        ];

        foreach ($pages as $page) {
            DB::table('cms_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                ['title' => $page['title'], 'status' => $page['status'], 'updated_by' => $adminId, 'updated_at' => $now, 'deleted_at' => null, 'deleted_by' => null, 'deletion_reason' => null]
            );
        }

        // Section yang sudah dihapus dari company profile ("Cara kerja" dan "Mengapa memilih kami").
        $removedAt = ['deleted_at' => $now, 'deleted_by' => $adminId, 'deletion_reason' => 'Section dihapus dari company profile'];
        DB::table('cms_pages')->where('slug', 'cara-kerja')->update($removedAt + ['status' => 'Draft']);
        foreach (['home' => ['whyTitle', 'why'], 'about-us' => ['founded']] as $slug => $keys) {
            $removedPageId = (int) DB::table('cms_pages')->where('slug', $slug)->value('page_id');
            DB::table('cms_fields')->where('page_id', $removedPageId)->whereIn('field_key', $keys)->update($removedAt);
        }

        // ============================================
        // 2. CMS MEDIA - Save base64 images as files
        // ============================================
        $mediaData = [
            // gedung - building image
            [
                'file_name' => 'gedung.jpg',
                'base64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAE0AWADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDx/VLqCfw9pNqpJkiLK6lTUOuWEWh6zCsBLQsqyYcbtoOeMd+lV7q48+0sVG7K5xuYEdsYrcjuJL/xhpv2qKMN5YRlVgyn5Wr1naX4HnttL7yDwnPKdZup7WR0zFKf3UW4EbT27Cvon4Rr/wAW90b3iP8A6Ea+fdE04y+IdXFtHAIreKdtj5wAFbpivob4UDHw90T/AK4Z/wDHjT2Vgb1Z1u0UmKdSUhCZxS9BRgUnUCgB3WkoA9KKAFxRRmnUAMIoA4xTzSjHpQIaBS4p46UAZpARNUeCaslRTCtO4EYHrSEelKQc0Y4pgNFBFLjHNB560BYbQRTqKAsMop5HFC0XAbikxTjSUCYlFOxSYpisJRQRS4oASnRg7+OuD/KmnrT4f9YPof5UnsaLchYsT05rk/iiC3w/1zj/AJd2rrJAN4y1cv8AEiJpvA2sxRKzyPbsAB3NcEeljtTs3c8L8clf7Vstyk5060+6uf8AlkK6azwLrw3gHB0C8GD+NYfje0uItZs4HhkEy6daqygZwRGAR+Yrr7fRb1J9BaWIRmDRLsOrMMjd0/mK7pfAjggn7aTWhwnw8J/4S/SMxkfvAMk+1TaypPg/TguPl1G8H/jwq98NNGvbnxTYOsaIsJ8xizjoPSrfibRZbXwppqLKrGTULqTkEYBP/wBaqk/eREItU5Kxm6fu/wCFf3G4LldUtTx9azvGSsPGOsANgfa3PT3ru9F8MKfAsa3VxlbrUIGOxcFdrD1rD13REvfH+oxSTOqPd4IUDPOKSa5mxyhJ0Yova6CdQ8SD+94ftz+orp/2afGk2nas3hm9kZ9Put0sDMf9TIBk/wDASB+ePU0eJ9ItIR4lZATKulRQByxzsH6VjfCzRbJJry68tmnjgOxy5OMgg8dKiSjKLUjR8ylFroeTa3pkOn2Ol3VsGD3EKs69RuwpyPrmo7a5a71iF1eRXUABkUIwIHpwKt6jdTTQ6NDNHGvlYVSrZyMKBn04FT+J7KSTxcYbCKMylFcIRgMfLyfxq2tbryBO6sy/4PuY4NQ183MoWR7WcKXIBY7W/CvoH4WjHgDRP+vcfzNfNPh3eG1VJIiZVt5Sw8pX2/Kc8k8fUV9NfDRdvgLRB/07L/Wi90Jq1z...',
                'media_type' => 'image/jpeg',
                'alt_text' => 'Gedung Meditech',
                'file_size_kb' => 150,
                'width_px' => 1200,
                'height_px' => 800,
            ],
            // staff - team/staff image
            [
                'file_name' => 'staff.jpg',
                'base64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAcFBQYFBAcGBgYIBwcICxILCwoKCxYPEA0SGhYbGhkWGRgcICgiHB4mHhgZIzAkJiorLS4tGyIyNTEsNSgsLSz/2wBDAQcICAsJCxULCxUsHRkdLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCwsLCz/wAARCAIXAyADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD2f+2L7/n4P/fI/wAKX+1r7/n4P/fI/wAKzwaXdUDLx1e+/wCfg/8AfI/wpp1i/wD+fg/98r/hVJmpuaALx1nUP+fk/wDfK/4Uxtb1EdLk/wDfK/4VSJpjHik2xlttd1If8vR/74X/AAp0Ouak5+a6J/4Cv+FZbGpLc80k2VZG+uqXpHM5/wC+R/hTxqV3n/XH/vkf4VRj+6KkFUIvR6hdFsGU/kKuR3UzDl/0FZUf36vxdKaEy0biT+/+lNNxL/f/AEFMppp3JH/aZsff/QUn2qb+/wDoKjzTSaVwJDdz/wDPT9BR9sn/AOeh/IVCaSgZL9suP+eh/IUn2y4/56H8hUNFAE32y4/56H8hS/bLj/nofyFV6WgCf7Zcf89P0FJ9suP+en6CoaKAJTe3A/5afoKqXeqXkcZKTEH/AHR/hUjVQvf9UaTZUUcL4w8c+JNMgZrPU2hI9Ioz/Na4aT4s+NwmRrz/APgPD/8AEVtfED/j2evNJD8lTBsqaS2Oil+MPjxWAHiGQf8AbvD/APEVds/iz44kZQ+vOQT/AM+8P/xFeez/AOsq/Ynla0Mj33QvGut3UCG41AyMep8tB/IV1MPiC8ZMtc5P+6P8K8e0C9McKgmuoj1banWjlZPMd8Neuj/y3/Qf4Uo165/57/oP8K4Ma1704azx96jlY+ZHeDXLj/nv+g/wpf7buP8Anv8AoP8ACuEGs+9O/tn3o5WPmR3X9t3H/Pb9B/hS/wBt3H/Pb9BXC/2z/tfrR/bP+1Ssw5kd1/bVx/z2/Qf4Uf21cf8APb9B/hXDf21/tUf2170rMOZHdf21P/z2/QUf2zcf89v0FcL/AG3/ALVH9t+9LUd0d3/bVx/z1/QU4axOf+W36CuC/tz/AGqkXXR/eo1HdHdf2tP/AM9v0FH9rz/89f0FcQNYIHWg6yfX9aNQujtv7Ym/56/oKX+2Jsf639BXD/wBsn1/Wj+2G9aeoaHb/ANszf89f0FA1mY/8tf0FcMdXY96VdY...',
                'media_type' => 'image/jpeg',
                'alt_text' => 'Tim Meditech',
                'file_size_kb' => 120,
                'width_px' => 800,
                'height_px' => 600,
            ],
            // p1 - product/service 1
            [
                'file_name' => 'p1.jpg',
                'base64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAB9AO0DASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3nVvHun2GjadqSQy3EN4SAEIyhA5B96ntvGMM/iwaEtrIJCgfzSwx9zd0/GvF/E2m3mhvFp0zs1m5FzAex3AD8+MH6V1WqXg8N/Ei31S/ikNrJAjKUGcgxhePxFeIsdV5ve0SaT/U+snk+H5F7P3nKMmtd7Wt8zsf+FgWwvNZgeymH9mhy7Bgd+1wvH51RtvitpUkqLNZ3cMbHHmEAgfka4jTxJqEHjLV44nW0ljcqWHdpAwH1xWPbm5ms9IstRnMOjzTFkdUB2ndhjn/AD1rOWPrqzT38vOxvTybCPmUlqrJ6v8Alu7b3fket+JPiHZ6VfrZ2lrLfS7BIxjOAARn8eOaVviJYDw4urLbTkecIHh4DK2M/iK4jx/b6VZa7GlvJe2N5FCgScDMcgC/KQQc9sZqjqOq3+qfDvdqA3CK+RI5tuC42NnPrj1960nja0ZzTe17GNLKsNUpUpqL1au22nr+H3ao9P8ACnjWHxDfm1isLqDEZk3yAbSMj/Gm+LPHmn+HrwWZilurvALRx4G3PTJ9a574VXUT3oiXXLi9f7MP9FdGCxYx0JOOOlZOoXSeGPijd3+s28kttLuaNwu7hgMEZ9OlbPFVFQjK+rdm+33HKsvoPGTp8rtGN1G7Tb+aueg+EPGFh4mWRbYPDcRDLQydceo9RWbqnxF0/T/EL6XJBK2yRY3mBG0E4z+WayfD3ifTZpdW1Sy0GOzitoXdrsYBck8LwOp+teamG5vNLvLyTT7maSWXzvtgB2qBncOnqf0rOrjqkaceR3eutuiN8LlFGpWn7WLjFWSTa0b8/Ltue4+MPFtv4ZFoZ7eScXBIGwgYxj1+tS+LvE8PhvTbe8ngkmWaQRhUIBHBPf6V5R4z1Yat4U8NXDtmWPfFL/vLtH8sH8a2fibr2n654VtG02fzRDdKr/KRglG9auWOdqji9kmjGnlEU6CnF6uSlv0dl6HrFjcC7s4LhVKrKiuAe2Rmp65rwT4g07VdPgtbGfzJ7aBBKu0jbxjv710tenTmpxUk7ng16UqNRwkrW7hVOb/WtVyqtwhDFuxNWYkYp1VYpHn+ZDti7Hu1TCLjiR8+uaAJaSR1jQu5AUDJJqNXZHCS4+b7rDofb2NQ3v7y6tYD...',
                'media_type' => 'image/jpeg',
                'alt_text' => 'Jaringan gas medis & nurse call',
                'file_size_kb' => 100,
                'width_px' => 600,
                'height_px' => 400,
            ],
            // p2 - product/service 2
            [
                'file_name' => 'p2.jpg',
                'base64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAB4AOcDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDw27m+zW/nOrFSdowOCfTNVYPMuI2uJThF+6nYmvV/iFoUd18OtMl0a1zBA29goycHqx9TXA6daq42EApBHvb69v1rHC5nDGYedfZRbVuunf1KxNCVGoqfcpeEvD0niW8ZpdywqcZFReLNLbRdTFjHKJQVDZHX8a9Z8KpbaL4feRgqErkn9a4O226rqGoalcglGPy5H5D8q8XC4+rXxM5fYWlvM76eB9olFbnGST/ZGVUAL4ycioze7s+Yv4itMWsNxqbhlASTIX29KyZrbylcNnIOB9a9y557jZtCjjTwx4O8Ee9bXhG4MPie1KnhyFPuCMf4ViXYMcEEZGD96pbWd7W9E8RIeEBgfcc1ZB7wqjYPpVWWNT2rz6z+IFwIwLqBZCOrL8pq+vja0nIG5o/XcKkLHcW8IELmuAKJoviKaG5QHTdQz94fKCeo/M/ka3PDniqHU5ZLRozFIwJjJbO8fTtWhq+mQappb29wMEnKOOqN2Iq0rkvQ4HXPDcllcM9irT2x+YKOXX6eorHS4kSRSssm5OBnqvtzXX6PqMmn3X9nauQJI+I5M/Kw7VW+INlBFdw6nb7GjfCTxjuccMP8+lTvuNHOKVZApkXbnptxj86ndSbKKSGRfMZyjpuBYDHB+lUIJY8KJCVO7BIPGD0NaVtDHHLKt+r7Y2KNsfpkfK3uOh+lMZFpKy2zSozGMP8AwoQST9ew/Wukitlt7SFVhLvOdwaXiPA/jIPLAfl9TxWPbWyrDZ3SzrFdQzfvYGXKkDBDjtjtXsvws0ZPEc3/AAkerXEV3Ir4jjHIDD+Jh2xxgfj6YfMoLmJtzOxN4e8CIdDe/wBcmkgjCtOQ/DAYyZH9GI7dhx1zWX8LPD9x4g1i41MEpFE/mhpssSTxGnvhQD7Guo+MuteVp1v4dsiWvNRIMqpywiz0+rNgD8a77wVokPhjwxBaysivGhluZM4BcjLHPoOmfQVm2+Vye7NFFXsuh5f8ZLW4Mmg6CHillu7jz5FTIPlp6/XJ/EVU0tbNPilqV9cxxSaf4ftNrOVBUtDGWI9zv4/CqHiHxOj65qHiy4w0xIh0uB+wH+ryPTP7w/8AAR/FUGkadJZeH7ewuGY32ssJ7gOeRArbizf7zAAe...',
                'media_type' => 'image/jpeg',
                'alt_text' => 'Mekanikal listrik tegangan rendah',
                'file_size_kb' => 100,
                'width_px' => 600,
                'height_px' => 400,
            ],
            // p3 - product/service 3
            [
                'file_name' => 'p3.jpg',
                'base64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCACAAO0DASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDxKiiivTJCiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKlgt5rg4hieQ/wCyM00r7CbtuRUVrw6Dcld9y8Vuncu3P+fxqX7NpFoMzTSXLDsOF/z+NbRoTl0sYSxNOPW5hgFiAoJJ7CrqaXevE0gt3CqMndwfyNaTassERNjawwjGc4yTVfVjqEcNnJeXMbJdxGVY43yVXtuA6Gtfq8V8TMHi237qMiimQ/6pPoKfXGd4UUUUAFFFFABRVux065vW/cREr3c8KPxrstL8FJDDHc6tKkUTDcpmyocf7Kj5nHuBj1IrnrYqnR+J6nbhcur4lrkWnd/1r8jgqK7zxHbaNDpN2bDTw0qxlhcSkq2R3VFOF/Et+FcXb2N1c209xBbySQQAGV1XIT61NDGU60XLa2mpeOy2tgpqE9W1fTsV6KKK6jzwooooAKKKKACiiigAooooAKKKKACiiigC5pGmXmsahDY6bbvcXUpwkad/f2Hua1P9P0calps0uJbO68lgp3BWG8NtPplR+Ven/s3aWf8AicarJHx8ltE5H1ZwP/HK858ZNs8T+LGxnGqSH/x6WurDqzuefiZ8z5OxzU1zPPcMHkZjkjJP9ailQnAB3N3wOf5U5tudxABbcePm5qeJYnWZZmdPvCNxyucjgjsMZ5A/+t0tnPYkWN3spJERmjjXDsoyF+p7VRtAZrlI0BLNwueM5yK7zw7oN7qHhbVzDcGOJVaQPEf3cu1eUIBwfrjjHvXH6bLNc6lp+9RiELEuwdge/vzWFKs6k5QdvdfT0W5TcLLkd319Tcvfh74r02L/AEnQ7whByYQJR/44TXNOjRuyOpV1OCpGCDX3B3rxD9oyz0SK2sbsNFHrskoTYmN0sWDlmHsQME+uPpzONkdVLEucuVo8NoorW8J6M/iHxHYaVG/lm5k2s+M7VAyx/AA1J1t2V2ZNdP4Z0aKfZNdKHV8bAeg5I59a+m/D3hLRPD9okGm6fAu0YaV0DSOfVmPJ/l6Yrynxgix+M75UUKomXAAwBwK5cdzQotpnTk1SNfGRg1prv6MrtNDZa3Lo2jW6LdQu0f2i...',
                'media_type' => 'image/jpeg',
                'alt_text' => 'Jaringan networking CCTV access door',
                'file_size_kb' => 100,
                'width_px' => 600,
                'height_px' => 400,
            ],
            // p4 - product/service 4
            [
                'file_name' => 'p4.jpg',
                'base64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAB5APADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDRijDHJBxVhICclQSR29qbENvNa+nXCs4Rhkda+jnJrY8KEU9ypHC8q8gg44yKrxwvbWq7bhmKXLsoKhljIA9O/J61u6lew24BkHyk46cAdyax9LtIP7RktrORIo5y0xAO7oME7Sfp0x1rgqNVtJLRHXC9PWO7My6soJlIkiVhnjIBHf8ADNY+m2N5pWpuVublrV0by1cKVRu3A7D8PSuo1SM2F7LCyu6pjLIhOcjPQZNQabcWM11HKs0bjZIAA4K52HqPqPzFVWV4Xjv9xNJ2laW33lKfT9Yu4RClvbP5RLsZJECJkDGQWBYkdhxVCHwsIby2aa5gubuZmknn8+LeQOqRqGypHGe+SADxW4mq+Xd3bxIzE4YYGeAo554H4/pUNjCn2v7fLDGYt6PHHCDvlbnCN/sk9B6+vWvLrXUkd9K3Kcdrq+INNi8y1ga7gRci45WSJT367sHB4OehzWZoWo3Wp6fAbbI67pSu3Jyc5z1HIPBPSu3sb7VrHXLmDWoonjKtcRxpgMcADg9CCMdOnfHenf6FpM88f2CaXTraSQSPbnC7ztJ+TtjHcH86Tu9ZblJJbEHhTSLgS2qyXB+ziP8AeTjbtSUbRgKOnQ+uc1pXKvaPMf3Mly5CLIy5CZy2ccEEgnGOM/Q1Vm8LXKsk1krsFYHy2IYMR91sjvgE569apPqsS3loL1HjuUmMUqcEBRgZ5Py8D7pHORzxWcm1si0ru9z13wtaJp2hx2sdxBMFxnyhgKSoOD74IP41h6joVw2o6xfoQLbET7cfxYIbGO5+X8jWvHrWmIsc+YrdJkU/KMb2yedo+6cYyKS4uLJ57m4N2Aktm0Ufy/I7Zzjd2PHQ9c+1dOHrcri+tznr0+bmXQ5g26f2eJdvziUqT7YyP61W8v2rSkkii0qVWOX81GVFGWIww4H5VLa+HdUvoEnlMdpbyIGjjDbpHz/eI4X6DJ9xXtyxMafxM8dYaU9UYxcB9q/M4HKjt9fSpFMpBBchf7q9KmNsluXjVAhViCB696Zg5rVa6szfu6IjMWOlPt7fzZlUsFGeSe1XLaPzF2HjvxWxo+iySuJGIWPd3HJrOpWUE22b0sO5tWRDa6RbOmGy+71GD+FZ2vaAdP2yQEyR...',
                'media_type' => 'image/jpeg',
                'alt_text' => 'Jaringan telekomunikasi',
                'file_size_kb' => 100,
                'width_px' => 600,
                'height_px' => 400,
            ],
            // brands - hardware brands
            [
                'file_name' => 'brands.jpg',
                'base64' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCACXApYDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD6poorJ8Ua/Y+G9Il1DUpNsacKg+9I3ZVHrVRi5NRjuTOcYRcpOyRe1C+tdOtJLq+njt7eMZaSQ4Arxzxf8ZiHe38MW4IHH2qdev8Aur/j+VchqOpeIvih4gW3to2ECnKQKT5UC/3mPr7/AJV6/wCCfhrpHhyOOa4jW+1EcmaVcqh/2V7fXrXqKhQwa5q/vS7f5nhvFYnHyccL7sP5v8v6+48ih0Lx142YT3AvJIG5D3L+XGPop7fQV0Fj8D9QdQb7V7WE9xFE0n6kiveOnSis5ZpV2ppRXkjaGSUN6rcn5s8Vb4GDb8uvHd72v/2VY+o/BXXLcF9PvrO6x0BLRsf5j9a+gqKmOaYlbyv8kXLJcJJaRt82fM8er+OvAcypdm8itwcCO5Hmwt7A8j8iK9N8FfFnS9aeO11dV029bgMzZic+zfw/Q/nXo9xBFcwvDcRJLE4wyOoYEe4NeRePvhFBPHJfeFgIZxlmsyfkf/cJ+6fbp9K2WIw+K92tHll3RzywuLwPvYeXPH+V/p/wD2EHIyOlFfPfw5+Id74ZvRo3iPzjYK3l/vQfMtj+PO327dq+gYZY54UlhdZInUMrKchgehFcWJws8NK0tnsz0cHjaeLhzR0a3XYfRRRXMdgU2aVIYnlmdUjRSzMxwAB1Jp1eS/tF+JX0nwrDpVq+241NirkHkRLjd+ZIH0zXTg8NLFVo0Y9f6ZlXqqjTc30PMPi58TLrxTey6dpcrw6HG20BTg3BH8Te3oK6f9mPQN91qevTJ8sYFrAT6nlz+W0fjXhQBJAHJNfX3g+2sPAXw0sV1i4WzjSMPcykHiSQ89ATnJA/Cvsc3UMFglhcOtZu3m+/q3t8zw8E5Yiu61R7anz/APHDxANe8f3nlPutbIC0iwePl+8f++i35CuAr6RtPhL4K8TaPLf6DqF+4lLBLouSN46khlGRnrXlvwi8FweLfF09nqG9tPtImeZomxuOdqgH3Jz9Aa68FmGGp4eUY3SpJXurP/h2YV8NVlVTdnz7HC2txNaXEc9tK8M8bBkkRsMpHcGvqD4LfEg+KrU6Xq7qNZgTIfoLhB/F/vDuPxrDvfAXwxsvEMeh3N3eLqsjIq24ldjlvujIXHcVhfEvwhZfDK90Tx...',
                'media_type' => 'image/jpeg',
                'alt_text' => 'Brand mitra hardware',
                'file_size_kb' => 120,
                'width_px' => 800,
                'height_px' => 400,
            ],
        ];

        $mediaIds = [];
        foreach ($mediaData as $media) {
            $existing = DB::table('cms_media')->where('file_name', $media['file_name'])->first();
            
            // Save base64 as file
            $base64 = $media['base64'];
            $binary = base64_decode($base64);
            $filePath = 'cms/' . $media['file_name'];
            
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            Storage::disk('public')->put($filePath, $binary);
            
            $data = [
                'file_name' => $media['file_name'],
                'file_path' => $filePath,
                'media_type' => $media['media_type'],
                'alt_text' => $media['alt_text'],
                'file_size_kb' => $media['file_size_kb'],
                'width_px' => $media['width_px'],
                'height_px' => $media['height_px'],
                'uploaded_by' => $adminId,
                'uploaded_at' => $now,
                'deleted_at' => null,
                'deleted_by' => null,
                'deletion_reason' => null,
            ];
            
            if ($existing) {
                DB::table('cms_media')->where('media_id', $existing->media_id)->update($data);
                $mediaIds[$media['file_name']] = $existing->media_id;
            } else {
                $id = DB::table('cms_media')->insertGetId($data);
                $mediaIds[$media['file_name']] = $id;
            }
        }

        // ============================================
        // 3. CMS FIELDS (Key-value pairs per page)
        // ============================================
        $pageFields = [
            'home' => [
                'heroImg' => $mediaIds['gedung.jpg'],
                'heroTitle' => 'Mitra instalasi dan perlengkapan teknologi Anda',
                'heroSub' => 'Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, dan komputer.',
                'ctaLabel' => 'Minta penawaran',
                'ctaLink' => '#kontak',
                'cta2Label' => 'Lihat produk',
                'cta2Link' => '#produk',
                'stats' => json_encode([
                    ['value' => '150+', 'label' => 'Proyek terselesaikan'],
                    ['value' => '60+', 'label' => 'Klien yang percaya'],
                    ['value' => '25+', 'label' => 'Tim teknisi profesional'],
                    ['value' => '15+', 'label' => 'Brand mitra resmi'],
                ]),
                'ctaTitle' => 'Butuh instalasi atau perangkat baru?',
                'ctaSub' => 'Ceritakan kebutuhan Anda, tim kami siap menyiapkan penawaran.',
                'ctaBtnLabel' => 'Hubungi kami',
                'ctaBtnLink' => '#kontak',
                'heroImgAlt' => 'Gedung perkantoran modern',
                'seoTitle' => 'PT Meditech Cipta Inovasi',
                'seoDesc' => 'Jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, dan komputer.',
            ],
            'about-us' => [
                'img' => $mediaIds['staff.jpg'],
                'body' => 'PT Meditech Cipta Inovasi didirikan pada 2 Juni 2025 sebagai perusahaan yang bergerak di bidang jasa konstruksi dan instalasi, serta perdagangan perlengkapan alat kesehatan, elektrikal, elektronik, mesin kantor, komputer, dan lainnya.',
                'body2' => 'Kami berkomitmen memberikan pelayanan terbaik, dengan ketepatan waktu dan kerja sama untuk melayani semua kebutuhan pelanggan.',
                'quote' => 'Great things are never done by one person. They\'re done by a team.',
                'title' => 'Tentang perusahaan',
                'imgAlt' => 'Staf tersenyum sambil memeriksa dokumen',
            ],
            'visi-misi' => [
                'visi' => 'Menjadi perusahaan yang memiliki integritas, mitra terpercaya, profesional, competitive dengan pelayanan purna jual terbaik.',
                'misi' => json_encode([
                    ['text' => 'Menjadi perusahaan yang memiliki integritas, mitra terpercaya, profesional, competitive dengan pelayanan purna jual terbaik.'],
                    ['text' => 'Menjalin kerja sama dengan jaminan purna jual.'],
                    ['text' => 'Memberikan kualitas pekerjaan sesuai standarisasi pekerjaan.'],
                    ['text' => 'Mengedepankan profesionalisme.'],
                    ['text' => 'Menjadikan pelanggan sebagai mitra bisnis, yang harus dijaga hubungan baiknya untuk kelangsungan kerja samanya.'],
                ]),
            ],
            'produk-layanan' => [
                'title' => 'Produk & layanan',
                'intro' => 'Instalasi jasa untuk fasilitas kesehatan dan perkantoran, ditambah perangkat keras dan layanan perawatan.',
                'serviceTag' => 'Instalasi jasa',
                'hwImgAlt' => 'Logo brand perangkat keras',
                'services' => json_encode([
                    ['img' => $mediaIds['p1.jpg'], 'title' => 'Jaringan gas medis & nurse call'],
                    ['img' => $mediaIds['p2.jpg'], 'title' => 'Mekanikal listrik tegangan rendah'],
                    ['img' => $mediaIds['p3.jpg'], 'title' => 'Jaringan networking (CCTV, access door, dll)'],
                    ['img' => $mediaIds['p4.jpg'], 'title' => 'Jaringan telekomunikasi'],
                ]),
                'hwTitle' => 'Hardware',
                'hwImg' => $mediaIds['brands.jpg'],
                'hardware' => json_encode([
                    ['title' => 'Paket hardware', 'desc' => 'PC, laptop, notebook, dan server (HP, Dell, Lenovo, Asus, Epson, Acer, MSI, dll).'],
                    ['title' => 'CCTV', 'desc' => 'Hikvision, Dahua, Uniview, dll.'],
                    ['title' => 'Printer', 'desc' => 'HP, Epson, Canon, dll.'],
                    ['title' => 'Projector', 'desc' => 'InFocus, Epson, NEC, dll.'],
                ]),
                'mtTitle' => 'Maintenance',
                'mtDesc' => 'Layanan perawatan berkala agar perangkat dan instalasi tetap berfungsi optimal.',
            ],
            'proyek-terbaru' => [
                'title' => 'Proyek terbaru',
                'intro' => 'Beberapa pekerjaan yang telah kami selesaikan.',
                'items' => json_encode([
                    ['tag' => 'Gas medis & nurse call', 'title' => 'Instalasi ruang rawat inap', 'meta' => 'RS Contoh Sehat · Tangerang · 2025'],
                    ['tag' => 'Networking', 'title' => 'Sistem CCTV dan access door', 'meta' => 'Gedung Perkantoran Contoh · Jakarta · 2025'],
                    ['tag' => 'Hardware', 'title' => 'Pengadaan laptop dan server', 'meta' => 'Universitas Contoh · Banten · 2026'],
                ]),
            ],
            'sektor-pelanggan' => [
                'title' => 'Dipercaya berbagai sektor',
                'intro' => 'Kami membangun kemitraan yang erat dengan pelanggan dalam mencapai tujuan mereka, terutama di bidang teknologi.',
                'sectors' => json_encode([
                    ['name' => 'Perbankan', 'icon' => 'bank'],
                    ['name' => 'Perusahaan oli & gas', 'icon' => 'oilgas'],
                    ['name' => 'Manufaktur', 'icon' => 'factory'],
                    ['name' => 'Perusahaan distribusi', 'icon' => 'logistics'],
                    ['name' => 'Rumah sakit', 'icon' => 'hospital'],
                    ['name' => 'Retail', 'icon' => 'retail'],
                    ['name' => 'Pendidikan', 'icon' => 'education'],
                ]),
            ],
            'testimoni' => [
                'title' => 'Kata pelanggan kami',
                'items' => json_encode([
                    ['name' => 'Dzaky Julian', 'role' => 'Manajer Fasilitas, RS Contoh Sehat', 'quote' => 'Instalasi selesai tepat waktu dan timnya responsif saat ada kendala.'],
                    ['name' => 'Rina Wulandari', 'role' => 'Kepala IT, PT Contoh Distribusi', 'quote' => 'Pengadaan perangkat sesuai spesifikasi dan layanan purna jualnya jelas.'],
                    ['name' => 'Andi Pratama', 'role' => 'General Affair, Gedung Contoh', 'quote' => 'Pemasangan CCTV rapi dan dokumentasinya lengkap.'],
                ]),
            ],
            'partner-brand' => [
                'title' => 'Brand yang bekerja sama dengan kami',
                'intro' => 'Kami memasok perangkat dari brand ternama dan mitra resmi.',
            ],
            'kontak' => [
                'title' => 'Hubungi kami',
                'intro' => 'Kirim pesan atau datang langsung ke kantor kami.',
                'companyName' => 'PT Meditech Cipta Inovasi',
                'siteName' => 'MediTech',
                'headerLogoAlt' => 'Logo Meditech',
                'copyright' => '© 2026 PT Meditech Cipta Inovasi',
                'address' => 'Jl. Neo Paloma II Blok S10 No 26 Cluster Garden Ville, Desa Ciakar, Kec. Panongan, Kab. Tangerang, Banten.',
                'phone' => '021 - 8908 - 8898',
                'email' => 'info@meditech.co.id',
                'hours' => 'Senin–Jumat, 08.00–17.00 WIB',
                'tagline' => 'Motivation Creative Innovation With Technology',
            ],
        ];

        foreach ($pageFields as $slug => $fields) {
            $pageId = (int) DB::table('cms_pages')->where('slug', $slug)->value('page_id');
            foreach ($fields as $key => $value) {
                $isMedia = is_int($value) && $key !== 'display_order';
                $data = [
                    'page_id' => $pageId,
                    'field_key' => $key,
                    'field_value' => $isMedia ? '' : $value,
                    'media_id' => $isMedia ? $value : null,
                    'media_aspect_ratio' => $isMedia ? '16:9' : '16:9',
                    'media_fit_mode' => $isMedia ? 'cover' : 'cover',
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_reason' => null,
                ];
                DB::table('cms_fields')->updateOrInsert(
                    ['page_id' => $pageId, 'field_key' => $key],
                    $data
                );
            }
        }

        // ============================================
        // 4. CMS ITEMS (List items for services, hardware, etc.)
        // ============================================
        $serviceItems = [
            ['page' => 'produk-layanan', 'section' => 'services', 'title' => 'Jaringan gas medis & nurse call', 'subtitle' => 'Instalasi jasa', 'description' => '', 'order' => 1, 'media' => $mediaIds['p1.jpg']],
            ['page' => 'produk-layanan', 'section' => 'services', 'title' => 'Mekanikal listrik tegangan rendah', 'subtitle' => 'Instalasi jasa', 'description' => '', 'order' => 2, 'media' => $mediaIds['p2.jpg']],
            ['page' => 'produk-layanan', 'section' => 'services', 'title' => 'Jaringan networking (CCTV, access door, dll)', 'subtitle' => 'Instalasi jasa', 'description' => '', 'order' => 3, 'media' => $mediaIds['p3.jpg']],
            ['page' => 'produk-layanan', 'section' => 'services', 'title' => 'Jaringan telekomunikasi', 'subtitle' => 'Instalasi jasa', 'description' => '', 'order' => 4, 'media' => $mediaIds['p4.jpg']],
        ];

        $hardwareItems = [
            ['page' => 'produk-layanan', 'section' => 'hardware', 'title' => 'Paket hardware', 'subtitle' => '', 'description' => 'PC, laptop, notebook, dan server (HP, Dell, Lenovo, Asus, Epson, Acer, MSI, dll).', 'order' => 1, 'media' => null],
            ['page' => 'produk-layanan', 'section' => 'hardware', 'title' => 'CCTV', 'subtitle' => '', 'description' => 'Hikvision, Dahua, Uniview, dll.', 'order' => 2, 'media' => null],
            ['page' => 'produk-layanan', 'section' => 'hardware', 'title' => 'Printer', 'subtitle' => '', 'description' => 'HP, Epson, Canon, dll.', 'order' => 3, 'media' => null],
            ['page' => 'produk-layanan', 'section' => 'hardware', 'title' => 'Projector', 'subtitle' => '', 'description' => 'InFocus, Epson, NEC, dll.', 'order' => 4, 'media' => null],
        ];

        $projectItems = [
            ['page' => 'proyek-terbaru', 'section' => 'items', 'title' => 'Instalasi ruang rawat inap', 'subtitle' => 'Gas medis & nurse call', 'description' => '', 'meta' => 'RS Contoh Sehat · Tangerang · 2025', 'order' => 1, 'media' => null],
            ['page' => 'proyek-terbaru', 'section' => 'items', 'title' => 'Sistem CCTV dan access door', 'subtitle' => 'Networking', 'description' => '', 'meta' => 'Gedung Perkantoran Contoh · Jakarta · 2025', 'order' => 2, 'media' => null],
            ['page' => 'proyek-terbaru', 'section' => 'items', 'title' => 'Pengadaan laptop dan server', 'subtitle' => 'Hardware', 'description' => '', 'meta' => 'Universitas Contoh · Banten · 2026', 'order' => 3, 'media' => null],
        ];

        $sectorItems = [
            ['page' => 'sektor-pelanggan', 'section' => 'sectors', 'title' => 'Perbankan', 'subtitle' => '', 'description' => '', 'meta' => 'bank', 'order' => 1],
            ['page' => 'sektor-pelanggan', 'section' => 'sectors', 'title' => 'Perusahaan oli & gas', 'subtitle' => '', 'description' => '', 'meta' => 'oilgas', 'order' => 2],
            ['page' => 'sektor-pelanggan', 'section' => 'sectors', 'title' => 'Manufaktur', 'subtitle' => '', 'description' => '', 'meta' => 'factory', 'order' => 3],
            ['page' => 'sektor-pelanggan', 'section' => 'sectors', 'title' => 'Perusahaan distribusi', 'subtitle' => '', 'description' => '', 'meta' => 'logistics', 'order' => 4],
            ['page' => 'sektor-pelanggan', 'section' => 'sectors', 'title' => 'Rumah sakit', 'subtitle' => '', 'description' => '', 'meta' => 'hospital', 'order' => 5],
            ['page' => 'sektor-pelanggan', 'section' => 'sectors', 'title' => 'Retail', 'subtitle' => '', 'description' => '', 'meta' => 'retail', 'order' => 6],
            ['page' => 'sektor-pelanggan', 'section' => 'sectors', 'title' => 'Pendidikan', 'subtitle' => '', 'description' => '', 'meta' => 'education', 'order' => 7],
        ];

        $testimoniItems = [
            ['page' => 'testimoni', 'section' => 'items', 'title' => 'Dzaky Julian', 'subtitle' => 'Manajer Fasilitas, RS Contoh Sehat', 'description' => 'Instalasi selesai tepat waktu dan timnya responsif saat ada kendala.', 'order' => 1],
            ['page' => 'testimoni', 'section' => 'items', 'title' => 'Rina Wulandari', 'subtitle' => 'Kepala IT, PT Contoh Distribusi', 'description' => 'Pengadaan perangkat sesuai spesifikasi dan layanan purna jualnya jelas.', 'order' => 2],
            ['page' => 'testimoni', 'section' => 'items', 'title' => 'Andi Pratama', 'subtitle' => 'General Affair, Gedung Contoh', 'description' => 'Pemasangan CCTV rapi dan dokumentasinya lengkap.', 'order' => 3],
        ];

        $allItems = array_merge($serviceItems, $hardwareItems, $projectItems, $sectorItems, $testimoniItems);

        foreach ($allItems as $item) {
            $pageId = (int) DB::table('cms_pages')->where('slug', $item['page'])->value('page_id');
            $mediaId = $item['media'] ?? null;
            $subtitle = $item['subtitle'] ?? '';
            $meta = $item['meta'] ?? '';
            $description = $item['description'] ?? '';
            $itemValue = $item['item_value'] ?? '';

            DB::table('cms_items')->updateOrInsert(
                ['page_id' => $pageId, 'section_key' => $item['section'], 'title' => $item['title']],
                [
                    'media_id' => $mediaId,
                    'subtitle' => $subtitle,
                    'item_value' => $itemValue,
                    'meta' => $meta,
                    'description' => $description,
                    'sort_order' => $item['order'],
                    'is_active' => true,
                    'media_aspect_ratio' => $mediaId ? '4:3' : '16:9',
                    'media_fit_mode' => $mediaId ? 'cover' : 'cover',
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_reason' => null,
                ]
            );
        }

        // ============================================
        // 5. CMS PARTNERS
        // ============================================
        $partners = [
            ['name' => 'Acer', 'color' => '#0f7a3c', 'category' => 'Hardware'],
            ['name' => 'Dell', 'color' => '#0b5cab', 'category' => 'Hardware'],
            ['name' => 'HP', 'color' => '#0096d6', 'category' => 'Printer'],
            ['name' => 'Lenovo', 'color' => '#c8102e', 'category' => 'Hardware'],
            ['name' => 'InFocus', 'color' => '#1d3a8a', 'category' => 'Projector'],
            ['name' => 'Canon', 'color' => '#b71c1c', 'category' => 'Printer'],
            ['name' => 'NEC', 'color' => '#2b3a8f', 'category' => 'Projector'],
            ['name' => 'Epson', 'color' => '#003f87', 'category' => 'Printer'],
        ];

        foreach ($partners as $index => $partner) {
            $logoPath = "data:image/svg+xml;utf8," . urlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="60" viewBox="0 0 200 60">' .
                '<rect width="200" height="60" fill="' . $partner['color'] . '"/>' .
                '<text x="100" y="38" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">' . $partner['name'] . '</text>' .
                '</svg>'
            );

            $existing = DB::table('cms_media')->where('file_path', $logoPath)->first();
            if ($existing) {
                DB::table('cms_media')->where('media_id', $existing->media_id)->update([
                    'uploaded_by' => $adminId,
                    'uploaded_at' => $now,
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_reason' => null,
                ]);
                $mediaId = $existing->media_id;
            } else {
                $mediaId = DB::table('cms_media')->insertGetId([
                    'file_name' => Str::slug($partner['name']) . '-logo.svg',
                    'file_path' => $logoPath,
                    'media_type' => 'image/svg+xml',
                    'alt_text' => 'Logo ' . $partner['name'],
                    'file_size_kb' => 2,
                    'width_px' => 200,
                    'height_px' => 60,
                    'uploaded_by' => $adminId,
                    'uploaded_at' => $now,
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_reason' => null,
                ]);
            }

            DB::table('cms_partners')->updateOrInsert(
                ['partner_name' => $partner['name']],
                [
                    'media_id' => $mediaId,
                    'category_id' => null,
                    'website_url' => 'https://' . strtolower($partner['name']) . '.com',
                    'display_order' => $index + 1,
                    'is_active' => true,
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'deletion_reason' => null,
                ]
            );
        }

        $this->command?->info('CMS Content seeded successfully with full company profile data!');
    }
}