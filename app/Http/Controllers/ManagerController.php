<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagerRequest;
use App\Http\Requests\UpdateManagerRequest;
use App\Http\Resources\ManagerResource;
use App\Models\Group;
use App\Models\Manager;
use App\Models\User;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\Log;

class ManagerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $name = \Illuminate\Support\Str::lower($this->prepare(request('name')));
        $id = intval($this->prepare(request('id')));
        $role = intval($this->prepare(request('role')));
        $limit = intval($this->prepare(request('limit')));

        $data = \Illuminate\Support\Facades\DB::table('managers')
            ->join('users', 'users.id', 'managers.user_id')
            ->where('role_id', '>', 1)
            ->when($role > 1, fn($q) => $q->whereRoleId($role))
            ->when(
                !empty($id) || !empty($name),
                fn($q) => $q->where('managers.id', $id)
                    ->orWhereRaw('LOWER(users.name) LIKE ?', ["%{$name}%"])
            )
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
        // dd(\App\Models\BxCrm::where('domain', env('CRM_DOMAIN'))->first());
        $validated = $request->validated();
        $user = User::firstWhere('email', $validated['email']);

        if (!isset($user)) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Данный пользователь не прошёл аутентификацию в интеграции',
            ]);
        }

        $manager = Manager::firstOrNew([
            'user_id' => $user->id,
            'role_id' => $validated['role_id'],
        ]);
        // $manager = Manager::firstOrNew($request->except(['crm_id', 'name']));
        // $user->fill($validated);
        // dd($user, $manager);
        // $user = Manager::with('user:id,name')->firstOrCreate($request->validated());
        // return response()->json([
        //     'status' => true,
        //     'data' => $user,
        //     'message' => 'test',
        // ]);
// dd($user, $manager, $request);
        if ($manager->exists) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Данному пользователю уже выдана такая роль',
            ]);
        }

        $bx_user = \App\Models\BxUser::join('bx_crms', 'bx_crms.id', 'bx_users.bx_crm_id')
            ->whereUserId($user->id)
            ->where('domain', env('CRM_DOMAIN'))->first();
        // dd($bx_user);

        $validated['crm_id'] = $bx_user->crm_id;
        $user->fill($validated);
        $manager->fill($validated);
        // dd($user, $manager);
        $user->save();
        $manager->save();

        $message = "{$user->name} добавлен с ролью " .
            \App\Models\Role::findOrFail($manager->role_id)->name;
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

        $user = Manager::join('users', 'users.id', 'managers.user_id')
            ->select('managers.*', 'users.name')//, 'users.crm_id')
            ->findOrFail($id);
        $message = $user->name;

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
        $user = Manager::join('users', 'users.id', 'managers.user_id')
            ->select('managers.*', 'users.name')
            ->findOrFail($id);

        $data = UserTrait::destroy($id);

        if ($data != null) {
            return response()->json($data);
        }

        $message = "{$user->name} удалён";
        $result = $user->delete();
        Log::info($message);

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message,
        ]);
    }
}