<div style="display:grid;gap:16px">
    <x-filament::section heading="Gönderilecek kişi ve uygulama" compact>
        <p><strong>{{ $ticket->customer_name }}</strong> · {{ $ticket->app->name }}</p>
        <p style="font-size:13px;color:gray;margin-top:4px">{{ $ticket->customer_email }}</p>
    </x-filament::section>
    @php($lastMessage = $ticket->messages()->where('sender_type', 'user')->reorder('id', 'desc')->first())
    @if($lastMessage)
        <x-filament::section heading="Kullanıcının son mesajı" compact>
            <p style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;line-height:1.7">{{ $lastMessage->body }}</p>
            <p style="font-size:12px;color:gray;margin-top:8px">{{ $lastMessage->created_at->format('d.m.Y H:i') }}</p>
        </x-filament::section>
    @endif
</div>
