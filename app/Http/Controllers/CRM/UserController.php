<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CRM\UserResource;
use App\Traits\BX;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function search()
    {
        // $name = htmlspecialchars(trim(request('name')));
        // $last_name = htmlspecialchars(trim(request('last_name')));

        if (Cache::store('file')->has('crm_users')) {
            $data = Cache::store('file')->get('crm_users');
            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        }

        $data = BX::firstBatch('user.search', [
            'USER_TYPE' => 'employee',
            // 'NAME_SEARCH' => trim($last_name . ' ' . $name),
            'ACTIVE' => true,
        ]);
        $resource = UserResource::collection($data)->response()->getData();
        Cache::store('file')->put('crm_users', $resource, 3600);

        return response()->json([
            'status' => true,
            'data' => $resource
        ]);
    }
}
