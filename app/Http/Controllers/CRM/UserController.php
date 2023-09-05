<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\BX;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
  public function check()
  {
    $check = BX::resetDataExt(request());
    $data = BX::call('user.current')['result'];

    $auth_user = \Illuminate\Support\Facades\Auth::user();

    if (
      !empty($auth_user)
      && $data['ID'] == $auth_user->crm_id
    ) {
      return response()->json([
        'status' => false,
        'data' => null,
      ]);
    }

    $user = [
      'crm_id' => $data['ID'],
      'name' => trim($data['LAST_NAME'] . " " . $data['NAME'] . " " . $data['SECOND_NAME']),
      'avatar' => $data['PERSONAL_PHOTO'] ?? null,
      'post' => trim($data['WORK_POSITION'] ?? null),
      'departments' => $data['UF_DEPARTMENT'] ?? [],
      'inner_phone' => $data['UF_PHONE_INNER'] ?? 0,
    ];

    $auth = User::whereCrmId($user['crm_id'])->firstOrNew([
      'crm_id' => $user['crm_id'],
      'name' => $user['name'],
    ]);
    $user['user_id'] = $auth->id;

    $user['is_admin'] = BX::call('user.admin')['result'];
    $manager = \App\Models\Manager::whereCrmId($auth->crm_id)
      ->orderBy(DB::raw("CASE WHEN role_id = 2 THEN 1 WHEN role_id = 3 THEN 2 ELSE 3 END"))
      ->first();
    if (!empty($manager)) {
      $user['role_id'] = $manager->role_id;
      $user['in_work'] = $manager->in_work;
    }

    return response()->json([
      'status' => true,
      'data' => [
        'user' => $user,
        'token' => $auth->createToken("auth")->plainTextToken,
      ],
    ]);
  }

  /**
   * Display a listing of the resource.
   */
  public function search()
  {
    $data = UserTrait::search();

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }
}