<?php

namespace app\api\controller;

use app\api\transform\NoticeTransform;
use app\common\repository\NoticeRepository;
use app\BaseController;
use think\facade\Lang;
use think\response\Json;

class Notice extends BaseController
{
    public function index(NoticeRepository $repository): Json
    {
        $notices = $repository->i18n_notices();
        return json(msg(0, $notices, ''));
    }

}
