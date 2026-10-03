<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShiftCal\ShiftTemplateRequest;
use App\Http\Resources\Api\ShiftCal\ShiftTemplateResource;
use App\Models\ShiftCal\ShiftTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ShiftTemplateController extends Controller
{
    private function owned(Request $request): Builder
    {
        return ShiftTemplate::where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->owned($request);
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query->orderBy('name')->orderBy('id');

        return ShiftTemplateResource::collection($query->paginate($request->integer('per_page', 30)));
    }

    public function store(ShiftTemplateRequest $request): ShiftTemplateResource
    {
        $data = $request->validated();
        $record = new ShiftTemplate($data);
        $record->forceFill(['app_id' => $request->attributes->get('mobile_app')->id, 'user_id' => $request->user()->id])->save();

        return new ShiftTemplateResource($record);
    }

    public function show(Request $request, string $app, string $shift_template): ShiftTemplateResource
    {
        return new ShiftTemplateResource($this->owned($request)->whereKey($shift_template)->firstOrFail());
    }

    public function update(ShiftTemplateRequest $request, string $app, string $shift_template): ShiftTemplateResource
    {
        $record = $this->owned($request)->whereKey($shift_template)->firstOrFail();
        $data = $request->validated();
        $record->update($data);

        return new ShiftTemplateResource($record);
    }

    public function destroy(Request $request, string $app, string $shift_template): Response
    {
        $this->owned($request)->whereKey($shift_template)->firstOrFail()->delete();

        return response()->noContent();
    }
}
