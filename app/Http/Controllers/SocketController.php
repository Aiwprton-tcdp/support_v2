<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SocketController extends Controller
{
  public function Subscribe()
  {
    // $chat_id = '#support.' . md5(Auth::user()->crm_id) . md5(env('CENTRIFUGE_SALT'));
    $user_with_email = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->where('users.id', Auth::user()->id)
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->first();

    $chat_id = '#support.' . $user_with_email->crm_id;
    $client = new \phpcent\Client(
      env('CENTRIFUGE_URL'),
      "8ffaffac-8c9e-4a9c-88ce-54658097096e",
      "ee93146a-0607-4ea3-aa4a-02c59980647e"
    );
    $client->setSafety(false);
    $token = $client->generatePrivateChannelToken(request('client'), $chat_id);

    return response()->json([
      'channels' => [
        [
          'channel' => $chat_id,
          'token' => $token
        ]
      ]
    ]);
  }

  public function Refresh()
  {
    $user_with_email = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->where('users.id', Auth::user()->id)
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->first();

    $client = new \phpcent\Client(
      env('CENTRIFUGE_URL'),
      "8ffaffac-8c9e-4a9c-88ce-54658097096e",
      "ee93146a-0607-4ea3-aa4a-02c59980647e"
    );
    $client->setSafety(false);
    $token = $client->generateConnectionToken(
      $chat_id = '#support.' . $user_with_email->crm_id
    );

    return response()->json([
      'token' => $token
    ]);
  }

  // public function MesageUpload()
  // {
  //   // $ticket = intval(htmlspecialchars(trim(request('ticket'))));
  //   $message = htmlspecialchars(trim(request('message')));
  //   $user_id = intval(htmlspecialchars(trim(request('user_id'))));

  //   $chat_id = '#support.' . md5(Auth::user()->crm_id) . md5(env('CENTRIFUGE_SALT'));
  //   $chat_id = '#support.' . '11';
  //   $client = new \phpcent\Client(
  //     env('CENTRIFUGE_URL'),
  //     "8ffaffac-8c9e-4a9c-88ce-54658097096e",
  //     "ee93146a-0607-4ea3-aa4a-02c59980647e"
  //   );

  //   $client->publish($chat_id, [
  //     "message" => $message,
  //     "user_id" => $user_id,
  //   ]);

  //   return response()->json([
  //     'status' => true,
  //     'data' => null
  //   ]);
  // }
}