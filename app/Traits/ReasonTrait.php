<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait ReasonTrait
{
  public static function initByMessage($message)
  {
    $client = new \GuzzleHttp\Client();
    $res = $client->post(env('NLP_URL'), ['form_params' => ['message' => $message]])->getBody()->getContents();
    $name = json_decode($res, true);
    $reason = \App\Models\Reason::firstWhere('name', $name) ?? \App\Models\Reason::first();
    return $reason;
  }

  public static function Checksum() : array
  {
    $current = \App\Models\Reason::all('name')->map(fn($e) => $e->name)->toArray();
    // if (Cache::store('file')->has('checksum')) {
    //   $e = json_decode(Cache::store('file')->get('checksum'), true);
    //   return array_values(array_diff($e, $current));
    // }

    $client = new \GuzzleHttp\Client();
    $res = $client->get(env('NLP_URL') . '/reasons/list')->getBody()->getContents();
    // Cache::store('file')->forever('checksum', $res);

    return array_values(array_diff(json_decode($res, true), $current));
  }
}