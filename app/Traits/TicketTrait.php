<?php

namespace App\Traits;

trait TicketTrait
{
  /**
   * Remove the specified resource from storage.
   */
  public static function selectResponsive($managers)
  {
    $m = array_map(fn ($e) => $e['id'], $managers->toArray());
    $data = \Illuminate\Support\Facades\DB::table('tickets')
      ->join('users', 'users.id', 'tickets.manager_id')
      ->leftJoin(
        'messages',
        fn ($q) => $q
          ->on('messages.ticket_id', 'tickets.id')
          ->whereRaw('messages.id IN (SELECT MAX(m2.id) FROM messages as m2 join tickets as t2 on t2.id = m2.ticket_id GROUP BY t2.id)')
      )
      ->where('tickets.active', true)
      ->whereIn('tickets.manager_id', $m)
      ->whereNotIn('messages.user_id', $m)
      ->select(
        'tickets.id',
        'tickets.weight',
        'tickets.manager_id',
        'messages.id',
        'messages.user_id AS last_message_user_id'
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
}
