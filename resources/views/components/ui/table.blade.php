@props(['caption'])
<div {{ $attributes->class(['overflow-x-auto']) }} tabindex="0" role="region" aria-label="{{ $caption }}">
    <table class="ui-table">
        <caption class="sr-only">{{ $caption }}</caption>
        {{ $slot }}
    </table>
</div>
