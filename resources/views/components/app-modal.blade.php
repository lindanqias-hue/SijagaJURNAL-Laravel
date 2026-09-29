@props([
    'id' => 'app-modal',
    'title',
    'subtitle' => null,
    'close',
    'closeParams' => '',
    'size' => 'md',
])

<div class="app-modal__overlay"
    wire:click.self="{{ $close }}({{ $closeParams }})"
    wire:keydown.escape.window="{{ $close }}({{ $closeParams }})">
    <section id="{{ $id }}"
        class="card-custom app-modal app-modal--{{ $size }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        tabindex="-1"
        x-init="$nextTick(() => $el.focus())">
        <div class="app-modal__head">
            <div class="app-modal__heading">
                <h3 id="{{ $id }}-title">{{ $title }}</h3>
                @if ($subtitle)
                    <div class="text-muted small">{{ $subtitle }}</div>
                @endif
            </div>
            <button type="button" class="btn-close" aria-label="Tutup" wire:click="{{ $close }}({{ $closeParams }})"></button>
        </div>

        <div class="app-modal__body">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="app-modal__foot">
                {{ $footer }}
            </div>
        @endif
    </section>
</div>
