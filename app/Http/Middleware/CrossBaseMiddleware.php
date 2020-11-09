<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

header('Access-Control-Allow-Origin:*'); // 允许所有域名跨域
header('Access-Control-Allow-Methods:POST, GET, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers:x-requested-with,content-type,Authtoken');
header('Access-Control-Expose-Headers:HTTP_AUTHTOKEN,HTTP_BUTTON');
class CrossBaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $method = $request->header('method');
        if($method == 'OPTIONS'){
            return abort(204);

        }
        return $next($request);
    }
}