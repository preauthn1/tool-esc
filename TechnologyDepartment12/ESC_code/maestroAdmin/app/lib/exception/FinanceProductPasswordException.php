<?php

namespace app\lib\exception;

/**
 * token验证失败时抛出此异常
 */
class FinanceProductPasswordException extends BaseException
{
    public $code = 200;
    public $msg = '编码不正确';
    public $errorCode = -1;
}
