<?php

namespace app\api\controller;

class BaseController extends \app\BaseController
{
    protected $userInfo = [];
    protected function checkLogin(\think\App $app): bool
    {
        $base = new Base($app);
        $this->userInfo = $base->userInfo;
        $this->userid = $this->userInfo['id'];
        return true;
    }
}
