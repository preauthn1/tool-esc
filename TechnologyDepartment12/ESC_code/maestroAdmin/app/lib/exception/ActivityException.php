<?php

namespace app\lib\exception;

/**
 * 活动验证失败时抛出此异常
 */
class ActivityException extends BaseException
{
    public $code = 200;
    public $msg = '活动不存在';
    public $errorCode = -1;
}
