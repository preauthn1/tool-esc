<?php

use app\common\model\UserModel;
use app\lib\exception\TokenException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function hideUserName($str)
{
    $len = strlen($str) - 3;
    $out_str = "";
    for ($i = 0; $i < $len; $i++) {
        $out_str .= "*";
    }
    return substr($str, 0, 2) . $out_str . substr($str, -1);
}

/**
 * 获取浏览器默认语言
 * @return mixed|string
 */
function getLanguage()
{
    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return '';
    }
    $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    $language_data = explode(';', $language);
    if (!isset($language_data[0])) {
        return '';
    }
    $language_en = explode(',', $language_data[0]);
    if (!isset($language_en[0])) {
        return '';
    }
    return $language_en[0];
}

//获取host
function getHost()
{
    $port = $_SERVER['SERVER_PORT'];
    $http_host = $_SERVER['HTTP_HOST'];
    if ($port == 80) {
        $host = 'http://' . $http_host;
    } else if ($port == 443) {
        $host = 'https://' . $http_host;
    } else {
        $host = 'http://' . $http_host . ':' . $port;
    }
    return $host;
}

function get_real_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
        $ip = $_SERVER['HTTP_CDN_SRC_IP'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
        foreach ($matches[0] as $xip) {
            if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                $ip = $xip;
                break;
            }
        }
    }
    return $ip;
}

/**
 * @throws TokenException
 */
function check_token($token)
{
    if (!$token) {
        throw new TokenException();
    }
    try {
        // TODO
        // FIXME 判断token是否被加入了黑名单
        $tokenInfo = (array)JWT::decode($token, new Key(config('app.jwt_key'), 'HS256'));
        if ($tokenInfo['create_time'] + $tokenInfo['expires_in'] < time()) {
            throw new TokenException();
        }
        $userModel = new UserModel();
        $user_info = $userModel->where('id', $tokenInfo['user_id'])->find();
        if (!$user_info) {
            throw new TokenException();
        }

        // 验证login_key是否匹配
        if (!isset($tokenInfo['login_key']) || $tokenInfo['login_key'] !== $user_info['login_key']) {
            throw new TokenException();
        }

        return $user_info;
    } catch (\Exception $e) {
        throw new TokenException();
    }
}
