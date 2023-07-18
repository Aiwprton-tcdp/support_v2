<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CRM\UserResource;
use App\Models\User;
use App\Traits\BX;
use App\Traits\UserTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
  /**
   * Display the resource of the current user from CRM.
   */
  public function current()
  {
    return response()->json([
      'status' => true,
      'data' => \App\Traits\UserTrait::current()
    ]);
  }

  public function check()
  {
    BX::resetDataExt(request());
    $data = BX::call('user.current')['result'];

    $auth_user = \Illuminate\Support\Facades\Auth::user();
    // dd(isset($auth_user), empty($auth_user));
    // dd($data['ID'], \Illuminate\Support\Facades\Auth::user()->crm_id);

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
      'departments' => $data['UF_DEPARTMENT'] ?? 0,
      'inner_phone' => $data['UF_PHONE_INNER'] ?? 0,
    ];

    $auth = User::whereCrmId($user['crm_id'])->firstOrNew([
      'crm_id' => $user['crm_id'],
      'name' => $user['name'],
    ]);

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
    // $name = htmlspecialchars(trim(request('name')));
    // $last_name = htmlspecialchars(trim(request('last_name')));

    // if (Cache::store('file')->has('crm_users')) {
    //   $data = Cache::store('file')->get('crm_users');
    //   return response()->json([
    //     'status' => true,
    //     'data' => $data
    //   ]);
    // }

    // $data = BX::firstBatch('user.search', [
    //   'USER_TYPE' => 'employee',
    //   // 'NAME_SEARCH' => trim($last_name . ' ' . $name),
    //   'ACTIVE' => true,
    // ]);
    // $resource = UserResource::collection($data)->response()->getData();
    // Cache::store('file')->put('crm_users', $resource, 10800);

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }
}