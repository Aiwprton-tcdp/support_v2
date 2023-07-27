<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait ReasonTrait
{
  public static function Checksum() : array
  {
    $current = \App\Models\Reason::all('name')->map(fn($e) => $e->name)->toArray();
    if (Cache::store('file')->has('checksum')) {
      $e = json_decode(Cache::store('file')->get('checksum'), true);
      return array_values(array_diff($e, $current));
    }

    $client = new \GuzzleHttp\Client();
    $res = $client->get(env('NLP_URL') . '/reasons/list')->getBody()->getContents();
    Cache::store('file')->forever('checksum', $res);

    return array_values(array_diff(json_decode($res, true), $current));
  }
}