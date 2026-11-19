@props(['id', 'title', 'description' => ''])
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if ($description) aria-describedby="{{ $id }}-description" @endif {{ $attributes->class(['ui-dialog']) }}>
    <div class="ui-dialog-header">
        <div>
            <h2 id="{{ $id }}-title" class="ui-dialog-title">{{ $title }}</h2>
            @if ($description)
                <p id="{{ $id }}-description" class="mt-[5px] text-[11px] text-neutral-500">{{ $description }}</p>
            @endif
        </div>
        <button type="button" data-close-dialog aria-label="Tutup dialog" class="ui-dialog-close"><span aria-hidden="true">×</span></button>
    </div>
    {{ $slot }}
</dialog>
