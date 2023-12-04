<?php

namespace App\Traits;

use App\Http\Resources\CRM\DepartmentResource;
use App\Models\Group;
use App\Models\HiddenChatMessage;
use App\Models\ManagerGroup;
use App\Models\Participant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait UserTrait
{
  /**
   * Display the resource of the current user from CRM.
   */
  public static function current()
  {
    return \App\Http\Resources\CRM\UserResource::make(BX::call('user.current')['result']);
  }

  /**
   * Method for trying to find user:
   *    from CRM employers,
   *    from all CRM employers,
   *    from local `users` table;
   * Else returns default data
   * 
   * @param integer $user_id
   * @param string $email
   * @return object
   */
  public static function tryToDefineUserEverywhere($user_id, $email): object
  {
    $search = self::search();
    $managers = array_values(array_filter($search->data, fn($e) => $e->email == $email));
    unset($search);

    // $manager = !empty($managers) ? $managers[0] : null;

    // if (!isset($manager)) {
    //   $withFired = self::withFired();
    //   // $withFired = self::withFired(true);
    //   $managers = array_values(array_filter($withFired->data, fn($e) => $e->email == $email));
    //   unset($withFired);
    // }

    $manager = !empty($managers)
      ? $managers[0]
      : User::firstWhere('email', $email);

    if (!isset($manager)) {
      $manager = [
        'crm_id' => $user_id,
        'email' => $email,
        'name' => 'Неопределённый пользователь',
      ];
    }

    return (object) $manager;
  }

  public static function search()
  {
    $prefix = env('APP_PREFIX');
    if (Cache::store('file')->has("{$prefix}_users")) {
      return Cache::store('file')->get("{$prefix}_users");
    }

    $data = BX::firstBatch('user.search', [
      'USER_TYPE' => 'employee',
      'ACTIVE' => true,
    ]);
    $resource = \App\Http\Resources\CRM\UserResource::collection($data)->response()->getData();
    Cache::store('file')->forever("{$prefix}_users", $resource);

    return $resource;
  }

  public static function withFired($force = false)
  {
    $prefix = env('APP_PREFIX');
    // Cache::store('file')->forget("{$prefix}_all_users");
    if (!$force && Cache::store('file')->has("{$prefix}_all_users")) {
      return Cache::store('file')->get("{$prefix}_all_users");
    }

    $data = BX::firstBatch('user.get', [
      'USER_TYPE' => 'employee',
    ]);
    $resource = \App\Http\Resources\CRM\UserResource::collection($data)->response()->getData();
    Cache::store('file')->forever("{$prefix}_all_users", $resource);

    return $resource;
  }

  public static function departments()
  {
    $prefix = env('APP_PREFIX');
    if (Cache::store('file')->has("{$prefix}_departments")) {
      return Cache::store('file')->get("{$prefix}_departments");
    }

    $data = BX::firstBatch('department.get');
    $resource = DepartmentResource::collection($data)->response()->getData();
    Cache::store('file')->forever("{$prefix}_departments", $resource);

    return $resource;
  }

  /**
   * Remove the specified resource from storage.
   */
  public static function destroy($id)
  {
    $groups_ids = ManagerGroup::whereManagerId($id)
      ->orderBy('group_id')
      ->pluck('group_id')
      ->toArray();
    $groups = Group::join('manager_groups AS mg', 'mg.group_id', 'groups.id')
      ->whereIn('mg.group_id', $groups_ids)
      ->selectRaw('COUNT(mg.id) AS count, name')
      ->groupBy('groups.id')
      ->orderBy('groups.id')
      ->get();
    $groups_array = $groups->toArray();

    if ($groups->contains(fn($e) => $e->count == 1)) {
      $groups_alone = array_filter($groups_array, fn($e) => $e['count'] == 1);
      $names = array_map(fn($e) => $e['name'], $groups_alone);
      $message = 'Данный менеджер указан как единственный участник в группах: ';

      return [
        'status' => false,
        'data' => null,
        'message' => $message . implode(', ', $names),
      ];
    }

    $tickets = \App\Models\Ticket::join('managers', 'managers.user_id', 'tickets.new_manager_id')
      ->where('managers.id', $id)->select('tickets.id', 'tickets.reason_id')->get();
    $default_reason = \App\Models\Reason::first();

    // dd($id, $tickets);
    foreach ($tickets as $t) {
      $reason = \App\Models\Reason::find($t->reason_id) ?? $default_reason;

      $managers = TicketTrait::GetManagersForReason($reason->id);
      if (isset($managers)) {
        $current_manager = null;
        if (count($managers) > 1) {
          $responsive_id = TicketTrait::SelectResponsiveId($managers);
          if ($responsive_id > 0) {
            $current_manager = array_values(array_filter($managers, fn($m) => $m['user_id'] == $responsive_id))[0];
          }
        } else {
          $current_manager = $managers[0];
        }

        if ($current_manager != null) {
          UserTrait::changeTheResponsive($t->id, $current_manager->user_id);
        }
      }
    }

    // Deleting from `manager_groups` all rows where deleting user in not alone
    $delete_group_ids = array_filter($groups_ids, function ($e, $key) use ($groups_array) {
      if ($groups_array[$key]['count'] > 1)
        return $e;
    }, ARRAY_FILTER_USE_BOTH);
    ManagerGroup::whereIn('id', $delete_group_ids)->delete();

    return null;
  }

  public static function changeTheResponsive($ticket_id, $user_id)
  {
    $ticket = \App\Models\Ticket::findOrFail($ticket_id);

    if (!isset($ticket)) {
      return response()->json([
        'status' => false,
        'data' => $ticket,
        'message' => 'Тикет не доступен для редактирования'
      ]);
    } elseif ($ticket->new_user_id == $user_id) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Нельзя назначить ответственным самого же создателя тикета'
      ]);
    }

    $users_with_email = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', [$ticket->new_user_id, $user_id, $ticket->new_manager_id])
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();
    // dd($validated, [$ticket->new_user_id, $validated['user_id'], $ticket->new_manager_id], $users_with_email);

    $creator = $users_with_email->where('id', $ticket->new_user_id)->first();
    $manager = $users_with_email->where('id', $user_id)->first();
    $participant = $users_with_email->where('id', $ticket->new_manager_id)->first();

    // dd($creator, $manager, $participant);
    unset($users_with_email);

    $ticket->new_manager_id = $manager->id;
    $ticket->save();

    $old_participant = Participant::firstOrNew([
      'ticket_id' => $ticket_id,
      'user_id' => $user_id,
    ]);
    if ($old_participant->exists) {
      $old_participant->delete();
    }

    $new_participant = Participant::firstOrNew([
      'ticket_id' => $ticket_id,
      'user_id' => $participant->id,
    ]);
    if (!$new_participant->exists) {
      $new_participant->user_crm_id = $participant->crm_id;
      $new_participant->save();
    }

    $call_required = \App\Models\Reason::whereId($ticket->reason_id)->whereCallRequired(true)->exists();
    TicketTrait::SendNotification($manager->id, "Вы стали ответственным за тикет №{$ticket->id}", $ticket->id, $call_required);

    $user = UserTrait::tryToDefineUserEverywhere($manager->id, $manager->email);

    HiddenChatMessage::create([
      'content' => "Новый ответственный: {$user->name}",
      'user_crm_id' => 0,
      'new_user_id' => 1,
      'ticket_id' => $ticket->id,
      'created_at' => Carbon::now(),
    ]);

    $result = [
      'ticket_id' => $ticket->id,
      'new_manager' => $user,
      'new_manager_id' => $manager->id,
      'new_participant_id' => $ticket->new_manager_id,
    ];

    // TicketTrait::SendMessageToWebsocket("{$manager->crm_id}.ticket", [
    //   'participant' => $result,
    // ]);
    // $ticket->reason = \App\Models\Reason::find($ticket->reason_id)->name;
    // $ticket->user = UserTrait::tryToDefineUserEverywhere($creator->crm_id, $creator->email);
    // $ticket->manager = UserTrait::tryToDefineUserEverywhere($manager->crm_id, $manager->email);

    // $bx_crm_data = DB::table('bx_crms')
    //   ->join('bx_users AS bx', 'bx.bx_crm_id', 'bx_crms.id')
    //   ->where('bx.user_id', $creator->id)
    //   ->select('bx_crms.name', 'bx_crms.acronym', 'bx_crms.domain')
    //   ->first();
    // $ticket->bx_name = $bx_crm_data->name;
    // $ticket->bx_acronym = $bx_crm_data->acronym;
    // $ticket->bx_domain = $bx_crm_data->domain;

    // $resource = \App\Http\Resources\TicketResource::make($ticket);
    TicketTrait::SendMessageToWebsocket("{$manager->email}.participant", [
      // 'ticket' => $resource,
      'participant' => $result,
    ]);
    TicketTrait::SendMessageToWebsocket("{$creator->email}.participant", [
      'participant' => $result,
    ]);
    // TicketTrait::SendMessageToWebsocket("{$participant->email}.participant", [
    //   'participant' => $result,
    // ]);
    $part_emails = Participant::join('users', 'users.id', 'participants.user_id')
      ->whereTicketId($ticket->id)
      // ->join('bx_users', 'bx_users.user_id', 'users.id')
      // ->pluck('bx_users.crm_id')->toArray();
      ->pluck('users.email')->toArray();
    foreach ($part_emails as $email) {
      TicketTrait::SendMessageToWebsocket("{$email}.participant", [
        'participant' => $result,
      ]);
    }

    return $result;
  }
}