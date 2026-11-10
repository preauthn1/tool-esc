<?php

namespace app\lib\exception;

/**
 * token验证失败时抛出此异常
 */
class LockException extends BaseException
{
    public $code = 200;
    public $msg = '系统繁忙，请稍后再试';
    public $errorCode = -1;
}
