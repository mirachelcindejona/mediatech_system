@props(['variant' => 'success'])
<span {{ $attributes->class(['ui-badge', 'ui-badge-error' => $variant === 'error', 'ui-badge-success' => $variant !== 'error']) }}>{{ $slot }}</span>
