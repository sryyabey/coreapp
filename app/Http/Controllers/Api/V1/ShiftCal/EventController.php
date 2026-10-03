<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShiftCal\EventRequest;
use App\Http\Resources\Api\ShiftCal\EventResource;
use App\Models\ShiftCal\Event;
use App\Rules\OffsetDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EventController extends Controller
{
    private function owned(Request $request): Builder
    {
        return Event::where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->owned($request);
        $filters = $request->validate(['from' => ['required_with:to', new OffsetDateTime], 'to' => ['required_with:from', new OffsetDateTime, 'after:from'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        if (isset($filters['from'], $filters['to'])) {
            $query->where('starts_at', '<', CarbonImmutable::parse($filters['to'])->utc())->where('ends_at', '>', CarbonImmutable::parse($filters['from'])->utc());
        }
        $query->orderBy('starts_at')->orderBy('id');

        return EventResource::collection($query->paginate($request->integer('per_page', 30)));
    }

    public function store(EventRequest $request): EventResource
    {
        $data = $request->validated();
        $data['starts_at'] = CarbonImmutable::parse($data['starts_at'])->utc();
        $data['ends_at'] = CarbonImmutable::parse($data['ends_at'])->utc();
        $record = new Event($data);
        $record->forceFill(['app_id' => $request->attributes->get('mobile_app')->id, 'user_id' => $request->user()->id])->save();

        return new EventResource($record);
    }

    public function show(Request $request, string $app, string $event): EventResource
    {
        return new EventResource($this->owned($request)->whereKey($event)->firstOrFail());
    }

    public function update(EventRequest $request, string $app, string $event): EventResource
    {
        $record = $this->owned($request)->whereKey($event)->firstOrFail();
        $data = $request->validated();
        $data['starts_at'] = CarbonImmutable::parse($data['starts_at'])->utc();
        $data['ends_at'] = CarbonImmutable::parse($data['ends_at'])->utc();
        $record->update($data);

        return new EventResource($record);
    }

    public function destroy(Request $request, string $app, string $event): Response
    {
        $this->owned($request)->whereKey($event)->firstOrFail()->delete();

        return response()->noContent();
    }
}
