<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagerRequest;
use App\Http\Requests\UpdateManagerRequest;
use App\Http\Resources\ManagerResouce;
use App\Models\Group;
use App\Models\Manager;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use \Illuminate\Support\Facades\DB;

class ManagerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $name = Str::lower(htmlspecialchars(trim(request('name'))));
        $id = intval(htmlspecialchars(trim(request('id'))));
        $limit = intval(htmlspecialchars(trim(request('limit'))));

        $data = DB::table('managers')
            ->when(!empty($id) || !empty($name), function ($q) use ($id, $name) {
                $q->whereId($id)->orWhereRaw('LOWER(name) LIKE ?', ["%{$name}%"]);
            })
            ->paginate($limit < 1 ? 10 : $limit);

        return response()->json([
            'status' => true,
            'data' => ManagerResouce::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManagerRequest $request)
    {
        $validated = $request->validated();
        $group = Group::create(['name' => $validated->name]);
        Log::info("Group #" . $group->id . " has been created");

        $data = Manager::create($validated);
        Log::info("Manager CRM_#" . $data->crm_id . " has been created");
        
        return response()->json([
            'status' => true,
            'data' => ManagerResouce::make($data)
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Manager $manager)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateManagerRequest $request, $id)
    {
        $data = Manager::findOrFail($id);
        $data->fill($request->validated());
        $data->save();
        Log::info("Manager CRM_#" . $data->crm_id . " has been updated");

        $group = Group::whereId($data->group_id)
            ->updateOrCreate(['name' => $data->name]);
        Log::info("Group #" . $group->id . " has been updated");

        return response()->json([
            'status' => true,
            'data' => ManagerResouce::make($data)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // $data = Manager::findOrFail($id);
        // $group = Group::findOrFail($data->group_id);
        $has_reasons = Manager::rightJoin('manager_groups', 'manager_groups.user_id', 'managers.id')
            ->leftJoin('groups', 'manager_groups.group_id', 'groups.id')
            // ->where('managers.id', $id)
            // ->selectRaw(DB::raw('SELECT COUNT(managers.group_id) AS `count`'))
            // ->having(DB::raw('COUNT(managers.group_id)'), 1)
            ->select('groups.id', 'groups.name AS gname', 'managers.id AS user_id', 'managers.name AS uname')
            // ->orderBy('groups.id')
            ->groupBy('groups.id', 'user_id')
            ->get();
        return response()->json([
            'status' => null,
            'data' => $has_reasons
        ]);
        // $has_reasons = \App\Models\Reason::join('groups', 'groups.id', 'group_id')
        //     ->where('group_id', $group->id)
        //     -exists();
        // Если есть группы, где менеджер единственный
        // надо найти темы, где стоят группы,
        // у которых единственный менеджер тот, кого мы удаляем

        if ($has_reasons) {
            return response()->json([
                'status' => false,
                'data' => 'Невозможно удалить менеджера, так как есть темы, связанные с группами, в которых данный менеджер указан как единственный участник'
            ]);
        }

        $result = $data->delete();
        Log::info("Manager CRM_#" . $data->crm_id . " has been deleted");

        return response()->json([
            'status' => true,
            'data' => $result
        ]);
    }
}
