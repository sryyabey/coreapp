<x-filament-widgets::widget>
    <style>
        .core-overview{--co-border:#e5e7eb;--co-muted:#6b7280;--co-bg:#f9fafb;--co-accent:#b45309;--co-tint:#fffbeb}
        .dark .core-overview{--co-border:#374151;--co-muted:#9ca3af;--co-bg:#1f2937;--co-accent:#fbbf24;--co-tint:#322817}
        .co-links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .co-link{display:flex;align-items:flex-start;gap:10px;border:1px solid var(--co-border);border-radius:12px;padding:13px;text-decoration:none;color:inherit;transition:background .15s}
        .co-link:hover{background:var(--co-bg)}.co-link:focus-visible,.co-alert:focus-visible{outline:2px solid var(--co-accent);outline-offset:3px}
        .co-icon{width:20px;height:20px;flex-shrink:0;color:var(--co-accent);margin-top:2px}.co-link strong{display:block;font-size:13px;font-weight:600;overflow-wrap:anywhere}
        .co-link small{display:block;color:var(--co-muted);font-size:12px;line-height:1.5;margin-top:3px}
        .co-heading{font-size:13px;font-weight:600;margin:22px 0 10px}.co-muted{font-size:13px;color:var(--co-muted);line-height:1.6}
        .co-alert{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 12px;background:var(--co-tint);border-radius:10px;margin-top:8px;font-size:13px;text-decoration:none;color:inherit}
        .co-count{color:var(--co-accent);font-weight:700;font-variant-numeric:tabular-nums}
        @media(max-width:420px){.co-links{grid-template-columns:1fr}}
        @media(prefers-reduced-motion:reduce){.co-link{transition:none}}
    </style>
    <div class="core-overview" wire:poll.60s>
        <x-filament::section heading="İşlem merkezi" description="Sık kullanılan bölümlere hızlı erişim.">
            @if(count($links))
                <nav class="co-links" aria-label="Yönetim kısayolları">
                    @foreach($links as $link)
                        <a class="co-link" href="{{ $link['url'] }}">
                            <x-filament::icon :icon="$link['icon']" class="co-icon" />
                            <span><strong>{{ $link['label'] }}</strong><small>{{ $link['description'] }}</small></span>
                        </a>
                    @endforeach
                </nav>
                <h3 class="co-heading">Dikkat gerektirenler</h3>
                @forelse($attention as $item)
                    <a class="co-alert" href="{{ $item['url'] }}"><span>{{ $item['label'] }}</span><span class="co-count">{{ $item['count'] }}</span></a>
                @empty
                    <p class="co-muted">Erişebildiğiniz bölümlerde dikkat gerektiren bir kayıt yok.</p>
                @endforelse
            @else
                <p class="co-muted">Yönetim paneline hoş geldiniz. Bölümler, size erişim yetkisi verildiğinde burada görünecek.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
