<x-filament-panels::page>
    <x-filament::section heading="Talep bilgileri">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-gray-500">Uygulama</dt><dd>{{ $record->app->name }} ({{ $record->app->slug }})</dd></div>
            <div><dt class="text-sm text-gray-500">Kullanıcı</dt><dd>{{ $record->user->name }} · {{ $record->user->email }}</dd></div>
            <div><dt class="text-sm text-gray-500">Konu</dt><dd>{{ $record->subject }}</dd></div>
            <div><dt class="text-sm text-gray-500">Durum</dt><dd>{{ \App\Filament\Resources\SupportTickets\SupportTicketResource::statuses()[$record->status] ?? $record->status }}</dd></div>
        </dl>
        <p class="mt-4 text-sm text-gray-500">Yanıtlar yalnızca bu kullanıcının {{ $record->app->name }} uygulamasındaki destek görüşmesine gönderilir.</p>
    </x-filament::section>
    <x-filament::section heading="Görüşme">
        <div class="space-y-4">
            @foreach ($record->messages()->get() as $message)
                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="mb-2 flex flex-wrap justify-between gap-2 text-sm text-gray-500">
                        <strong>{{ $message->sender_type === 'staff' ? 'Destek ekibi' : $record->user->name }}</strong>
                        <time>{{ $message->created_at->format('d.m.Y H:i') }}</time>
                    </div>
                    <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                </article>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
