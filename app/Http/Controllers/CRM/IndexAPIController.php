<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Traits\BX;
use Illuminate\Support\Facades\DB;

class IndexAPIController extends Controller
{
  public function __invoke()
  {
    try {
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
        'post' => trim($data['WORK_POSITION'] ?? null),
        'departments' => $data['UF_DEPARTMENT'] ?? 0,
        'inner_phone' => $data['UF_PHONE_INNER'] ?? 0,
      ];

      $auth = \App\Models\User::whereCrmId($user['crm_id'])->firstOrNew([
        'crm_id' => $user['crm_id'],
        'name' => $user['name'],
      ]);

      if (!$auth->exists) {
        $auth->save();
        $token = $auth->createToken("auth")->plainTextToken;
      }

      $user['is_admin'] = BX::call('user.admin')['result'];
      $manager = \App\Models\Manager::whereCrmId($auth->crm_id)
        ->orderBy(DB::raw("CASE WHEN role_id = 2 THEN 1 WHEN role_id = 3 THEN 2 ELSE 3 END"))
        ->first();
      if (!empty($manager)) {
        $user['role_id'] = $manager->role_id;
      }

      return view('welcome', compact('user', 'token', 'ticket_id'));
    } catch (\Exception $er) {
      return $er->getMessage();
    }
  }
}