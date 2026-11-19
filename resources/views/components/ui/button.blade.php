@props(['variant' => 'secondary', 'type' => 'button', 'size' => 'default'])
<button type="{{ $type }}" {{ $attributes->class(['ui-button', 'ui-button-primary' => $variant === 'primary', 'ui-button-secondary' => $variant !== 'primary', 'ui-button-sm' => $size === 'sm']) }}>{{ $slot }}</button>
