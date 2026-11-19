@props(['id', 'title', 'description', 'confirmLabel'])
<x-ui.modal :id="$id" :title="$title" :description="$description" {{ $attributes }}>
    <form method="dialog">
        <div class="ui-dialog-body">{{ $slot }}</div>
        <div class="ui-dialog-footer">
            <x-ui.button data-confirm-back>Kembali</x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ $confirmLabel }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
