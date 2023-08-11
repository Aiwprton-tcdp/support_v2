<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\BX;
use App\Traits\UserTrait;

class UserController extends Controller
{
  public function check()
  {
    BX::resetDataExt(request());
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

    $user['is_admin'] = BX::call('user.admin')['result'];

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