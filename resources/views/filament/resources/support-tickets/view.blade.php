<x-filament-panels::page>
    <style>
        .support-console{--sc-border:#e5e7eb;--sc-muted:#6b7280;--sc-surface:#f9fafb;--sc-reply:#eff6ff;display:grid;gap:24px}
        .dark .support-console{--sc-border:#374151;--sc-muted:#9ca3af;--sc-surface:#1f2937;--sc-reply:#172b46}
        .sc-layout{display:grid;gap:24px;align-items:start}.sc-main,.sc-sidebar{display:grid;gap:24px;min-width:0}
        .sc-heading{font-size:20px;font-weight:650;line-height:1.4;overflow-wrap:anywhere}.sc-muted{color:var(--sc-muted);font-size:13px;line-height:1.6}
        .sc-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.sc-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
        .sc-pill{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;background:var(--sc-surface);font-size:12px;font-weight:600;border:1px solid var(--sc-border)}
        .sc-thread{display:flex;flex-direction:column;gap:20px}.sc-message{max-width:88%;border:1px solid var(--sc-border);border-radius:16px 16px 16px 4px;padding:16px;background:var(--sc-surface);align-self:flex-start;min-width:180px}
        .sc-message.is-staff{align-self:flex-end;background:var(--sc-reply);border-radius:16px 16px 4px 16px}.sc-body{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.7;font-size:14px;margin-top:8px}
        .sc-composer{margin-top:24px;padding-top:20px;border-top:1px solid var(--sc-border)}.sc-data{display:grid;gap:16px}.sc-data dt{color:var(--sc-muted);font-size:12px;margin-bottom:3px}.sc-data dd{font-size:14px;overflow-wrap:anywhere}
        .sc-timeline{display:grid;gap:18px}.sc-event{padding-left:14px;border-left:2px solid var(--sc-border)}.sc-note{background:var(--sc-surface);padding:14px;border-radius:10px;margin-top:8px}.sc-alert{padding:12px 16px;border:1px solid var(--sc-border);border-radius:10px;margin-top:16px;font-size:14px}
        .sc-empty{text-align:center;padding:28px 12px;color:var(--sc-muted)}.sc-related{display:grid;gap:14px}
        @media(min-width:1100px){.sc-layout{grid-template-columns:minmax(0,1fr) 320px}}
        @media(max-width:640px){.sc-message{max-width:96%}.sc-heading{font-size:18px}}
    </style>
    @php
        $notification = $this->notificationSummary();
        $canUpdate = auth()->user()->can('update', $record);
        $related = $this->relatedTickets();
    @endphp
    <div class="support-console" wire:poll.20s="refreshTicket">
        <x-filament::section>
            <div class="sc-row">
                <div>
                    <p class="sc-muted">{{ $record->app->name }} · {{ $record->user->name }}</p>
                    <h2 class="sc-heading">{{ $record->subject }}</h2>
                </div>
                <x-filament::badge :color="$record->status === 'open' ? 'warning' : ($record->status === 'answered' ? 'success' : 'gray')">
                    {{ \App\Filament\Resources\SupportTickets\SupportTicketResource::statuses()[$record->status] ?? $record->status }}
                </x-filament::badge>
            </div>
            @if(!$record->app->is_active || !$notification['membership_active'])
                <p class="sc-alert">Uygulama veya kullanıcı üyeliği pasif. Şu anda yanıt gönderilemez; iç not ekleyebilirsiniz.</p>
            @endif
        </x-filament::section>
        <div class="sc-layout">
            <div class="sc-main">
                <x-filament::section heading="Görüşme" description="Kullanıcı mesajları solda, destek yanıtları sağda.">
                    <div class="sc-thread">
                        @if($record->messages()->count() > $this->messageLimit)
                            <x-filament::button wire:click="moreMessages" color="gray" size="sm">Önceki mesajları göster</x-filament::button>
                        @endif
                        @forelse ($this->conversation() as $message)
                            <article wire:key="message-{{ $message->id }}" class="sc-message {{ $message->sender_type === 'staff' ? 'is-staff' : '' }}">
                                <div class="sc-row sc-muted">
                                    <strong>{{ $message->sender_type === 'staff' ? 'Destek · ' . ($message->sender?->name ?? 'Eski görevli') : $record->user->name }}</strong>
                                    <time>{{ $message->created_at->format('d.m.Y H:i') }}</time>
                                </div>
                                <p class="sc-body">{{ $message->body }}</p>
                                @if($message->sender_type === 'staff')
                                    <p class="sc-muted" style="margin-top:8px">{{ $record->user_read_at && $record->user_read_at->gte($message->created_at) ? '✓ Kullanıcı okudu' : 'Henüz okunmadı' }}</p>
                                @endif
                            </article>
                        @empty
                            <p class="sc-empty">Bu görüşmede henüz mesaj yok.</p>
                        @endforelse
                    </div>
                    @if($canUpdate)
                        <div class="sc-composer">
                            <div class="sc-row">
                                <div>
                                    <strong>Kullanıcıya yanıt ver</strong>
                                    <p class="sc-muted">{{ $record->user->email }} · {{ $record->app->name }}</p>
                                </div>
                                {{ $this->replyAction }}
                            </div>
                        </div>
                    @endif
                </x-filament::section>
                <x-filament::section heading="Ekip notları ve geçmiş" description="Yalnızca destek ekibi görür." collapsible collapsed>
                    @if($canUpdate)<div style="margin-bottom:20px">{{ $this->noteAction }}</div>@endif
                    <div class="sc-timeline">
                        @forelse ($this->history() as $activity)
                            <article wire:key="activity-{{ $activity->id }}" class="sc-event">
                                <strong>{{ ['created'=>'Talep oluşturuldu','customer_message'=>'Kullanıcı mesajı','reply'=>'Destek yanıtı','note'=>'İç not','closed'=>'Talep kapatıldı','reopened'=>'Talep yeniden açıldı','updated'=>'Talep bilgileri güncellendi'][$activity->type] ?? $activity->type }}</strong>
                                <p class="sc-muted">{{ $activity->actor?->name ?? 'Silinmiş kullanıcı' }} · {{ $activity->created_at->format('d.m.Y H:i') }}</p>
                                @if($activity->body)<p class="sc-body sc-note">{{ $activity->body }}</p>@endif
                                @if($activity->type === 'updated')
                                    @foreach($activity->metadata ?? [] as $field => $change)
                                        @php($labels = match($field) { 'priority' => \App\Models\SupportTicket::priorities(), 'category' => \App\Models\SupportTicket::categories(), default => [] })
                                        <p class="sc-muted">{{ ['priority'=>'Öncelik','category'=>'Kategori','assigned_to'=>'Görevli no','due_at'=>'Yanıt hedefi'][$field] ?? $field }}: {{ $labels[$change['from'] ?? ''] ?? ($change['from'] ?? '—') }} → {{ $labels[$change['to'] ?? ''] ?? ($change['to'] ?? '—') }}</p>
                                    @endforeach
                                @endif
                            </article>
                        @empty
                            <p class="sc-muted">Henüz ekip notu veya işlem kaydı yok.</p>
                        @endforelse
                        @if($record->activities()->count() > $this->activityLimit)
                            <x-filament::button wire:click="moreActivities" color="gray" size="sm">Daha eski işlemleri göster</x-filament::button>
                        @endif
                    </div>
                </x-filament::section>
            </div>
            <aside class="sc-sidebar" aria-label="Talep bilgileri ve yönetimi">
                <x-filament::section heading="Talep yönetimi">
                    <dl class="sc-data">
                        <div><dt>Görevli</dt><dd>{{ $record->assignee?->name ?? 'Henüz atanmadı' }}</dd></div>
                        <div><dt>Öncelik</dt><dd><span class="sc-pill">{{ \App\Models\SupportTicket::priorities()[$record->priority] ?? $record->priority }}</span></dd></div>
                        <div><dt>Kategori</dt><dd>{{ \App\Models\SupportTicket::categories()[$record->category] ?? $record->category }}</dd></div>
                        <div><dt>Yanıt hedefi</dt><dd>{{ $record->due_at?->format('d.m.Y H:i') ?? 'Belirlenmedi' }} @if($record->status !== 'closed' && $record->due_at?->isPast())<x-filament::badge color="danger">Gecikti</x-filament::badge>@endif</dd></div>
                    </dl>
                    @if($canUpdate)
                        <div class="sc-actions">{{ $this->takeAction }} {{ $this->detailsAction }}</div>
                        <div class="sc-actions">{{ $this->closeAction }} {{ $this->reopenAction }}</div>
                    @endif
                </x-filament::section>
                <x-filament::section heading="Kullanıcı ve uygulama" collapsible>
                    <dl class="sc-data">
                        <div><dt>Kullanıcı</dt><dd>{{ $record->user->name }}<br><span class="sc-muted">{{ $record->user->email }}</span></dd></div>
                        <div><dt>Uygulama</dt><dd>{{ $record->app->name }}<br><span class="sc-muted">{{ $record->app->slug }}</span></dd></div>
                        <div><dt>Dil · açılış</dt><dd>{{ $record->locale === 'tr' ? 'Türkçe' : 'İngilizce' }} · {{ $record->created_at->format('d.m.Y H:i') }}</dd></div>
                        <div><dt>Talep numarası</dt><dd class="sc-muted">{{ $record->id }}</dd></div>
                    </dl>
                </x-filament::section>
                <x-filament::section heading="Bildirim ayrıntıları" collapsible collapsed>
                    <div class="sc-data">
                        <p class="sc-muted">Kullanıcı tercihi: {{ $notification['enabled'] ? 'Bildirimler açık' : 'Bildirimler kapalı' }}</p>
                        <p>{{ ['pending'=>'Gönderim bekliyor','completed'=>'Gönderim işlemi tamamlandı','skipped'=>'Bildirim gönderilmedi'][$notification['status'] ?? ''] ?? 'Henüz destek yanıtı yok' }}</p>
                        @foreach($notification['counts'] as $status => $count)
                            <p class="sc-muted">{{ ['sent'=>'Bildirim servisi kabul etti','pending'=>'Bekleyen cihaz','invalid_token'=>'Geçersiz cihaz kaydı','failed'=>'Gönderim başarısız'][$status] ?? $status }}: {{ $count }}</p>
                        @endforeach
                        <p class="sc-muted">Servisin kabulü telefonda gösterildiğini garanti etmez. Okuma durumu mesajın altında görünür.</p>
                    </div>
                </x-filament::section>
                @if($related->isNotEmpty())
                    <x-filament::section heading="Diğer talepler" collapsible collapsed>
                        <div class="sc-related">
                            @foreach($related as $other)
                                <div><p class="sc-muted">{{ $other->app->name }}</p><x-filament::link :href="\App\Filament\Resources\SupportTickets\SupportTicketResource::getUrl('view',['record'=>$other])">{{ $other->subject }}</x-filament::link></div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            </aside>
        </div>
    </div>
</x-filament-panels::page>
