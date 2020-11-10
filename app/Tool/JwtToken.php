<?php
namespace App\Tool;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Hmac\Sha256;

/**
 * jwt token生成类
 */
class JwtToken
{
    /**
     * token 过期时间
     * @var int 过期时间 秒
     */
    private static $expireTime = 86400; // 60*60*24 有效期24小时

    /**
     * JWT唯一标识ID， 通过uniqid()函数生成
     * @var string
     */
    private static $jti = '5ce24e4a64d48';


    //随机生成的字符串
    private static $secret = 'mBC5v1sOKVvbdEitdSBenu';

    /**
     * jwtToken 实例化唯一对象
     * @var object jwtToken 实例化唯一对象
     */
    private static $_object=null;


    public $uid = null;


    /**
     * 静态工厂方法，返回jwtToken唯一实例对象
     * return object  [jwtToken唯一实例对象]
     */
    public static function getInstance()
    {
        if (is_null(self::$_object)) {
            self::$_object = new self();
        }
        return self::$_object;
    }

    /**
     * 创建用户 JWT token
     * @param  int   $uid      用户ID
     * @param  array  $userInfo [userName=>'', userRole=>'']
     * @return string   $token    返回token
     */
    public function createJWTtoken($uid, $userInfo = [])
    {
        $builder = new Builder();
        $signer = new Sha256();

        //设置载荷参数
        $iss = $_SERVER['SERVER_NAME'];
        $aud = $_SERVER['REMOTE_ADDR'];
        $jti = self::$jti;
        $signKey = md5(md5($uid.self::$secret.$uid));

        //开始创建token
        $tokenBuilder = $builder ->setIssuer($iss)
            ->setAudience($aud)
            ->setId($jti, true)
            ->setIssuedAt(time())
            ->setExpiration(time() + self::$expireTime)
            ->set('uid', $uid);
        if (isset($userInfo['username'])) {
            $tokenBuilder -> set('userName', $userInfo['username']);
        }
        if (isset($userInfo['roles'])) {
            $tokenBuilder -> set('role', $userInfo['roles']);
        }

        $token = $tokenBuilder -> sign($signer, $signKey) ->getToken();

        $this -> uid = $uid; //用户ID

        return (string)$token;
    }

    /**
     * 校验JWT token
     * @param  string $token 要校验的token
     * @return mixed  校验失败：false;   成功：返回原token或者更新的token
     */
    public function checkJWT($token)
    {

        try {
            $token = (new Parser())->parse((string) $token);
            $signer = new Sha256();

            //验证用户ID
            $uid = $token -> getClaim('uid');
            if (empty($uid)) {
                return false;
            }
            //校验用户信息是否正确
            $redis = app('redis.connection');
//            $redis -> select(6);
            $user = $redis -> exists('user_'.$uid);
            if (!$user) {
                return false;
            }

            $this -> uid = $uid;

            //验证签名
            $signKey = md5(md5($uid.self::$secret.$uid));
            $signVerify = $token -> verify($signer, $signKey);
            if (!$signVerify) {
                return false;
            }

            //验证token
            $iss = $_SERVER['SERVER_NAME'];
            $aud = $_SERVER['REMOTE_ADDR'];
            $jti = self::$jti;
            $data = new ValidationData(); // It will use the current time to validate (iat, nbf and exp)
            $data->setIssuer($iss);
            $data->setAudience($aud);
            $data->setId($jti);
            $validate = $token->validate($data);
            if (!$validate) {
                return false;
            }

            //验证token有效期
            $exp = $token -> getClaim('exp');
            if ($exp - time() < 3600) {
                //有效期只有1个小时，重新生成一个jwt
                $userInfo = $redis->hgetall('user_'.$uid);
                $newToken = $this -> createJWTtoken($uid, $userInfo);
                return (string)$newToken;
            }
            //有效期大于1个小时，返回现有token
            return (string)$token;
        } catch (\Exception $e) {
            return false;
//            return $e->getMessage();
        }
    }
}
