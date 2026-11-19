@props(['target'])
<div {{ $attributes->class(['flex flex-wrap justify-end gap-2 px-4 py-3']) }}>
        <x-ui.button size="sm" data-export-table="{{ $target }}"><svg class="ui-icon" aria-hidden="true"><use href="#i-download"/></svg>Ekspor Excel / CSV</x-ui.button>
        <x-ui.button size="sm" data-print-table="{{ $target }}"><svg class="ui-icon" aria-hidden="true"><use href="#inventory-icon-print"/></svg>Cetak / Simpan PDF</x-ui.button>
</div>
