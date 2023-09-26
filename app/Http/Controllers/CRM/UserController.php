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

    $auth_user = \Illuminate\Support\Facades\Auth::user();
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
    
    // dd($data);
    // $search = UserTrait::search();
    // $users_collection = array();

    $users_by_emails = DB::table('users')
      // ->join('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.email', array_map(fn($d) => $d->email, $data->data))
      ->pluck('users.id', 'users.email');
      // dd($users_by_emails, $data);
    foreach ($data->data as $user) {
      $user->user_id = @$users_by_emails[$user->email];
    }
    // dd($users_by_emails, $data);
    // unset($search);

    // $all_ids = array_merge(...array_map(fn($t) => [$t->new_user_id, $t->new_manager_id], $data->all()));
    // $users_with_emails = DB::table('users')
    //   ->join('bx_users', 'bx_users.user_id', 'users.id')
    //   ->whereIn('users.id', array_values(array_unique($all_ids)))
    //   ->select('users.id', 'users.email', 'bx_users.crm_id')
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

  public function setUsersIds()
  {
    $data = UserTrait::search();
    $users = User::all();

    // dd($data);
    // foreach ($data as $d) {
    foreach ($users as $u) {
      // if ($u->name == "Система")
      //   continue;

      $user_id = $u->id;
      if ($u->name == "Система") {
        $crm_id = 0;
      } else {
        $f = array_values(array_filter($data->data, fn($d) => $d->name == $u->name));
        if (count($f) == 0) continue;
        $crm_id = $f[0]->crm_id;
      }
      \App\Models\Ticket::whereUserId($crm_id)->update(['new_user_id' => $user_id]);
      \App\Models\Ticket::whereManagerId($crm_id)->update(['new_manager_id' => $user_id]);

      \App\Models\ResolvedTicket::whereUserId($crm_id)->update(['new_user_id' => $user_id]);
      \App\Models\ResolvedTicket::whereManagerId($crm_id)->update(['new_manager_id' => $user_id]);

      \App\Models\Message::whereUserCrmId($crm_id)->update(['new_user_id' => $user_id]);
      \App\Models\HiddenChatMessage::whereUserCrmId($crm_id)->update(['new_user_id' => $user_id]);

      \App\Models\Participant::whereUserCrmId($crm_id)->update(['user_id' => $user_id]);
    }
    // }
    // dd($users, $data);

    return response()->json([
      'status' => true,
      'data' => $data
    ]);
  }
}