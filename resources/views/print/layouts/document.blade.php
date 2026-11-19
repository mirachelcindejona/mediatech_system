<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dokumen Meditech')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #262626; font: 12px/1.55 Arial, sans-serif; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; max-width: 900px; margin: 22px auto 12px; }
        .toolbar a, .toolbar button { border: 1px solid #d4d4d4; border-radius: 6px; padding: 9px 13px; background: #fff; color: #404040; font-size: 11px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar .primary { border-color: #0d21a1; background: #0d21a1; color: #fff; }
        .paper { width: min(900px, calc(100% - 32px)); min-height: 1050px; margin: 0 auto 30px; padding: 48px 54px; background: #fff; box-shadow: 0 4px 25px #00000012; }
        @page { size: A4; margin: 12mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .paper { width: 100%; min-height: 0; margin: 0; padding: 20px 24px; box-shadow: none; }
            thead { display: table-header-group; }
            tr, .avoid-break { break-inside: avoid; }
        }
        @media (max-width: 640px) {
            .paper { padding: 25px 20px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="toolbar">
        <a href="@yield('back_url', route('dashboard'))">Kembali</a>
        <button type="button" class="primary" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <main class="paper">
        @yield('content')
    </main>
</body>
</html>
