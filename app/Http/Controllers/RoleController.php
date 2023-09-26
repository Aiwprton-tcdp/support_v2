<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Cache::store('file')->has('roles')) {
            return response()->json([
                'status' => true,
                'data' => Cache::store('file')->get('roles')
            ]);
        }

        $data = \Illuminate\Support\Facades\DB::table('roles')
            ->whereNot('id', 1)->get();
        $resource = RoleResource::collection($data);
        Cache::store('file')->forever('roles', $resource);

        return response()->json([
            'status' => true,
            'data' => $resource
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        $data = Role::create($request->validated());
        $message = "Создана роль {$data->name}";

        Log::info($message);
        Cache::store('file')->forget('roles');

        return response()->json([
            'status' => true,
            'data' => RoleResource::make($data),
            'message' => $message
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        $data = Role::findOrFail($id);
        $name = $data->name;
        $data->fill($request->validated());
        $data->save();
        $message = "Название роли {$name} изменено на {$data->name}";

        Log::info($message);
        Cache::store('file')->forget('roles');

        return response()->json([
            'status' => true,
            'data' => RoleResource::make($data),
            'message' => $message
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // При удалении надо проверять на менеджеров, имеющих эту роль
        $data = Role::findOrFail($id);
        $name = $data->name;
        $result = $data->delete();
        $message = "Удалена роль {$name}";
        
        Log::info($message);
        Cache::store('file')->forget('roles');

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message
        ]);
    }
}
