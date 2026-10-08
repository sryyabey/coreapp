<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'app_slug' => $this->app->slug, 'app_name' => $this->app->name,
            'subject' => $this->subject, 'status' => $this->status,
            'has_unread_reply' => $this->last_staff_reply_at !== null && ($this->user_read_at === null || $this->last_staff_reply_at->gt($this->user_read_at)),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
            'messages' => SupportMessageResource::collection($this->whenLoaded('messages'))];
    }
}
