<?php

namespace app\lib\exception;

/**
 * token验证失败时抛出此异常
 */
class TokenException extends BaseException
{
    public $code = 200;
    public $msg = '登录已退出';
    public $errorCode = 10001;
}
