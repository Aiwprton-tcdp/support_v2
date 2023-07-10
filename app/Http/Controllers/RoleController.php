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
        // if (Cache::store('file')->has('roles')) {
        //     $data = Cache::store('file')->get('roles');
        //     return response()->json([
        //         'status' => true,
        //         'data' => $data
        //     ]);
        // }

        $data = \Illuminate\Support\Facades\DB::table('roles')
            ->whereNot('id', 1)->get();
        $resource = RoleResource::collection($data);//->response()->getData();
        // Cache::store('file')->forever('roles', $resource);

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

        Log::info("Role `" . $data->name . "` has been created");
        // Cache::store('file')->forget('roles');

        return response()->json([
            'status' => true,
            'data' => RoleResource::make($data)
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

        Log::info("Role `" . $name . "` has been updated");
        Cache::store('file')->forget('roles');

        return response()->json([
            'status' => true,
            'data' => RoleResource::make($data)
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

        Log::info("Role `" . $name . "` has been deleted");
        Cache::store('file')->forget('roles');

        return response()->json([
            'status' => true,
            'data' => $result
        ]);
    }
}
