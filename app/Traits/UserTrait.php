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
   * @param string $email
   * @return object
   */
  public static function tryToDefineUserEverywhere($user_id, $email): object
  {
    $search = self::search();
    $managers = array_values(array_filter($search->data, fn($e) => $e->email == $email));
    unset($search);

    // $manager = !empty($managers) ? $managers[0] : null;

    // if (!isset($manager)) {
    //   $withFired = self::withFired();
    //   // $withFired = self::withFired(true);
    //   $managers = array_values(array_filter($withFired->data, fn($e) => $e->email == $email));
    //   unset($withFired);
    // }

    $manager = !empty($managers)
      ? $managers[0]
      : User::firstWhere('email', $email);

    if (!isset($manager)) {
      $manager = [
        'crm_id' => $user_id,
        'email' => $email,
        'name' => 'Неопределённый пользователь',
      ];
    }

    return (object)$manager;
  }

  public static function search()
  {
    $prefix = env('APP_PREFIX');
    if (Cache::store('file')->has("{$prefix}_users")) {
      return Cache::store('file')->get("{$prefix}_users");
    }

    $data = BX::firstBatch('user.search', [
      'USER_TYPE' => 'employee',
      'ACTIVE' => true,
    ]);
    $resource = \App\Http\Resources\CRM\UserResource::collection($data)->response()->getData();
    Cache::store('file')->forever("{$prefix}_users", $resource);

    return $resource;
  }

  // public static function withFired($force = false)
  // {
  //   if (!$force && Cache::store('file')->has('crm_all_users')) {
  //     return Cache::store('file')->get('crm_all_users');
  //   }

  //   $data = BX::firstBatch('user.get', [
  //     'USER_TYPE' => 'employee',
  //   ]);
  //   $resource = \App\Http\Resources\CRM\UserResource::collection($data)->response()->getData();
  //   Cache::store('file')->forever('crm_all_users', $resource);

  //   return $resource;
  // }

  public static function departments()
  {
    $prefix = env('APP_PREFIX');
    if (Cache::store('file')->has("{$prefix}_departments")) {
      return Cache::store('file')->get("{$prefix}_departments");
    }

    $data = BX::firstBatch('department.get');
    $resource = DepartmentResource::collection($data)->response()->getData();
    Cache::store('file')->forever("{$prefix}_departments", $resource);

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