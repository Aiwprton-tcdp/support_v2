<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CRM\UserResource;
use App\Models\Manager;
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

      $auth = User::whereCrmId($user['crm_id'])->firstOrNew([
        'crm_id' => $user['crm_id'],
        'name' => $user['name'],
      ]);

      if (!$auth->exists) {
        $auth->save();
        $token = $auth->createToken("auth")->plainTextToken;
      }

      $user['is_admin'] = BX::call('user.admin')['result'];
      $manager = Manager::whereCrmId($auth->crm_id)->first();
      if (!empty($manager)) {
        $user['role_id'] = $manager->role_id;
      }

      // $data = $_REQUEST;
      return view('welcome', compact('user', 'token', 'ticket_id'));
    } catch (\Exception $er) {
      return $er->getMessage();
    }
  }
}