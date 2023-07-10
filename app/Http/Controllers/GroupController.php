<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $name = Str::lower(htmlspecialchars(trim(request('name'))));
        $id = intval(htmlspecialchars(trim(request('id'))));
        $limit = intval(htmlspecialchars(trim(request('limit'))));

        $data = \Illuminate\Support\Facades\DB::table('groups')
            ->when(!empty($id) || !empty($name), function ($q) use ($id, $name) {
                $q->whereId($id)->orWhereRaw('LOWER(name) LIKE ?', ['%{$name}%']);
            })
            ->paginate($limit < 1 ? 10 : $limit);

        return response()->json([
            'status' => true,
            'data' => GroupResource::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupRequest $request)
    {
        $group = Group::firstOrNew($request->validated());
        $is_old = $group->exists;

        $message = 'Группа `' . $group->name .
            ($is_old ? '` уже существует' : '` успешно создана');
        
        if (!$is_old) {
            Log::info($message);
            $group->save();
        }

        return response()->json([
            'status' => !$is_old,
            'data' => GroupResource::make($group),
            'message' => $message
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupRequest $request, $id)
    {
        $data = Group::findOrFail($id);
        $validated = $request->validated();
        $name = $data->name;
        $val_name = $validated['name'];

        if ($name == $val_name) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Группа уже имеет такое же название'
            ]);
        }

        $exists = Group::whereName($val_name)->whereNot('id', $id)->exists();
        if ($exists) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Группа с таким названием уже существует'
            ]);
        }

        $data->fill($validated);
        $data->save();

        $message = 'Группа `' . $name . '` изменена на `' . $val_name . '`';
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => GroupResource::make($data),
            'message' => $message
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = Group::findOrFail($id);
        $reasons = \App\Models\Reason::join('groups', 'groups.id', 'group_id')
            ->whereGroupId($data->id)
            ->selectRaw('COUNT(group_id) AS count, reasons.name AS rname')
            ->groupBy('group_id', 'rname')
            ->pluck('rname')->toArray();

        if (count($reasons) > 0) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Группа используется в темах: ' . implode(', ', $reasons)
            ]);
        }

        \App\Models\ManagerGroup::whereGroupId($data->id)->delete();
        $result = $data->delete();
        $message = 'Группа `' . $data->name . '` успешно удалена';
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message
        ]);
    }
}
