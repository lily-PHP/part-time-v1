<?php

namespace App\Http\Middleware;

use Closure;
use App\Tool\JwtToken;

class VerifyLogin
{

    protected $except = [

    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $method = '/'.$request->path();

        if (!in_array($method, $this->except)) {
            $headerToken = isset($_SERVER['HTTP_AUTHTOKEN']) ? $_SERVER['HTTP_AUTHTOKEN'] : null;
            if (!$headerToken) {
                //请求头缺失token
                $return = [
                    'status'=> 401,
                    'msg'=>'Authorization Token Required'
                ];
                return response() -> json($return);
            }

            $jwtObj = jwtToken::getInstance();
            $is_verify = $jwtObj -> checkJWT($headerToken); //调用校验方法， 失败返回false，成功返回JWT TOKEN
            if (!$is_verify) {
                //token校验失败
                $return = [
                    'status'=> 401,
                    'msg'=>'Incorrect Token'
                ];
                return response() -> json($return);
            }

            //从Redis获取用户数据
            $uid = $jwtObj->uid; //用户ID
            $redis = app('redis.connection');
            $userInfo = $redis ->hgetall('user_'.$uid);

            if (!$userInfo) {
                $return = [
                    'code'=>403,
                    'msg'=>'请重新登录'
                ];
                return response() -> json($return);
            }

            //校验用户页面访问权限。。。。。

            header('Authtoken:'. $is_verify); //校验通过，设置响应头返回token
        }

        return $next($request);
    }

}
