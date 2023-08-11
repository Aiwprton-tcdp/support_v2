<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait TicketTrait
{
  private static $C_REST_WEB_HOOK_URL = '';
  private static $DOMAIN = '';


  public static function GetReason($message)
  {
    $client = new \GuzzleHttp\Client();
    $res = $client->post(env('NLP_URL'), ['form_params' => ['message' => $message]])->getBody()->getContents();
    $name = json_decode($res, true);
    $reason = \App\Models\Reason::whereName($name)->first();
    return $reason;
  }

  public static function GetManagersForReason($reason_id)
  {
    return \App\Models\Reason::join('groups', 'groups.id', 'reasons.group_id')
      ->join('manager_groups', 'manager_groups.group_id', 'groups.id')
      ->join('managers', 'managers.id', 'manager_groups.manager_id')
      ->join('users', 'users.crm_id', 'managers.crm_id')
      ->where('reasons.id', $reason_id)
      ->select('managers.crm_id', 'users.name', 'reasons.weight', 'reasons.name AS reason')
      ->groupBy('reasons.id', 'groups.id', 'manager_groups.id', 'managers.id', 'users.id')
      ->get();
  }

  public static function SelectResponsiveId($managers): int
  {
    $m = array_map(fn($e) => $e['crm_id'], $managers->toArray());
    $data = \Illuminate\Support\Facades\DB::table('tickets')
      ->join('managers', 'managers.crm_id', 'tickets.manager_id')
      ->leftJoin(
        'messages',
        fn($q) => $q
          ->on('messages.ticket_id', 'tickets.id')
          ->whereRaw('messages.id IN (SELECT MAX(m2.id) FROM messages as m2 join tickets as t2 on t2.id = m2.ticket_id GROUP BY t2.id)')
      )
      ->where('tickets.active', true)
      ->whereIn('tickets.manager_id', $m)
      ->whereNotIn('messages.user_crm_id', $m)
      ->select(
        'tickets.id',
        'tickets.weight',
        'tickets.manager_id',
        'messages.id',
        'messages.user_crm_id AS last_message_user_id'
      )
      ->get()->toArray();

    if (count($data) == 0) {
      return 0;
    } elseif (count($data) < count($m)) {
      foreach ($data as $ticket) {
        $key = array_search($ticket->manager_id, $m);
        if ($key === false) {
          return 0;
        } else {
          unset($m[$key]);
        }
      }

      return intval(array_values($m)[0]);
    }

    $sums = array();
    foreach ($data as $value) {
      if (array_key_exists($value->manager_id, $sums)) {
        $sums[$value->manager_id] += $value->weight;
      } else {
        $sums[$value->manager_id] = $value->weight;
      }
    }

    $responsive_ids = array_keys($sums, min($sums));

    $responsive_id = count($responsive_ids) > 0 ? $responsive_ids[0] : $responsive_ids;

    return $responsive_id;
  }

  public static function SaveAttachment($message_id, $content)
  {
    $attachments_path = 'public/attachments/' . $message_id;
    if (!Storage::disk('local')->exists($attachments_path)) {
      Storage::makeDirectory($attachments_path);
    }

    $path = $attachments_path . '/' . $content['name'];
    $file = file_get_contents($content["tmp_name"]);

    Storage::disk('local')->put($path, $file);

    $new_data = [
      'message_id' => $message_id,
      'name' => $content['name'],
      'link' => Storage::url($path),
    ];
    $attachment = \App\Models\Attachment::create($new_data);

    return \App\Http\Resources\AttachmentResource::make($attachment);
  }

  public static function MarkedTicketsPreparing()
  {
    $tickets = \App\Models\Ticket::whereActive(false)
      ->whereRaw('updated_at < (utc_timestamp() - INTERVAL 1 DAY)')
      ->pluck('id');

    foreach ($tickets as $id) {
      $result = static::FinishTicket($id);
      Log::info($result['message']);
    }
  }

  public static function FinishTicket($old_ticket_id, $mark = 0)
  {
    $ticket = \App\Models\Ticket::findOrFail($old_ticket_id);
    $data = clone ($ticket);
    $data->old_ticket_id = $data->id;
    $data->mark = $mark;

    $validated = \Illuminate\Support\Facades\Validator::make($data->toArray(), [
      'old_ticket_id' => 'required|integer|min:1',
      'user_id' => 'required|integer|min:1',
      'manager_id' => 'required|integer|min:1',
      'reason_id' => 'required|integer|min:1',
      'weight' => 'required|integer|min:1',
      'mark' => 'required|integer|min:0',
    ])->validate();

    $resolved = \App\Models\ResolvedTicket::firstOrNew($validated);
    if ($resolved->exists) {
      $message = "Попытка завершить уже завершённый тикет #{$ticket->id}";
      return [
        'status' => false,
        'data' => null,
        'message' => $message
      ];
    }

    \App\Models\HiddenChatMessage::create([
      'content' => 'Тикет завершён',
      'user_crm_id' => 0,
      'ticket_id' => $ticket->id,
    ]);

    $resolved->save();
    $result = $ticket->delete();
    $message = "Тикет `{$ticket->id}` успешно завершён";

    self::SendMessageToWebsocket("{$ticket->manager_id}.ticket.delete", [
      'id' => $ticket->id,
      'message' => $message,
    ]);
    self::SendMessageToWebsocket("{$ticket->user_id}.ticket.delete", [
      'id' => $ticket->id,
      'message' => $message,
    ]);
    $part_ids = \App\Models\Participant::whereTicketId($ticket->id)
      ->pluck('user_crm_id')->toArray();
    foreach ($part_ids as $id) {
      self::SendMessageToWebsocket("{$id}.ticket.delete", [
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

  public static function SendMessageToWebsocket($recipient_id, $data)
  {
    // $channel_id = '#support.' . md5($data->user_id) . md5(env('CENTRIFUGE_SALT'));
    $channel_id = '#support.' . $recipient_id;
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
    $market_id = env('MARKETPLACE_ID');
    $content = "{$message}\r\n[URL=/marketplace/app/{$market_id}/?id={$ticket_id}]Перейти[/URL]";
    $WEB_HOOK_URL = "https://xn--24-9kc.xn--d1ao9c.xn--p1ai/rest/10033/t8swdg5q7trw0vst/im.message.add.json?USER_ID={$recipient_id}&MESSAGE={$content}&URL_PREVIEW=Y";

    \Illuminate\Support\Facades\Http::get($WEB_HOOK_URL);
  }

  // public static function SendNotification($recipient_id, $message, $ticket_id)
  // {
  //   static::$C_REST_WEB_HOOK_URL = 'https://xn--24-9kc.xn--b1aaiaj6cd.xn--p1ai/rest/10/86v5bz5tbr1c9xhq/';
  //   static::$DOMAIN = env('CRM_URL');

  //   $market_id = env('MARKETPLACE_ID');
  //   $content = "{$message}\r\n[URL=/marketplace/app/{$market_id}/?id={$ticket_id}]Перейти[/URL]";

  //   $result = BX::call(
  //     'im.message.add',
  //     array(
  //       'USER_ID' => $recipient_id,
  //       'MESSAGE' => $content,
  //       'URL_PREVIEW' => "Y",
  //     )
  //   );
  //   static::$C_REST_WEB_HOOK_URL = '';
  // }
}