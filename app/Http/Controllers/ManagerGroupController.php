<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagerGroupRequest;
use App\Models\ManagerGroup;
use Illuminate\Support\Facades\Log;

class ManagerGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $group = intval(htmlspecialchars(trim(request('group'))));
        $data = \Illuminate\Support\Facades\DB::table('manager_groups')
            ->when(!empty($group), function ($q) use ($group) {
                $q->whereGroupId($group);
            })->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManagerGroupRequest $request)
    {
        $validated = $request->validated();
        $user = \App\Models\Manager::with(['user:id,name'])->findOrFail($validated['manager_id']);
        
        if ($user->role_id != 2) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'В группу можно добавить только менеджера',
            ]);
        }
        
        $group = \App\Models\Group::findOrFail($validated['group_id']);
        $data = ManagerGroup::firstOrNew($validated);
        $is_old = $data->exists;
        
        $message = 'Менеджер `' . $user->name . '`' .
            ($is_old ? ' уже' : '') .
            ' добавлен в группу `' . $group->name . '`';
        
        if ($group->alone) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Группа одноимённая и не доступна для добавления в неё менеджеров',
            ]);
        }
        
        if (!$is_old) {
            $data->save();
            Log::info($message);
        }

        return response()->json([
            'status' => !$is_old,
            'data' => $data,
            'message' => $message,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = ManagerGroup::findOrFail($id);
        $count = ManagerGroup::where('group_id', $data->group_id)->count();
        
        if ($count == 1) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Данный менеджер указан как единственный участник в данной группе'
            ]);
        }

        $user = \App\Models\Manager::with(['user:id,name'])->findOrFail($data->manager_id);
        $group = \App\Models\Group::findOrFail($data->group_id);
        $message = '`' . $user->name . '` удалён из группы `' . $group->name . '`';
        Log::info($message);

        $result = $data->delete();

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message,
        ]);
    }
}
