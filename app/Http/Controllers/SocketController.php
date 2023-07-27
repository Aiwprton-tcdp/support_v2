<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class SocketController extends Controller
{
  public function Subscribe()
  {
    // $chat_id = '#support.' . md5(Auth::user()->crm_id) . md5(env('CENTRIFUGE_SALT'));
    $chat_id = '#support.' . Auth::user()->crm_id;
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
    $client = new \phpcent\Client(
      env('CENTRIFUGE_URL'),
      "8ffaffac-8c9e-4a9c-88ce-54658097096e",
      "ee93146a-0607-4ea3-aa4a-02c59980647e"
    );
    $client->setSafety(false);
    $token = $client->generateConnectionToken(Auth::user()->crm_id);

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