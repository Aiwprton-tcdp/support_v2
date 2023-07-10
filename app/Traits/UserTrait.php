<?php

namespace App\Traits;

use App\Models\Group;
use App\Models\ManagerGroup;

trait UserTrait
{
  /**
   * Remove the specified resource from storage.
   */
  public static function destroy($id)
  {
    $groups_ids = ManagerGroup::whereUserId($id)
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

    if ($groups->contains(fn ($e) => $e->count == 1)) {
      $groups_alone = array_filter($groups_array, fn ($e) => $e['count'] == 1);
      $names = array_map(fn ($e) => $e['name'], $groups_alone);
      $message = 'Данный менеджер указан как единственный участник в группах: ';

      return [
        'status' => false,
        'data' => null,
        'message' => $message . implode(', ', $names),
      ];
    }

    // Deleting from `manager_groups` all rows where deleting user in not alone
    $delete_group_ids = array_filter($groups_ids, function ($e, $key) use ($groups_array) {
      if ($groups_array[$key]['count'] > 1) return $e;
    }, ARRAY_FILTER_USE_BOTH);
    ManagerGroup::whereIn('id', $delete_group_ids)->delete();

    return null;
  }
}
