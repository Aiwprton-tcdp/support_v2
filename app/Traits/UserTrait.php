<?php

namespace App\Traits;

use App\Http\Resources\CRM\DepartmentResource;
use App\Models\Group;
use App\Models\ManagerGroup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

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
   * @return object
   */
  public static function tryToDefineUserEverywhere($user_id): object
  {
    $search = self::search();
    $managers = array_values(array_filter($search->data, fn($e) => $e->crm_id == $user_id));
    unset($search);

    $manager = !empty($managers) ? $managers[0] : null;

    if (!isset($manager)) {
      $withFired = self::withFired(true);
      $managers = array_values(array_filter($withFired->data, fn($e) => $e->crm_id == $user_id));
      unset($withFired);
    }

    $manager = !empty($managers)
      ? $managers[0]
      : User::whereCrmId($user_id)->first();

    if (!isset($manager)) {
      $manager = [
        'crm_id' => $user_id,
        'name' => 'Неопределённый пользователь',
      ];
    }

    return (object)$manager;
  }

  public static function search()
  {
    if (Cache::store('file')->has('crm_users')) {
      $data = Cache::store('file')->get('crm_users');
      return $data;
    }

    $data = BX::firstBatch('user.search', [
      'USER_TYPE' => 'employee',
      'ACTIVE' => true,
    ]);
    $resource = \App\Http\Resources\CRM\UserResource::collection($data)->response()->getData();
    Cache::store('file')->put('crm_users', $resource, 10800);

    return $resource;
  }

  public static function withFired($force = false)
  {
    if (!$force && Cache::store('file')->has('crm_all_users')) {
      $data = Cache::store('file')->get('crm_all_users');
      return $data;
    }

    $data = BX::firstBatch('user.get', [
      'USER_TYPE' => 'employee',
    ]);
    $resource = \App\Http\Resources\CRM\UserResource::collection($data)->response()->getData();
    Cache::store('file')->put('crm_all_users', $resource, 10800);

    return $resource;
  }

  public static function departments()
  {
    if (Cache::store('file')->has('crm_departments')) {
      $data = Cache::store('file')->get('crm_departments');
      return response()->json([
        'status' => true,
        'data' => $data
      ]);
    }

    $data = BX::firstBatch('department.get');
    $resource = DepartmentResource::collection($data)->response()->getData();
    Cache::store('file')->put('crm_departments', $resource, 10800);

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

    // Deleting from `manager_groups` all rows where deleting user in not alone
    $delete_group_ids = array_filter($groups_ids, function ($e, $key) use ($groups_array) {
      if ($groups_array[$key]['count'] > 1)
        return $e;
    }, ARRAY_FILTER_USE_BOTH);
    ManagerGroup::whereIn('id', $delete_group_ids)->delete();

    return null;
  }
}