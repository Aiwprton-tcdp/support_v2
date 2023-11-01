<?php

namespace App\Traits;

use App\Http\Resources\TicketResource;
use App\Models\BxCrm;
use App\Models\HiddenChatMessage;
use App\Models\Manager;
use App\Models\Participant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait TicketTrait
{
  public static function GetReason($message)
  {
    $client = new \GuzzleHttp\Client();
    $res = $client->post(env('NLP_URL'), ['form_params' => ['message' => $message]])->getBody()->getContents();
    $name = json_decode($res, true);
    $reason = \App\Models\Reason::firstWhere('name', $name);
    return $reason;
  }

  public static function GetManagersForReason(int $reason_id): mixed
  {
    $managers = \App\Models\Reason::join('groups', 'groups.id', 'reasons.group_id')
      ->join('manager_groups', 'manager_groups.group_id', 'groups.id')
      ->join('managers', 'managers.id', 'manager_groups.manager_id')
      ->rightJoin('users', 'users.id', 'managers.user_id')
      ->join('bx_users', 'bx_users.user_id', 'users.id')
      ->where('reasons.id', $reason_id)
      ->where('managers.in_work', true)
      // ->orWhere('groups.default', 1)
      ->select('bx_users.crm_id', 'managers.user_id', 'users.name', 'reasons.id AS reason_id', 'reasons.weight', 'reasons.name AS reason')
      ->groupBy('reasons.id', 'groups.id', 'manager_groups.id', 'managers.id', 'users.id', 'bx_users.id')
      ->get();

    if (count($managers) == 0) {
      $managers = \App\Models\Reason::join('groups', 'groups.id', 'reasons.group_id')
        ->join('manager_groups', 'manager_groups.group_id', 'groups.id')
        ->join('managers', 'managers.id', 'manager_groups.manager_id')
        ->rightJoin('users', 'users.id', 'managers.user_id')
        ->join('bx_users', 'bx_users.user_id', 'users.id')
        ->where('groups.default', 1)
        ->select('bx_users.crm_id', 'managers.user_id', 'users.name', 'reasons.id AS reason_id', 'reasons.weight', 'reasons.name AS reason')
        ->groupBy('users.id', 'groups.id', 'manager_groups.id', 'managers.id', 'reasons.id', 'bx_users.id')
        ->get();
    }
    // dd($managers);
    //TODO фильтруем менеджеров из актуальной группы
    $fromCurrent = array_filter($managers->all(), fn($m) => isset($m['reason']));
    // dd($managers, $fromCurrent, $managers[0]);

    //TODO если там пусто, то берём активных из оставшихся (из дефолтной группы)
    $map = $fromCurrent;
    if (count($map) == 0) {
      $map = $managers;
    }
    //TODO если таких нет, то берём всех
    $count = count($map);
    if ($count == 0) {
      return null;
    } elseif ($count == 1) {
      // dd($map[array_key_first($map)]);
      return $map;
      // return $map[array_key_first($map)]->toArray();
    }
    //TODO считаем веса и возвращаем менеджера с меньшим весом

    $reason = \App\Models\Reason::findOrFail($reason_id);
    foreach ($map as $m) {
      $m['reason_id'] = $reason->id;
      $m['weight'] = $reason->weight;
      // $m['reason'] = $reason->name;
    }
    // dd($reason, $map);
    return $map;
    // dd(array_map(fn($m) => $m['crm_id'], $map));
    // $reasons = \Illuminate\Support\Facades\DB::table('reasons')
    //   ->rightJoin('groups', 'groups.id', 'reasons.group_id')
    //   ->join('manager_groups', 'manager_groups.group_id', 'groups.id')
    //   ->join('managers', 'managers.id', 'manager_groups.manager_id')
    //   // ->where('groups.default', 1)
    //   // ->whereIn('managers.crm_id', array_map(fn($m) => $m['crm_id'], $map))
    //   // ->select('managers.crm_id', 'reasons.id AS reason_id', 'reasons.weight', 'reasons.name AS reason')
    //   // ->groupBy('reasons.id', 'managers.id')
    //   ->get();
    // $sums = array();
    // foreach ($map as $value) {
    //   if (array_key_exists($value['manager_id'], $sums)) {
    //     $sums[$value['manager_id']] += $value['weight'];
    //   } else {
    //     $sums[$value['manager_id']] = $value['weight'];
    //   }
    // }

    // dd($sums);
    // $responsive_ids = array_keys($sums, min($sums));

    // $responsive_id = count($responsive_ids) > 0 ? $responsive_ids[0] : $responsive_ids;

    // dd($responsive_id);
    // return $responsive_id;
  }

  public static function SelectResponsiveId($managers): int
  {
    // dd($managers);
    $m = array_map(fn($e) => $e['user_id'], $managers);
    $data = DB::table('tickets')
      ->join('managers', 'managers.user_id', 'tickets.new_manager_id')
      ->leftJoin(
        'messages',
        fn($q) => $q
          ->on('messages.ticket_id', 'tickets.id')
          ->whereRaw('messages.id IN (SELECT MAX(m2.id) FROM messages as m2 join tickets as t2 on t2.id = m2.ticket_id GROUP BY t2.id)')
      )
      ->where('tickets.active', true)
      ->whereIn('tickets.new_manager_id', $m)
      ->whereNotIn('messages.new_user_id', $m)
      ->select(
        'tickets.id',
        'tickets.weight',
        'tickets.new_manager_id',
        'messages.id',
        'messages.new_user_id AS last_message_user_id'
      )
      ->get()->toArray();

    if (count($data) == 0) {
      return 0;
    } elseif (count($data) < count($m)) {
      foreach ($data as $ticket) {
        $key = array_search($ticket->new_manager_id, $m);
        // var_dump($key === false);
        // if ($key === false) {
        //   return 0;
        // } else {
        //   unset($m[$key]);
        // }
        if ($key === true) {
          unset($m[$key]);
        }
      }

      return intval(array_values($m)[0]);
    }

    $sums = array();
    foreach ($data as $value) {
      if (array_key_exists($value->new_manager_id, $sums)) {
        $sums[$value->new_manager_id] += $value->weight;
      } else {
        $sums[$value->new_manager_id] = $value->weight;
      }
    }

    $responsive_ids = array_keys($sums, min($sums));

    $responsive_id = count($responsive_ids) > 0 ? $responsive_ids[0] : $responsive_ids;

    return $responsive_id;
  }

  public static function SaveAttachment($message_id, $content, $app_domain, $prefix)
  {
    if (env('APP_URL') != $app_domain) {
      return self::SaveAttachmentToAnotherCRM($message_id, $content, $prefix);
    }

    $attachments_path = "public/attachments/{$message_id}";
    if (!Storage::disk('local')->exists($attachments_path)) {
      Storage::makeDirectory($attachments_path);
    }

    $path = "{$attachments_path}/{$content['name']}";
    $file = file_get_contents($content["tmp_name"]);

    Storage::disk('local')->put($path, $file);

    $attachment = \App\Models\Attachment::create([
      'message_id' => $message_id,
      'name' => $content['name'],
      'link' => Storage::url($path),
    ]);

    return \App\Http\Resources\AttachmentResource::make($attachment);
  }

  private static function SaveAttachmentToAnotherCRM($message_id, $content, $prefix)
  {
    // $attachments_path = dirname(base_path()) . "/{$prefix}/storage/app/public/attachments";
    $attachments_path = dirname(base_path()) . "/{$prefix}/storage/app/public/attachments/{$message_id}";
    // dd($attachments_path, File::isDirectory($attachments_path));
    if (!File::isDirectory($attachments_path)) {
      File::makeDirectory($attachments_path, 0700, true, true);
    }

    $path = "{$attachments_path}/{$content['name']}";
    $file = file_get_contents($content["tmp_name"]);

    File::put($path, $file);

    $storage_path = "public/attachments/{$message_id}/{$content['name']}";
    $attachment = \App\Models\Attachment::create([
      'message_id' => $message_id,
      'name' => $content['name'],
      'link' => Storage::url($storage_path),
    ]);

    return \App\Http\Resources\AttachmentResource::make($attachment);
  }

  public static function MarkedTicketsPreparing()
  {
    $tickets = Ticket::whereActive(false)
      ->whereRaw('updated_at < (utc_timestamp() - INTERVAL 1 DAY)')
      ->pluck('id');

    foreach ($tickets as $id) {
      $result = static::FinishTicket($id);
    }
  }

  public static function FinishTicket($old_ticket_id, $mark = 0)
  {
    $ticket = Ticket::findOrFail($old_ticket_id);
    $data = clone ($ticket);
    $data->old_ticket_id = $data->id;
    $data->mark = $mark;

    $validated = \Illuminate\Support\Facades\Validator::make($data->toArray(), [
      'old_ticket_id' => 'required|integer|min:1',
      'user_id' => 'required|integer|min:1',
      'manager_id' => 'required|integer|min:1',
      'new_user_id' => 'required|integer|min:1',
      'new_manager_id' => 'required|integer|min:1',
      'crm_id' => 'required|integer|min:1',
      'reason_id' => 'required|integer|min:1',
      'weight' => 'required|integer|min:1',
      'mark' => 'required|integer|min:0|max:3',
    ])->validate();
    $validated['mark'] = strval($validated['mark']);

    $resolved = \App\Models\ResolvedTicket::firstOrNew([
      'old_ticket_id' => $validated['old_ticket_id']
    ]);
    if ($resolved->exists) {
      return [
        'status' => false,
        'data' => null,
        'message' => 'Попытка завершить уже завершённый тикет'
      ];
    }

    $resolved->fill($validated);
    $resolved->save();
    $result = $ticket->delete();

    HiddenChatMessage::create([
      'content' => 'Тикет завершён',
      'user_crm_id' => 0,
      'new_user_id' => 1,
      'ticket_id' => $ticket->id,
    ]);

    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', [$ticket->new_user_id, $ticket->new_manager_id])
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();

    $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
    $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
    unset($users_with_emails);

    $message = "Тикет №{$ticket->id} успешно завершён";
    self::SendMessageToWebsocket("{$m->email}.ticket.delete", [
      'id' => $ticket->id,
      'message' => $message,
    ]);
    self::SendMessageToWebsocket("{$u->email}.ticket.delete", [
      'id' => $ticket->id,
      'message' => $message,
    ]);
    $part_emails = Participant::whereTicketId($ticket->id)
      ->join('users', 'users.id', 'participants.user_id')
      // ->join('bx_users', 'bx_users.user_id', 'users.id')
      // ->pluck('bx_users.crm_id')->toArray();
      ->pluck('users.email')->toArray();
    foreach ($part_emails as $email) {
      self::SendMessageToWebsocket("{$email}.ticket.delete", [
        'id' => $ticket->id,
        'message' => $message,
      ]);
    }

    return [
      'status' => true,
      'data' => $result,
      'message' => $message
    ];
  }

  public static function TryToRedistributeByReason($reason_id, $old_user_id, $new_users_ids, $count)
  {
    $tickets = Ticket::whereNewManagerId($old_user_id)
      ->whereReasonId($reason_id)->orderByDesc('id')->take($count)->get();

    $managers = Manager::join('users', 'users.id', 'managers.user_id')
      // ->join('bx_users', 'bx_users.user_id', 'users.id')
      ->whereRoleId(2)
      ->whereIn('user_id', $new_users_ids)
      ->pluck('users.name', 'user_id');

    // dd($managers);
    $tickets_ids = array_map(fn($e) => $e['id'], $tickets->toArray());
    $participants = Participant::whereIn('ticket_id', $tickets_ids)
      ->whereIn('user_id', $new_users_ids)->get();

    $managersCount = count($new_users_ids);
    $currentKey = 0;
    $hiddenChatMessages = [];
    $participants_ids = [];

    $users_collection = array();

    foreach (UserTrait::withFired()->data as $user) {
      $users_collection[$user->email] = $user;
    }

    $all_ids = array_merge(...array_map(fn($t) => [$t->new_user_id, $t->new_manager_id], $tickets->all()));
    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', array_values(array_unique($all_ids)))
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();
    unset($all_ids);

    foreach ($tickets as $key => $ticket) {
      if ($key == $count)
        break;
      if ($currentKey == $managersCount)
        $currentKey = 0;

      $manager_id = $new_users_ids[$currentKey++];

      array_push($hiddenChatMessages, [
        'content' => "Новый ответственный: {$managers[$manager_id]}",
        'user_crm_id' => 0,
        'new_user_id' => 1,
        'ticket_id' => $ticket->id,
      ]);

      $participant = $participants->where('ticket_id', $ticket->id)
        ->where('user_id', $manager_id)->first();
      if (isset($participant)) {
        array_push($participants_ids, $participant->id);
      }

      $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
      $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();

      $new_participant = Participant::firstOrNew([
        'ticket_id' => $ticket->id,
        // 'user_crm_id' => $ticket->manager_id,
        'user_id' => $ticket->new_manager_id,
      ]);
      if (!$new_participant->exists) {
        $new_participant->user_crm_id = $m->crm_id;
        $new_participant->save();
      }

      $ticket->new_manager_id = $manager_id;
      $ticket->save();

      $ticket->reason = \App\Models\Reason::find($ticket->reason_id)->name;
      $ticket->user = $users_collection[$u->email];
      $ticket->manager = $users_collection[$m->email];

      $manager_email = DB::table('users')
        ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
        ->where('users.id', $manager_id)
        ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
        ->first();

      self::SendNotification($manager_id, "Вы стали ответственным за тикет №{$ticket->id}", $ticket->id);
      self::SendMessageToWebsocket("{$manager_email->email}.ticket", [
        'ticket' => TicketResource::make($ticket),
      ]);

      $result = [
        'ticket_id' => $ticket->id,
        'new_manager' => $ticket->manager,
        'new_participant_id' => $m->crm_id,
      ];
      self::SendMessageToWebsocket("{$m->email}.participant", [
        'participant' => $result,
      ]);
      self::SendMessageToWebsocket("{$u->email}.participant", [
        'participant' => $result,
      ]);
      $another_recipients = Participant::whereTicketId($ticket->id)
        ->join('users', 'users.id', 'participants.user_id')
        // ->join('bx_users', 'bx_users.user_id', 'users.id')
        // ->pluck('bx_users.crm_id')->toArray();
        ->pluck('users.email')->toArray();
      foreach ($another_recipients as $email) {
        self::SendMessageToWebsocket("{$email}.participant", [
          'participant' => $result,
        ]);
      }
    }

    unset($users_collection);
    Participant::whereIn('id', $participants_ids)->delete();
    HiddenChatMessage::insert($hiddenChatMessages);

    return [
      'status' => true,
      'data' => null,
      'message' => "Тикеты успешно переданы ({$count}шт.)"
    ];
  }

  public static function SendMessageToWebsocket($recipient_email, $data)
  {
    $email = preg_replace('/@[А-яA-z]+\.[А-яA-z]+/iu', '', $recipient_email);
    $channel_id = "#support.{$email}";
    $client = new \phpcent\Client(
      env('CENTRIFUGE_URL') . '/api',
      '8ffaffac-8c9e-4a9c-88ce-54658097096e',
      'ee93146a-0607-4ea3-aa4a-02c59980647e'
    );
    $client->setSafety(false);
    $client->publish($channel_id, $data);
  }

  public static function SendNotification($recipient_id, $message, $ticket_id)
  {
    $bx_data = BxCrm::join('bx_users', 'bx_users.bx_crm_id', 'bx_crms.id')
      ->where('bx_users.user_id', $recipient_id)
      ->select('bx_crms.domain', 'bx_crms.marketplace_id', 'bx_crms.webhook_id', 'bx_users.crm_id')
      ->get();

    foreach ($bx_data as $bx) {
      $market_id = $bx->marketplace_id;
      $crm_id = $bx->domain;
      $webhook_id = $bx->webhook_id;
      $user_id = $bx->crm_id;

      $content = "{$message}\r\n[URL=/marketplace/app/{$market_id}/?id={$ticket_id}]Перейти[/URL]";
      $WEB_HOOK_URL = "https://${crm_id}/rest/{$webhook_id}/im.message.add.json?USER_ID={$user_id}&MESSAGE={$content}&URL_PREVIEW=Y";

      \Illuminate\Support\Facades\Http::get($WEB_HOOK_URL);
    }
  }
}