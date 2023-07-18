<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CRM\UserResource;
use App\Models\User;
use App\Traits\BX;
use App\Models\VerifiedUser;
use App\Traits\UserTrait;
use Auth;

class IndexAPIController extends Controller
{
  public function __invoke()
  {
    try {
      // dd(\Illuminate\Support\Facades\Auth::id());
      $token = '';
      $user_id = 0;
      $check = BX::setDataE($_REQUEST); // получает авторизацию битрикса
      // $user = UserTrait::current(); // получает конкретного пользователя по авторизации
      $data = BX::call('user.current')['result'];
      $user = [
        'crm_id' => $data['ID'],
        'name' => trim($data['LAST_NAME'] . " " . $data['NAME'] . " " . $data['SECOND_NAME']),
        'avatar' => $data['PERSONAL_PHOTO'] ?? null,
        'post' => trim($data['WORK_POSITION'] ?? null),
        'departments' => $data['UF_DEPARTMENT'] ?? 0,
        'inner_phone' => $data['UF_PHONE_INNER'] ?? 0,
      ];
      // dd(\Illuminate\Support\Facades\Auth::id());
      $auth = User::whereCrmId($user['crm_id'])->firstOrNew([
        'crm_id' => $user['crm_id'],
        'name' => $user['name'],
      ]);

      if (!$auth->exists) {
        $auth->save();
        $token = $auth->createToken("auth")->plainTextToken;
      } else {
        // dd(Auth::id());
        $user_id = Auth::id();
      }

      $data = $_REQUEST;
      return view('welcome', compact('user', 'data', 'token', 'user_id'));
    } catch (\Exception $er) {
      return $er->getMessage();
    }
  }
}