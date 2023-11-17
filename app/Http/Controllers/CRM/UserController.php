<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\BX;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
  public function check()
  {
    $token = request('token');
    if (isset($token) && empty(request('auth'))) {
      if (Auth::guest()) {
        return $this->checkByToken($token);
      } else {
        return $this->checkByAuth();
      }
    }

    if (empty($token) || $token == 'null') {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Token is invalid'
      ]);
    }

    // dd(request('sid')['DOMAIN']);
    $domain = request('sid')['DOMAIN'];
    if (!isset($domain)) {
      dd('Ошибка аутентификации: не указан домен');
    }

    $bxCrm = \App\Models\BxCrm::firstWhere('domain', $domain);
    // dd($bxCrm);
    if (!isset($bxCrm)) {
      dd('Ошибка аутентификации: не удалось найти домен в базе данных');
    }

    $check = BX::resetDataExt(request());
    $data = BX::call('user.current')['result'];

    $userByToken = DB::table('personal_access_tokens')
      ->join('users', 'users.id', 'personal_access_tokens.tokenable_id')
      // ->whereNotNull('personal_access_tokens.token')
      ->where('personal_access_tokens.id', explode('|', $token)[0])
      ->orderByDesc('users.id')
      ->first();
    // dd($token, explode('|', $token)[0], $userByToken, $data['EMAIL']);
    // если EMAIL не совпадают, то выпускаем новый токен

    $auth_user = Auth::user();
    // dd($auth_user);
    if (
      !empty($auth_user)
      && $data['ID'] == $auth_user->crm_id
      && $auth_user->bx_crm_id != null
      && $auth_user->email != ""
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
      'email' => $data['EMAIL'],
      'post' => trim($data['WORK_POSITION'] ?? null),
      'departments' => $data['UF_DEPARTMENT'] ?? [],
      'inner_phone' => $data['UF_PHONE_INNER'] ?? 0,
    ];

    $auth = User::firstOrNew([
      'email' => $user['email'],
      // 'name' => $user['name'],
      // 'crm_id' => $user['crm_id'],
      // 'bx_crm_id' => $bxCrm->id,
    ]);
    // if ($token != @$userByToken->token)
    if ($user['email'] != @$userByToken->email)
      $token = $auth->createToken("auth")->plainTextToken;

    if (!$auth->exists) {
      // if ($auth->bx_crm_id == null || $auth->email == "") {
      // if ($auth->email == "") {
      // $auth->fill([
      //   'email' => $user['email'],
      // ]);
      // $auth->bx_crm_id = $bxCrm->id;
      $auth->crm_id = $user['crm_id'];
      $auth->name = $user['name'];
      $auth->save();
      \App\Models\BxUser::create([
        'user_id' => $auth->id,
        'crm_id' => $user['crm_id'],
        'bx_crm_id' => $bxCrm->id,
      ]);
    }
    $user['user_id'] = $auth->id;
    $user['in_crm'] = true;

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
        'token' => $token,
      ],
    ]);
  }

  public function checkByToken($token)
  {
    if (empty($token) || $token == 'null') {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'The token is invalid'
      ]);
    }

    $userByToken = DB::table('personal_access_tokens')
      ->join('users', 'users.id', 'personal_access_tokens.tokenable_id')
      ->join('bx_users', 'bx_users.user_id', 'users.id')
      // ->whereNotNull('personal_access_tokens.token')
      ->where('personal_access_tokens.id', explode('|', $token)[0])
      ->orderByDesc('users.id')
      ->first();
    if (!isset($userByToken)) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'The token is not up-to-date'
      ]);
    }
    // dd($userByToken);
    $auth = User::firstOrNew([
      'email' => $userByToken->email,
    ]);
    // if ($token != $userByToken->token)
    // if ($user['email'] != @$userByToken->email)
    $token = $auth->createToken("auth")->plainTextToken;

    $user = [
      'bx_crm_id' => $userByToken->bx_crm_id,
      'crm_id' => $userByToken->crm_id,
      'email' => $userByToken->email,
      'user_id' => $userByToken->user_id,
      'name' => $userByToken->name,
      'in_crm' => false,
    ];



    // $user['avatar'] = $data['ID'];
    // $user['is_admin'] = $data['ID'];
    // 'avatar' => $data['PERSONAL_PHOTO'] ?? null,
    // $user['is_admin'] = BX::call('user.admin')['result'];



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
        'token' => $token,
      ],
    ]);
  }

  public function checkByAuth()
  {
    $userByAuth = DB::table('users')
      ->join('bx_users', 'bx_users.user_id', 'users.id')
      ->where('users.id', Auth::user()->id)
      ->orderByDesc('users.id')
      ->first();
    $user = [
      'bx_crm_id' => $userByAuth->bx_crm_id,
      'crm_id' => $userByAuth->crm_id,
      'email' => $userByAuth->email,
      'user_id' => $userByAuth->user_id,
      'name' => $userByAuth->name,
      'in_crm' => false,
    ];



    // $user['avatar'] = $data['ID'];
    // $user['is_admin'] = $data['ID'];
    // 'avatar' => $data['PERSONAL_PHOTO'] ?? null,
    // $user['is_admin'] = BX::call('user.admin')['result'];



    $auth = User::firstOrNew([
      'email' => $userByAuth->email,
    ]);
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
      ],
    ]);
  }

  /**
   * Display a listing of the resource.
   */
  public function search()
  {
    $data = UserTrait::search();

    // dd($data);
    // $search = UserTrait::search();
    // $users_collection = array();

    // $users_by_emails = DB::table('users')
    //   // ->join('bx_users', 'bx_users.user_id', 'users.id')
    //   ->whereIn('users.email', array_map(fn($d) => $d->email, $data->data))
    //   ->pluck('users.id', 'users.email');
    // // dd($users_by_emails, $data);
    $users_with_emails = DB::table('users')
      ->join('bx_users', 'bx_users.user_id', 'users.id')
      // ->join('bx_crms', 'bx_crms.id', 'bx_users.bx_crm_id')
      // ->where('bx_crms.domain', env('CRM_DOMAIN'))
      ->whereIn('users.email', array_map(fn($d) => $d->email, $data->data))
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();
    // dd($users_with_emails);
    foreach ($data->data as $user) {
      $u = $users_with_emails->where('email', $user->email)->first();
      $user->user_id = @$u->id;
    }
    // dd($users_with_emails, $data);
    // unset($search);

    // $all_ids = array_merge(...array_map(fn($t) => [$t->new_user_id, $t->new_manager_id], $data->all()));
    // $users_with_emails = DB::table('users')
    //   ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
    //   ->whereIn('users.id', array_values(array_unique($all_ids)))
    //   ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
    //   ->get();
    // unset($all_ids);

    // foreach ($data as $ticket) {
    //   $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
    //   $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
    //   $ticket->user = $users_collection[$u->email]
    //     ?? UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
    //   $ticket->manager = $users_collection[$m->email]
    //     ?? UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);
    // }
    // unset($users_with_emails, $users_collection);

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }

  public function generateToken($id)
  {
    $token = DB::table('personal_access_tokens')
      ->where('personal_access_tokens.tokenable_id', $id)
      ->select('personal_access_tokens.id')
      ->orderByDesc('personal_access_tokens.id')
      ->first();

    if (!isset($token)) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'User has no any authorizations',
      ]);
    }
    $hash = hash('sha256', $token->id);

    return response()->json([
      'status' => true,
      'data' => "{$token->id}|{$hash}"
    ]);
  }

  public function setUsersIds()
  {
    // $data = UserTrait::search();
    $data = UserTrait::withFired();
    $users = User::all();

    // dd($users[10]);
    // foreach ($data as $d) {
    foreach ($users as $u) {
      // if ($u->name == "Система")
      //   continue;

      $user_id = $u->id;
      if ($u->name == "Система") {
        $crm_id = 0;
      } else {
        $f = array_values(array_filter($data->data, fn($d) => $d->name == $u->name && $d->crm_id == $u->crm_id));
        if (count($f) == 0)
          continue;
        $crm_id = $f[0]->crm_id;

        User::whereId($user_id)->orWhere('crm_id', $crm_id)->orWhere('name', $u->name)->update([
          'name' => $f[0]->name,
          'email' => $f[0]->email,
        ]);
      }

      \App\Models\Ticket::whereUserId($crm_id)->update(['new_user_id' => $user_id]);
      \App\Models\Ticket::whereManagerId($crm_id)->update(['new_manager_id' => $user_id]);

      \App\Models\ResolvedTicket::whereUserId($crm_id)->update(['new_user_id' => $user_id]);
      \App\Models\ResolvedTicket::whereManagerId($crm_id)->update(['new_manager_id' => $user_id]);

      \App\Models\Message::whereUserCrmId($crm_id)->update(['new_user_id' => $user_id]);
      \App\Models\HiddenChatMessage::whereUserCrmId($crm_id)->update(['new_user_id' => $user_id]);

      \App\Models\Manager::whereCrmId($crm_id)->update(['user_id' => $user_id]);
      \App\Models\Participant::whereUserCrmId($crm_id)->update(['user_id' => $user_id]);
    }
    // }
    // dd($users, $data);

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }





  //TODO добавить при авторизации email
  //TODO сделать отправку HTTP-запроса в Битрикс, авторизоваться по данным от него и получить список параметров:
  //TODO AUTH_ID, AUTH_EXPIRES, REFRESH_ID, member_id, status, PLACEMENT
  //TODO по данным из этого списка попытаться проверить статус админа для авторизовавшегося пользователя
  //TODO сохранять список этих параметров в сессию, чтобы использовать при перезагрузке



  public static function AuthInBitrix()
  {
    $config = [
      'base_uri' => env('CRM_URL'),
      'cookies' => true,
      'headers' => [
        'Accept-Language' => 'ru,en-US' // Если не задать - будут выдаватся страницы на английском языке
      ]
    ];
    $login = request('login'); // goncov@юдл.рф
    $password = request('password'); // 6574959059
    // dd($login, $password);
    $client = new \GuzzleHttp\Client($config);
    $res = $client->post('auth/', [
      'form_params' => [
        'AUTH_FORM' => 'Y',
        'TYPE' => 'AUTH',
        'backurl' => '/marketplace/app/' . env('MARKETPLACE_ID') . '/',
        'USER_LOGIN' => $login,
        'USER_PASSWORD' => $password,
        'USER_REMEMBER' => 'Y',
      ]
    ])->getBody()->getContents();
    $res = $client->get('/marketplace/app/' . env('MARKETPLACE_ID') . '/');
    // ])->getBody()->getContents();
    // $name = json_decode($res, true);
    // dd($res);
    // $check = BX::resetDataExt(request());
    // $data = BX::call('user.current')['result'];
    // return $_REQUEST;
    // return request();
    return $res->getHeaders();
  }
}