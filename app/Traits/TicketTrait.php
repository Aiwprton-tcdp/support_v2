<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait TicketTrait
{
  private static $C_REST_WEB_HOOK_URL = '';
  private static $DOMAIN = '';

  /**
   * Remove the specified resource from storage.
   */
  public static function GetManagersForReason($reason_id)
  {
    return \App\Models\Reason::join('groups', 'groups.id', 'reasons.group_id')
      ->join('manager_groups', 'manager_groups.group_id', 'groups.id')
      ->join('managers', 'managers.id', 'manager_groups.manager_id')
      ->join('users', 'users.crm_id', 'managers.crm_id')
      ->where('reasons.id', $reason_id)
      // ->where('reasons.name', $reason)
      ->select('managers.id', 'managers.crm_id', 'users.name', 'reasons.weight', 'reasons.name AS reason')
      ->groupBy('reasons.id', 'groups.id', 'manager_groups.id', 'managers.id', 'users.id')
      ->get();
  }

  /**
   * Remove the specified resource from storage.
   */
  public static function SelectResponsive($managers)
  {
    $m = array_map(fn($e) => $e['id'], $managers->toArray());
    $data = \Illuminate\Support\Facades\DB::table('tickets')
      ->join('managers', 'managers.id', 'tickets.manager_id')
      ->join('users', 'users.crm_id', 'managers.crm_id')
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

    // посчитать сумму весов для каждого
    // возвращаем с наибольшей суммой или рандомного
    $sums = array();
    foreach ($data as $value) {
      if (array_key_exists($value->manager_id, $sums)) {
        $sums[$value->manager_id] += $value->weight;
      } else {
        $sums[$value->manager_id] = $value->weight;
      }
    }

    // $sums.map(fn ($e) => $e = 10);
    // $res = array_map(fn ($e) => $e = 10, $sums);

    $responsive_ids = array_keys($sums, min($sums));

    $responsive_id = count($responsive_ids) > 0 ? $responsive_ids[0] : $responsive_ids;

    return $responsive_id;
  }

  public static function GetReason($message)
  {
    $client = new \GuzzleHttp\Client();
    $res = $client->post(env('NLP_URL'), ['form_params' => ['message' => $message]])->getBody()->getContents();
    // echo $res->getStatusCode(); // 200
    // dd($name);
    $name = json_decode($res, true);
    $reason = \App\Models\Reason::whereName($name)->first();
    // dd($name, $reason);
    return $reason;


    // $response = \Illuminate\Support\Facades\Http::acceptJson()
    //   ->post(env('NLP_URL'), [
    //   'message' => $message,
    // ])->throw()->json();

    // return $response;
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

  public static function SendNotification($recipient_id, $message, $ticket_id)
  {
    static::$C_REST_WEB_HOOK_URL = 'https://xn--24-9kc.xn--b1aaiaj6cd.xn--p1ai/rest/10/86v5bz5tbr1c9xhq/';
    static::$DOMAIN = env('CRM_URL');

    $content = "{$message}\r\n[URL=/marketplace/app/2/?id={$ticket_id}]Перейти[/URL]";

    $result = BX::call(
      'im.message.add',
      array(
        'USER_ID' => $recipient_id,
        'MESSAGE' => $content,
        'URL_PREVIEW' => "Y",
      )
    );
    static::$C_REST_WEB_HOOK_URL = '';
  }
}