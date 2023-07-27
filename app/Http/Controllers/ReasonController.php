<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReasonRequest;
use App\Http\Requests\UpdateReasonRequest;
use App\Http\Resources\ReasonResource;
use App\Models\Reason;
use App\Traits\ReasonTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ReasonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $name = Str::lower(htmlspecialchars(trim(request('name'))));
        $id = intval(htmlspecialchars(trim(request('id'))));
        $limit = intval(htmlspecialchars(trim(request('limit'))));

        $checksum = ReasonTrait::Checksum();

        if (Cache::store('file')->has('reasons')) {
            return response()->json([
                'status' => true,
                'checksum' => $checksum,
                'data' => Cache::store('file')->get('reasons')
            ]);
        }

        $data = \Illuminate\Support\Facades\DB::table('reasons')
            ->when(!empty($id) || !empty($name), function ($q) use ($id, $name) {
                $q->whereId($id)->orWhereRaw('LOWER(name) LIKE ?', ["%{$name}%"]);
            })
            ->paginate($limit < 1 ? 100 : $limit);
        $resource = ReasonResource::collection($data)->response()->getData();
        Cache::store('file')->forever('reasons', $resource);
        
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

        $message = 'Тема `' . $reason->name .'` успешно создана';
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
        $reason = Reason::findOrFail($id);
        $message = 'Тема `' . $reason->name . '` успешно изменена';
        
        $reason->fill($request->validated());
        $reason->save();
        $data = Reason::findOrFail($id);
        Log::info($message);
        Cache::store('file')->forget('reasons');

        $checksum = ReasonTrait::Checksum();

        return response()->json([
            'status' => true,
            'data' => ReasonResource::make($data),
            'checksum' => $checksum,
            'message' => $message
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $reason = Reason::findOrFail($id);

        $has_active_tickets = \App\Models\Ticket::whereActive(true)
            ->whereReasonId($id)->exists();
        if ($has_active_tickets) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'С данной темой есть открытые тикеты'
            ]);
        }
        
        $result = $reason->delete();
        $message = 'Тема `' . $reason->name . '` успешно удалена';
        Log::info($message);
        Cache::store('file')->forget('reasons');

        $checksum = ReasonTrait::Checksum();

        return response()->json([
            'status' => true,
            'data' => $result,
            'checksum' => $checksum,
            'message' => $message
        ]);
    }
}
