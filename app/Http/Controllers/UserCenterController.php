<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mews\Captcha\Captcha;
use Illuminate\Support\Facades\Validator;
use DB;

class UserCenterController extends Controller
{
    /**
     * 生成验证码
     * @param Request $request
     * @param Captcha $captchaBuilder
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCaptcha(Request $request, Captcha $captchaBuilder)
    {
        $return = config('return.select');
        $key = $request -> input('sid') ?? '';
        if (!$key) {
            return response() -> json($return);
        }

        $captcha = $captchaBuilder->create('math', true);
        if (!$captcha['key'] || !$captcha['img']) {
            $return['msg'] = '生成验证码失败~';
            return response() -> json($return);
        }

        $redis = app('redis.connection');
        $redis -> select(6);
        $redis -> set('Captcha_'.$key, $captcha['key']);
        $redis -> expire('Captcha_'.$key, 60*2);

        $return['status'] = 200;
        $return['data'] = $captcha['img'];
        $return['msg'] = '验证码生成成功~';

        return response() -> json($return);
    }


    /**
     * 用户注册
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userRegister(Request $request)
    {
        $return = config('return.select');
        $all = $request -> all();

        $message = [
            'username.required' => '用户名：邮箱必填',
            'password.required' => '密码必填',
            'name.required'     => '昵称必填',
            'sid.required'      => 'UUID缺失',
            'code.required'     => '验证码必填',
        ];
        $validator = Validator::make($all, [
            'username'      => 'required',
            'password'      => 'required',
            'name'          => 'required',
            'sid'           => 'required',
            'code'          => 'required'
        ], $message);

        if ($validator -> fails()) {
            $return['msg'] = $validator -> errors() -> first();
            return response() -> json($return);
        }

        $redis = app('redis.connection');
        $redis -> select(6);
        $cache_cap = $redis -> get('Captcha_'.$all['sid']);

        if (!$cache_cap) {
            $return['msg'] = '验证码过期~';
            return response() -> json($return);
        }

        if (!captcha_api_check($all['code'], $cache_cap)) {
            $return['status'] = 40002;
            $return['msg'] = '验证码错误';
            return response() -> json($return);
        }

        //验证码正确，校验用户名是否存在
        if (DB::table('user') -> where('u_userName', $all['username']) -> exists()) {
            $return['status'] = 40000;
            $return['msg'] = '用户名已被注册';
            return response() -> json($return);
        }

        if (DB::table('user') -> where('u_nickName', $all['name']) -> exists()) {
            $return['status'] = 40001;
            $return['msg'] = '昵称已被注册';
            return response() -> json($return);
        }

        $password = md5('lily_'.$all['password']);
        $res = DB::table('user') -> insert([
            'u_userName' => addslashes($all['username']),
            'u_password' => $password,
            'u_nickName' => addslashes($all['name'])
        ]);

        if (!$res) {
            $return['msg'] = '注册失败，请刷新重试~';
        } else {
            $return['status'] = 200;
            $return['msg'] = '注册成功~';
        }

        return response() -> json($return);
    }
}