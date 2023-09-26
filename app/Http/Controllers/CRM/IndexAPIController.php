<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\BxCrm;
use App\Traits\BX;
use Illuminate\Support\Facades\DB;

class IndexAPIController extends Controller
{
  public function __invoke()
  {
    try {
      // dd($_REQUEST);
      $domain = $_REQUEST['DOMAIN']; // ?? null;
      if (!isset($domain)) {
        dd('Ошибка аутентификации: не указан домен');
      }

      $bxCrm = BxCrm::firstWhere('domain', $domain);
      // dd($bxCrm);
      if (!isset($bxCrm)) {
        dd('Ошибка аутентификации: не удалось найти домен в базе данных');
      }

      $ticket_id = isset($_REQUEST['PLACEMENT_OPTIONS']) && isset(json_decode($_REQUEST['PLACEMENT_OPTIONS'])->id)
        ? intval(json_decode($_REQUEST['PLACEMENT_OPTIONS'])->id)
        : 0;
      $token = '';
      $check = BX::setDataE($_REQUEST);
      $data = BX::call('user.current')['result'];
      $user = [
        'crm_id' => $data['ID'],
        'name' => trim($data['LAST_NAME'] . " " . $data['NAME'] . " " . $data['SECOND_NAME']),
        'avatar' => $data['PERSONAL_PHOTO'] ?? null,
        'email' => $data['EMAIL'],
        'post' => trim($data['WORK_POSITION'] ?? null),
        'departments' => $data['UF_DEPARTMENT'] ?? 0,
        'inner_phone' => $data['UF_PHONE_INNER'] ?? 0,
      ];

      $auth = \App\Models\User::firstOrNew([
        // 'name' => $user['name'],
        'email' => $user['email'],
        // 'crm_id' => $user['crm_id'],
        // 'bx_crm_id' => $bxCrm->id,
      ]);

      $alternative_auth = \App\Models\User::firstOrNew([
        'crm_id' => $user['crm_id'],
      ]);

      // dd($alternative_auth->exists);
      // dd($auth->id, $alternative_auth->id);
      // dd($auth, $alternative_auth);
      // dd($auth);
      // if ($auth->exists && $auth->bx_crm_id == null) {
      //   $auth->bx_crm_id = $bxCrm->id;
      //   $auth->save();
      // } else
      if (!$auth->exists) {
        if ($alternative_auth->exists) {
          $alternative_auth->email = $user['email'];
          $alternative_auth->name = $user['name'];
          $alternative_auth->save();
          $token = $alternative_auth->createToken("auth")->plainTextToken;
        } else {
          $auth->crm_id = $user['crm_id'];
          $auth->name = $user['name'];
          $auth->save();
          $token = $auth->createToken("auth")->plainTextToken;
        }
      }
      $auth_id = $auth->id ?? $alternative_auth->id;

      $bx_user = \App\Models\BxUser::firstOrNew([
        'crm_id' => $user['crm_id'],
        'bx_crm_id' => $bxCrm->id,
      ]);
      $bx_user->user_id = $auth_id;
      $bx_user->save();

      $user['user_id'] = $auth_id;

      $user['is_admin'] = BX::call('user.admin')['result'];
      $manager = \App\Models\Manager::whereCrmId($auth->crm_id)
        ->orderBy(DB::raw("CASE WHEN role_id = 2 THEN 1 WHEN role_id = 3 THEN 2 ELSE 3 END"))
        ->first();
      if (!empty($manager)) {
        $user['role_id'] = $manager->role_id;
        $user['in_work'] = $manager->in_work;
      }

      return view('welcome', compact('user', 'token', 'ticket_id'));
    } catch (\Exception $er) {
      return $er->getMessage();
    }
  }
}