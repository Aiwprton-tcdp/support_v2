<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Traits\BX;
use App\Models\VerifiedUser;

class IndexAPIController extends Controller
{
    public function __invoke()
    {
//        $validatedUsers = [];
        $check = BX::setDataE($_REQUEST); // получает авторизацию битрикса
        $user = BX::call('user.current'); // получает конкретного пользователя по авторизации

        return view('welcome');
//        $rawUsers = VerifiedUser::where('verified', 1)->get();
//        foreach ($rawUsers as $rawUser) {
//            $validatedUsers[] = $rawUser['bx_id'];
//        }
//        if(in_array($user['result']['ID'], $validatedUsers)) {
//            return view('index');
//        } else {
//            return response()->view('errors/401', [], 401);
//        }
    }
}
