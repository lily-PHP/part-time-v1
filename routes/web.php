<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

//获取验证码
Route::get('/public/getCaptcha', 'UserCenterController@getCaptcha');
Route::any('/public/checkCaptcha', 'UserCenterController@userRegister');
