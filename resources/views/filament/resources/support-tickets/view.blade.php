<x-filament-panels::page>
    <div wire:poll.20s="refreshTicket">
        <x-filament::section :heading="$record->app->name . ' · ' . $record->subject">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-sm text-gray-500">Uygulama</dt><dd>{{ $record->app->name }} · {{ $record->app->slug }}</dd></div>
                <div><dt class="text-sm text-gray-500">Kullanıcı</dt><dd>{{ $record->user->name }}<br>{{ $record->user->email }}</dd></div>
                <div><dt class="text-sm text-gray-500">Durum</dt><dd>{{ \App\Filament\Resources\SupportTickets\SupportTicketResource::statuses()[$record->status] ?? $record->status }}</dd></div>
                <div><dt class="text-sm text-gray-500">Görevli</dt><dd>{{ $record->assignee?->name ?? 'Atanmadı' }}</dd></div>
                <div><dt class="text-sm text-gray-500">Öncelik / kategori</dt><dd>{{ \App\Models\SupportTicket::priorities()[$record->priority] ?? $record->priority }} · {{ \App\Models\SupportTicket::categories()[$record->category] ?? $record->category }}</dd></div>
                <div><dt class="text-sm text-gray-500">Yanıt hedefi</dt><dd>{{ $record->due_at?->format('d.m.Y H:i') ?? 'Belirlenmedi' }} @if($record->status !== 'closed' && $record->due_at?->isPast()) · Gecikti @endif</dd></div>
                <div><dt class="text-sm text-gray-500">Talep no</dt><dd class="break-all">{{ $record->id }}</dd></div>
                <div><dt class="text-sm text-gray-500">Dil / açılış</dt><dd>{{ $record->locale === 'tr' ? 'Türkçe' : 'İngilizce' }} · {{ $record->created_at->format('d.m.Y H:i') }}</dd></div>
                <div><dt class="text-sm text-gray-500">Son kullanıcı mesajı</dt><dd>{{ $record->last_customer_message_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
            </dl>
            <p class="mt-4 text-sm text-gray-500">Yanıt hedefi: {{ $record->user->email }} → {{ $record->app->name }}. Aynı kullanıcının diğer uygulamalardaki görüşmeleri ayrıdır.</p>
            @php($notification = $this->notificationSummary())
            @if(!$record->app->is_active || !$notification['membership_active'])
                <p class="mt-4">Uygulama veya üyelik pasif. Yanıt gönderimi kapalı; görüşmeyi inceleyebilir ve iç not ekleyebilirsiniz.</p>
            @endif
        </x-filament::section>
    </div>
    <x-filament::section heading="Kullanıcıyla görüşme">
        <div class="space-y-4">
            @if($record->messages()->count() > $this->messageLimit)
                <x-filament::button wire:click="moreMessages" color="gray" size="sm">Önceki mesajları göster</x-filament::button>
            @endif
            @foreach ($this->conversation() as $message)
                <article wire:key="message-{{ $message->id }}" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="mb-2 flex flex-wrap justify-between gap-2 text-sm text-gray-500">
                        <strong>{{ $message->sender_type === 'staff' ? 'Destek · ' . ($message->sender?->name ?? 'Eski görevli') : $record->user->name }}</strong>
                        <time>{{ $message->created_at->format('d.m.Y H:i') }}</time>
                    </div>
                    <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                    @if($message->sender_type === 'staff')
                        <p class="mt-2 text-sm text-gray-500">{{ $record->user_read_at && $record->user_read_at->gte($message->created_at) ? 'Kullanıcı okudu' : 'Henüz okunmadı' }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </x-filament::section>
    <x-filament::section heading="İç notlar ve işlem geçmişi" description="Bu bölüm kullanıcıya gösterilmez.">
        <div class="space-y-4">
            @foreach ($this->history() as $activity)
                <article wire:key="activity-{{ $activity->id }}" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="mb-2 flex flex-wrap justify-between gap-2 text-sm text-gray-500">
                        <strong>{{ ['created'=>'Talep oluşturuldu','customer_message'=>'Kullanıcı mesajı','reply'=>'Destek yanıtı','note'=>'İç not','closed'=>'Talep kapatıldı','reopened'=>'Talep yeniden açıldı','updated'=>'Atama / öncelik güncellendi'][$activity->type] ?? $activity->type }}</strong>
                        <span>{{ $activity->actor?->name ?? 'Silinmiş kullanıcı' }} · {{ $activity->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                    @if($activity->body)<p class="whitespace-pre-wrap break-words">{{ $activity->body }}</p>@endif
                    @if($activity->type === 'updated')
                        @foreach($activity->metadata ?? [] as $field => $change)
                            <p class="text-sm">{{ ['priority'=>'Öncelik','category'=>'Kategori','assigned_to'=>'Görevli no','due_at'=>'Yanıt hedefi'][$field] ?? $field }}: {{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}</p>
                        @endforeach
                    @endif
                </article>
            @endforeach
            @if($record->activities()->count() > $this->activityLimit)
                <x-filament::button wire:click="moreActivities" color="gray" size="sm">Daha eski işlemleri göster</x-filament::button>
            @endif
        </div>
    </x-filament::section>
    <x-filament::section heading="Son yanıtın bildirim durumu">
        <p>Destek bildirimi tercihi: {{ $notification['enabled'] ? 'Açık' : 'Kapalı' }}</p>
        <p>Gönderim: {{ ['pending'=>'Kuyrukta','completed'=>'Gönderim işlemi tamamlandı','skipped'=>'Gönderim koşulları sağlanmadı'][$notification['status'] ?? ''] ?? 'Henüz yanıt yok' }}</p>
        @foreach($notification['counts'] as $status => $count)
            <p>{{ ['sent'=>'FCM kabul etti','pending'=>'Bekleyen cihaz','invalid_token'=>'Geçersiz cihaz token’ı','failed'=>'Gönderim başarısız'][$status] ?? $status }}: {{ $count }}</p>
        @endforeach
        <p class="mt-2 text-sm text-gray-500">FCM’nin kabul etmesi telefonda gösterildiğini kanıtlamaz. Kullanıcının okuma durumu görüşmede ayrıca görünür.</p>
    </x-filament::section>
    @if($this->relatedTickets()->isNotEmpty())
        <x-filament::section heading="Bu kullanıcının erişebildiğiniz diğer talepleri">
            @foreach($this->relatedTickets() as $other)
                <p class="mb-2"><x-filament::link :href="\App\Filament\Resources\SupportTickets\SupportTicketResource::getUrl('view',['record'=>$other])">{{ $other->app->name }} · {{ $other->subject }}</x-filament::link></p>
            @endforeach
        </x-filament::section>
    @endif
</x-filament-panels::page>
