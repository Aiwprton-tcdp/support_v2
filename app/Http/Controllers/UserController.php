<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagerRequest;
use App\Http\Requests\UpdateManagerRequest;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\ManagerGroup;
use App\Models\User;
use App\Traits\UserTrait;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use \Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $name = Str::lower(htmlspecialchars(trim(request('name'))));
        $id = intval(htmlspecialchars(trim(request('id'))));
        $role = intval(htmlspecialchars(trim(request('role'))));
        $limit = intval(htmlspecialchars(trim(request('limit'))));

        $data = DB::table('users')
            ->where('role_id', '>', 1)
            ->when(!empty($role), function ($q) use ($role) {
                $q->whereRoleId($role);
            })
            ->when(!empty($id) || !empty($name), function ($q) use ($id, $name) {
                $q->whereId($id)->orWhereRaw('LOWER(name) LIKE ?', ["%{$name}%"]);
            })
            ->paginate($limit < 1 ? 10 : $limit);

        return response()->json([
            'status' => true,
            'data' => UserResource::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManagerRequest $request)
    {
        $user = User::firstOrCreate($request->validated());

        $message = "Сотрудник `{$user->name}` crm_id: {$user->crm_id} добавлен с ролью `" .
            \App\Models\Role::find($user->role_id)->name . '`';
        Log::info($message);

        if ($user->role_id == 2) {
            $group = Group::firstOrNew(['name' => $user->name]);
            $group->alone = true;
            $group->save();
            ManagerGroup::firstOrCreate([
                'manager_id' => $user->id,
                'group_id' => $group->id,
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => UserResource::make($user),
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
    public function update(UpdateManagerRequest $request, $id)
    {
        $validated = $request->validated();

        $user = User::join('managers', 'managers.crm_id', 'users.crm_id')
            ->select('users.*', 'managers.role_id')
            ->findOrFail($id);
        // dd($user);
        $message = "Сотрудник `{$user->name}` crm_id:{$user->crm_id}";

        if ($user->role_id == 2 && isset($validated['in_work'])) {
            $in_work = $validated['in_work'];

            $manager = \App\Models\Manager::join('users', 'users.crm_id', 'managers.crm_id')
                ->select('managers.*', 'users.name')
                ->where('users.id', $id)
                ->first();
            $message = 'Сотрудник `' . $manager->name . '` crm_id:' . $manager->crm_id
                . ($in_work ? ' возобновил' : ' завершил') . ' работу';

            $manager->in_work = $in_work;
            $manager->save();
            Log::info($message);

            return response()->json([
                'status' => true,
                'data' => UserResource::make($user),
                'message' => $in_work ? 'Работа возобновлена' : 'Работа завершена',
            ]);
        }

        if ($user->role_id != 2 && $validated['role_id'] == 2) {
            $group = Group::firstOrNew(['name' => $user->name]);
            $group->alone = true;
            $group->save();
            ManagerGroup::firstOrCreate([
                'manager_id' => $user->id,
                'group_id' => $group->id,
            ]);
            $message .= ' стал менеджером';
        } elseif ($user->role_id == 2 && $validated['role_id'] != 2) {
            $data = UserTrait::destroy($id);
            if ($data != null) {
                return response()->json($data);
            }
            $message .= ' перестал быть менеджером';
        }

        $user->fill($validated);
        $user->save();
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => UserResource::make($user),
            'message' => $message,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $data = UserTrait::destroy($id);

        if ($data != null) {
            return response()->json($data);
        }

        $message = "Сотрудник `{$user->name}` crm_id:{$user->crm_id} удалён";
        $result = $user->delete();
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message,
        ]);
    }
}