@props(['type' => 'text'])
<input type="{{ $type }}" {{ $attributes->class(['ui-input' => $type !== 'checkbox', 'ui-checkbox' => $type === 'checkbox']) }}>
