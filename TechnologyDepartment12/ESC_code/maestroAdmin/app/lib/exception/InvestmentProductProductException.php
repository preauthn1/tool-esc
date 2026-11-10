<?php

namespace app\lib\exception;

/**
 * token验证失败时抛出此异常
 */
class InvestmentProductProductException extends BaseException
{
    public $code = 200;
    public $msg = 'AI结算系统维护中';
    public $errorCode = -1;
}
