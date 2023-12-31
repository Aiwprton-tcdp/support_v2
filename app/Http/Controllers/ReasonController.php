<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReasonRequest;
use App\Http\Requests\UpdateReasonRequest;
use App\Http\Resources\ReasonResource;
use App\Models\Reason;
use App\Models\Ticket;
use App\Traits\ReasonTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReasonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $name = Str::lower($this->prepare(request('name')));
        $id = intval($this->prepare(request('id')));
        $limit = intval($this->prepare(request('limit')));
        $is_call_required = $this->prepare(request('call_required'));
        $call_required = filter_var($is_call_required, FILTER_VALIDATE_BOOLEAN);

        $checksum = ReasonTrait::Checksum();

        if (isset($is_call_required) && $is_call_required != 'true' && Cache::store('file')->has('reasons')) {
            return response()->json([
                'status' => true,
                'checksum' => $checksum,
                'data' => Cache::store('file')->get('reasons')
            ]);
        }

        $data = \Illuminate\Support\Facades\DB::table('reasons')
            ->when(
                !empty($id) || !empty($name),
                fn($q) => $q->where('id', $id)->orWhereRaw('LOWER(name) LIKE ?', ["%{$name}%"])
            )
            ->when(
                $is_call_required == 'true',
                fn($q) => $q->where('call_required', $call_required)
            )
            ->paginate($limit < 1 ? 100 : $limit);
        $resource = ReasonResource::collection($data)->response()->getData();
        if (isset($is_call_required) && $is_call_required != 'true') {
            Cache::store('file')->forever('reasons', $resource);
        }

        return response()->json([
            'status' => count($checksum) == 0,
            'checksum' => $checksum,
            'data' => $resource
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReasonRequest $request)
    {
        $validated = $request->validated();
        $reason = Reason::firstOrCreate(['name' => $validated['name']], $validated);

        $message = "Тема {$reason->name} успешно создана";
        Log::info($message);
        Cache::store('file')->forget('reasons');

        return response()->json([
            'status' => true,
            'data' => ReasonResource::make($reason),
            'message' => $message
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Reason $reason)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReasonRequest $request, $id)
    {
        $validated = $request->validated();

        if (Reason::whereName($validated['name'])->whereNot('id', $id)->exists()) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Тема с таким названием уже существует'
            ]);
        }

        $reason = Reason::findOrFail($id);
        $message = "Тема {$reason->name} успешно изменена";

        $reason->fill($validated);
        $reason->save();
        $data = Reason::findOrFail($id);
        Log::info($message);
        Cache::store('file')->forget('reasons');

        return response()->json([
            'status' => true,
            'data' => ReasonResource::make($data),
            'checksum' => ReasonTrait::Checksum(),
            'message' => $message
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $reason = Reason::findOrFail($id);

        if (Ticket::whereActive(true)->whereReasonId($id)->exists()) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'С данной темой есть открытые тикеты'
            ]);
        }

        $result = $reason->delete();
        $message = "Тема {$reason->name} успешно удалена";
        Log::info($message);
        Cache::store('file')->forget('reasons');

        return response()->json([
            'status' => true,
            'data' => $result,
            'checksum' => ReasonTrait::Checksum(),
            'message' => $message
        ]);
    }

    public function initByMessage()
    {
        $validator = Validator::make(request()->all(), [
            'message' => 'required|min:2|max:500',
        ]);
        $validated = $validator->safe()->only(['message']);
        $message = $validated['message'];
        if (!isset($message) || empty($message)) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Не передано сообщение',
            ]);
        }

        $reason = ReasonTrait::initByMessage($message);

        return response()->json([
            'status' => true,
            'data' => $reason,
        ]);
    }
}