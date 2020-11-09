<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mews\Captcha\Captcha;

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
     * 校验验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userRegister(Request $request)
    {
        $return = config('return.select');
        $key = $request -> input('sid') ?? '';
        $cap = $request -> input('code') ?? '';
        if (!$key || !$cap) {
            return response() -> json($return);
        }

        $redis = app('redis.connection');
        $redis -> select(6);
        $cache_cap = $redis -> get('Captcha_'.$key);

        if (!$cache_cap) {
            $return['msg'] = '验证码过期~';
            return response() -> json($return);
        }

        if (!captcha_api_check($cap, $cache_cap)) {
            $return['status'] = 403;
            $return['msg'] = '验证码错误';
        } else {
            $return['status'] = 200;
            $return['msg'] = '验证码正确~';
        }

        return response() -> json($return);
    }
}