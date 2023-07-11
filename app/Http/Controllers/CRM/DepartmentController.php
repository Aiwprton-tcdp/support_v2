<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CRM\DepartmentResource;
use App\Traits\BX;
use Illuminate\Support\Facades\Cache;

class DepartmentController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function search()
  {
    $id = intval(htmlspecialchars(trim(request('id'))));

    if (Cache::store('file')->has('crm_departments')) {
      $data = Cache::store('file')->get('crm_departments');
      return response()->json([
        'status' => true,
        'data' => $data
      ]);
    }

    $data = BX::firstBatch('department.get');
    $resource = DepartmentResource::collection($data)->response()->getData();
    Cache::store('file')->put('crm_departments', $resource, 3600);

    return response()->json([
      'status' => true,
      'data' => $resource
    ]);
  }
}
