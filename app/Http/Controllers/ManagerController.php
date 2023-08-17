<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagerRequest;
use App\Http\Requests\UpdateManagerRequest;
use App\Http\Resources\ManagerResource;
use App\Models\Group;
use App\Models\Manager;
use App\Models\User;
use App\Traits\UserTrait;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ManagerController extends Controller
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

        $data = \Illuminate\Support\Facades\DB::table('managers')
            ->join('users', 'users.crm_id', 'managers.crm_id')
            ->where('role_id', '>', 1)
            ->when(!empty($role), function ($q) use ($role) {
                $q->whereRoleId($role);
            })
            ->when(!empty($id) || !empty($name), function ($q) use ($id, $name) {
                $q->whereId($id)->orWhereRaw('LOWER(users.name) LIKE ?', ["%{$name}%"]);
            })
            ->select('managers.*', 'users.name')
            ->paginate($limit < 1 ? 100 : $limit);

        // $departments = UserTrait::departments();
        // dd($departments);
        //TODO доделать подразделения и отобразить список названий со ссылками
        // $departments_collection = array();

        // foreach ($departments->data as $d) {
        //     $departments_collection[$d->crm_id] = $d;
        // }
        // unset($departments);

        // foreach ($data as $manager) {
        //     $manager->user = $departments_collection[$manager->user_id];
        //     $manager->manager = $departments_collection[$manager->manager_id];
        //   }
        //   unset($departments_collection);

        return response()->json([
            'status' => true,
            'data' => ManagerResource::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManagerRequest $request)
    {
        $user = User::firstOrNew($request->except(['role_id']));
        $manager = Manager::firstOrNew($request->except(['name']));
        // $user = Manager::with('user:id,name')->firstOrCreate($request->validated());
        // return response()->json([
        //     'status' => true,
        //     'data' => $user,
        //     'message' => 'test',
        // ]);
dd($user, $manager, $request);
        if ($manager->exists) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Данному пользователю уже выдана такая роль',
            ]);
        }
        $user->save();
        $manager->save();

        $message = 'Сотрудник `' . $user->name . '` crm_id:' .
            $manager->crm_id . ' добавлен с ролью `' .
            \App\Models\Role::findOrFail($manager->role_id)->name . '`';
        Log::info($message);

        if ($manager->role_id == 2) {
            $group = Group::firstOrNew(['name' => $user->name]);
            $group->alone = true;
            $group->save();
            \App\Models\ManagerGroup::firstOrCreate([
                'manager_id' => $manager->id,
                'group_id' => $group->id,
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => ManagerResource::make($manager),
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

        $user = Manager::join('users', 'users.crm_id', 'managers.crm_id')
            ->select('managers.*', 'users.name')
            ->findOrFail($id);
        $message = 'Сотрудник `' . $user->name . '` crm_id:' . $user->crm_id;

        if ($user->role_id != 2 && $validated['role_id'] == 2) {
            $group = Group::firstOrNew(['name' => $user->name]);
            $group->alone = true;
            $group->save();
            \App\Models\ManagerGroup::firstOrCreate([
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
            'data' => ManagerResource::make($user),
            'message' => $message,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $manager = Manager::join('users', 'users.crm_id', 'managers.crm_id')
            ->select('managers.*', 'users.name')
            ->findOrFail($id);

        $data = UserTrait::destroy($id);

        if ($data != null) {
            return response()->json($data);
        }

        $message = '`' . $manager->name . '` crm_id:' . $manager->crm_id . ' удалён';
        $result = $manager->delete();
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message,
        ]);
    }
}