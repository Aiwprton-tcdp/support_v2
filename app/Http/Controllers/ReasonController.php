<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReasonRequest;
use App\Http\Requests\UpdateReasonRequest;
use App\Http\Resources\ReasonResource;
use App\Models\Reason;
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

        if (Cache::store('file')->has('reasons')) {
            return response()->json([
                'status' => true,
                'data' => Cache::store('file')->get('reasons')
            ]);
        }

        $data = \Illuminate\Support\Facades\DB::table('reasons')
            ->when(!empty($id) || !empty($name), function ($q) use ($id, $name) {
                $q->whereId($id)->orWhereRaw('LOWER(name) LIKE ?', ["%{$name}%"]);
            })
            ->paginate($limit < 1 ? 10 : $limit);
        $resource = ReasonResource::collection($data)->response()->getData();
        Cache::store('file')->forever('reasons', $resource);

        return response()->json([
            'status' => true,
            'data' => $resource
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReasonRequest $request)
    {
        $validated = $request->validated();
        $reason = Reason::firstOrNew(['name' => $validated['name']], $validated);
        $is_old = $reason->exists;

        $message = 'Тема `' . $reason->name .
            ($is_old ? '` уже существует' : '` успешно создана');
        
        if (!$is_old) {
            Log::info($message);
            $reason->save();
            Cache::store('file')->forget('reasons');
        }

        return response()->json([
            'status' => !$is_old,
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
        $reason = Reason::findOrFail($id);
        $message = 'Тема `' . $reason->name . '` успешно изменена';

        $exists_with_names = Reason::whereName($validated['name'])
            ->whereNot('id', $id)->exists();
        if ($exists_with_names) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Тема с таким названием уже существует'
            ]);
        }
        
        $reason->fill($validated);
        $reason->save();
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => ReasonResource::make($reason),
            'message' => $message
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $reason = Reason::findOrFail($id);
        $message = 'Тема `' . $reason->name . '` успешно удалена';

        $result = $reason->delete();
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message
        ]);
    }
}
