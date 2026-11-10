<?php

namespace app\lib\exception;

/**
 * token验证失败时抛出此异常
 */
class SignException extends BaseException
{
    public $code = 200;
    public $msg = '请求异常';
    public $errorCode = -1;
}
