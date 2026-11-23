<?php

namespace App\Http\Controllers;

use App\Models\CMS\CmsPage;
use App\Services\Cms\CmsContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PublicController extends Controller
{
    /**
     * Company profile satu halaman. Seluruh section dibaca dari CMS
     * (lihat App\Services\Cms\CmsContentService); $cms juga dibagikan
     * ke layout lewat View composer di AppServiceProvider.
     */
    public function index(CmsContentService $content): View
    {
        return view('public.home', [
            'cms'      => $content->all(),
            'partners' => $content->partners(),
        ]);
    }

    public function show(string $slug): View
    {
        $page = CmsPage::published()->where('slug', $slug)->firstOrFail();

        $fields = $page->fields()->whereNull('deleted_at')->pluck('field_value', 'field_key');
        $items = $page->items()->active()->orderBy('sort_order')->with('media')->get()->groupBy('section_key');

        return view('public.page', [
            'page'   => $page,
            'fields' => $fields,
            'items'  => $items,
        ]);
    }

    /**
     * Formulir "Kirim pesan" pada bagian kontak. Pesan diteruskan lewat email
     * ke alamat email yang diisi di CMS (Kontak & footer).
     */
    public function contact(Request $request, CmsContentService $content): RedirectResponse
    {
        $back = route('home') . '#kontak';

        // Honeypot: bot biasanya mengisi kolom tersembunyi ini.
        if ($request->filled('website')) {
            return redirect($back);
        }

        $validator = Validator::make($request->all(), [
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ], [], [
            'name'    => 'Nama',
            'email'   => 'Email',
            'message' => 'Kebutuhan Anda',
        ]);

        if ($validator->fails()) {
            return redirect($back)->withErrors($validator, 'contact')->withInput();
        }

        $data = $validator->validated();
        $to = $content->all()['contact']['email'] ?? null;
        $to = filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : config('mail.from.address');

        try {
            Mail::raw(
                "Pesan baru dari formulir website\n\n"
                . "Nama  : {$data['name']}\n"
                . "Email : {$data['email']}\n\n"
                . $data['message'],
                function ($mail) use ($to, $data) {
                    $mail->to($to)
                        ->replyTo($data['email'], $data['name'])
                        ->subject('Pesan baru dari website: ' . $data['name']);
                }
            );
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim pesan kontak website', ['error' => $e->getMessage()]);

            return redirect($back)
                ->withErrors(['send' => 'Pesan belum dapat dikirim. Silakan coba lagi atau hubungi kami melalui telepon.'], 'contact')
                ->withInput();
        }

        return redirect($back)->with('contact_status', 'Terima kasih, pesan Anda sudah kami terima. Tim kami akan segera menghubungi Anda.');
    }
}