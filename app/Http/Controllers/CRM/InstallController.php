<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\BX;

class InstallController extends Controller
{
    public function install(Request $request)
    {
        $result = BX::installApp($request);
        return view('install', compact('result'));
    }
}
