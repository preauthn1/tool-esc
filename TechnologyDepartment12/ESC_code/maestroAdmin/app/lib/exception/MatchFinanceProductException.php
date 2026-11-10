<?php

namespace app\lib\exception;

/**
 * token验证失败时抛出此异常
 */
class MatchFinanceProductException extends BaseException
{
    public $code = 200;
    public $msg = '广告库存已售空';
    public $errorCode = -1;
}
