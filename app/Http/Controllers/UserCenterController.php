<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mews\Captcha\Captcha;
use Illuminate\Support\Facades\Validator;
use DB;
use App\Tool\JwtToken;

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

        //字段必填校验
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

        //校验验证码
        $capcha_validate = $this -> validateCaptch($all['sid'], $all['code']);
        if (!$capcha_validate['ack']) {
            $return['status'] = 40002;
            $return['msg'] = $capcha_validate['msg'];
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

        $password = md5('lily_'.$all['password'].'2020');
        $res = DB::table('user') -> insert([
            'u_userName' => addslashes($all['username']),
            'u_password' => $password,
            'u_nickName' => addslashes($all['name']),
            'u_add_time' => time(),
            'u_update_time' => time()
        ]);

        if (!$res) {
            $return['msg'] = '注册失败，请刷新重试~';
        } else {
            $return['status'] = 200;
            $return['msg'] = '注册成功~';
        }

        return response() -> json($return);
    }


    /**
     * 用户登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLogin(Request $request)
    {
        $return = config('return.select');
        $all = $request -> all();

        //必填字段校验
        $message = [
            'username.required' => '用户名：邮箱必填',
            'password.required' => '密码必填',
            'sid.required'      => 'UUID缺失',
            'code.required'     => '验证码必填',
        ];
        $validator = Validator::make($all, [
            'username'      => 'required',
            'password'      => 'required',
            'sid'           => 'required',
            'code'          => 'required'
        ], $message);

        if ($validator -> fails()) {
            $return['msg'] = $validator -> errors() -> first();
            return response() -> json($return);
        }

        //校验验证码
        $capcha_validate = $this ->validateCaptch($all['sid'], $all['code']);
        if (!$capcha_validate['ack']) {
            $return['status'] = 40002;
            $return['msg'] = $capcha_validate['msg'];
            return response() -> json($return);
        }

        //校验密码
        $pass = md5('lily_'.$all['password'].'2020');
        $user_info = DB::table('user')
            -> where(['u_userName'=>$all['username'], 'u_password'=>$pass])
            -> first();
        if (!$user_info) {
            $return['msg'] = '用户名或密码错误';
            return response() -> json($return);
        }

        $user_info = (array)$user_info;
        $user_infos = [
            'username' => $user_info['u_userName'],
            'name' => $user_info['u_nickName'],
            'roles' => $user_info['u_roles'],
            'gender' => $user_info['u_sex'] ? '女' : '男',
            'favs' => $user_info['u_score'],
            'create_time' => date('Y-m-d H:i:s', $user_info['u_add_time']),
            'update_time' => date('Y-m-d H:i:s', $user_info['u_update_time']),
            'location' => $user_info['u_location'],
            'isVip' => (string)$user_info['u_isVip'],
            'pic' => $user_info['u_pic'],
            'mobile' => $user_info['u_mobile'],
            'isSign' => '0', //暂时写死
            'regmark' => $user_info['u_remark'],
            'uid' => $user_info['u_id'],
        ];

        //登录成功， 将用户数据存缓存， 并生成JWT token
        $redis = app('redis.connection');
        $redis -> select(6);
        $add = $redis -> hmset('user_'.$user_infos['uid'], $user_infos);
        if (!$add) {
            $return['msg'] = '用户数据存Redis失败';
            return response() -> json($return);
        }

        //生成token
        $jwtObj = JwtToken::getInstance();
        $token = $jwtObj -> createJWTtoken($user_infos['uid'], $user_infos);
        if (!$token) {
            $return['msg'] = '生成token失败';
            return response() -> json($return);
        }

        $user_infos['token'] = $token; // JWT token
        $return['status'] = 200;
        $return['msg'] = '登录成功';
        $user_infos['roles'] = $user_infos['roles'] ? json_decode($user_infos['roles'], true) : [];
        $return['data'] = $user_infos;
        return response() -> json($return);
    }


    /**
     * 校验验证码
     * @param $cache_id
     * @param $capcha
     * @return array
     */
    private function validateCaptch($cache_id, $capcha)
    {
        $redis = app('redis.connection');
        $cache_cap = $redis -> get('Captcha_'.$cache_id); //缓存中的验证码

        if (!$cache_cap) {
            return [
                'ack' => false,
                'msg' => '验证码过期~'
            ];
        }

        if (!captcha_api_check($capcha, $cache_cap)) {
            return [
                'ack' => false,
                'msg' => '验证码错误'
            ];
        }

        return [
            'ack' => true,
            'msg' => '验证码正确'
        ];
    }


    public function test()
    {
        return 123456;
    }
}