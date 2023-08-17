<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Traits\UserTrait;

class DepartmentController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function search()
  {
    $id = intval(htmlspecialchars(trim(request('id'))));

    return response()->json([
      'status' => true,
      'data' => UserTrait::departments()
    ]);
  }
}
