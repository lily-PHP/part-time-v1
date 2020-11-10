<?php
/**
 * 自定义全局函数文件
 */

/**
 * 校验验证码
 * @param $cache_id
 * @param $capcha
 * @return array
 */
if (function_exists('validate_captch')) {
    function validate_captch($cache_id, $capcha)
    {
        $redis = app('redis.connection');
//        $redis -> select(6);
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
}
