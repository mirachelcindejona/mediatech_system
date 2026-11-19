@props(['prefix'])
<div class="ui-pagination">
    <p id="{{ $prefix }}-count" role="status" aria-live="polite"></p>
    <div class="flex flex-wrap items-center gap-2">
        <label for="{{ $prefix }}-size">Tampilkan</label>
        <x-ui.select id="{{ $prefix }}-size"><option>5</option><option selected>10</option><option>25</option><option>50</option></x-ui.select>
        <x-ui.button id="{{ $prefix }}-prev" aria-label="Halaman sebelumnya">Sebelumnya</x-ui.button>
        <span id="{{ $prefix }}-page"></span>
        <x-ui.button id="{{ $prefix }}-next" aria-label="Halaman berikutnya">Berikutnya</x-ui.button>
    </div>
</div>
