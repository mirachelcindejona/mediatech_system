{{-- Shared Blade components remain the markup source for dynamically populated rows. --}}
<template id="inventory-empty-row">
    <tr data-empty><td><x-ui.empty-state><span data-field="message"></span></x-ui.empty-state></td></tr>
</template>
<template id="inventory-available-badge"><x-ui.badge>Available</x-ui.badge></template>
<template id="inventory-low-badge"><x-ui.badge variant="error">Low stock</x-ui.badge></template>
<template id="inventory-stock-row">
    <tr>
        <td data-field="sku" class="font-bold text-primary-500"></td><td data-field="name"></td>
        <td data-field="category" class="text-neutral-600"></td><td data-field="warehouse" class="text-neutral-600"></td>
        <td data-field="qty" class="font-extrabold"></td><td data-field="min" class="text-neutral-600"></td><td data-field="status"></td>
    </tr>
</template>
<template id="inventory-movement-row">
    <tr><td data-field="time"></td><td data-field="reference"></td><td data-field="action"></td><td data-field="detail"></td></tr>
</template>
<template id="inventory-confirm-row">
    <tr><td data-field="sku"></td><td data-field="name" class="whitespace-pre-line"></td><td data-field="quantity"></td><td data-field="warehouse"></td></tr>
</template>
@if(auth()->user()->can('inventory.update') && auth()->user()->can('inventory.post'))
    <template id="inventory-issue-row">
        <tr data-issue-row>
            <td><x-ui.input type="checkbox"/></td>
            <td data-field="sku"></td>
            <td class="inventory-order-product">
                <span data-field="name"></span><span data-field="remaining" class="mt-1 block text-[10px] text-neutral-500"></span>
                <label data-serial-control class="mt-2 block whitespace-normal" hidden>
                    <span class="text-[10px] text-neutral-500">Serial yang dikirim (pilih sesuai jumlah)</span>
                    <x-ui.select data-serial-select multiple size="3" class="mt-1 min-w-40"/>
                    <span data-serial-hint class="mt-1 block text-[10px] text-neutral-500"></span>
                </label>
            </td>
            <td data-available></td>
            <td><x-ui.input type="number" min="0.001" step="0.001" required/></td>
            <td><x-ui.select data-warehouse class="min-w-40"/></td>
        </tr>
    </template>
@endif
