<?php

namespace app\common\repository;

use app\admin\model\NoticeModel;
use app\api\transform\NoticeTransform;

use think\facade\Cache;
use think\facade\Lang;

class NoticeRepository extends Repository
{
    protected $model;

    public function __construct()
    {

        //绑定模型
        $this->model = new NoticeModel();
    }

    public function i18n_notices()
    {
        $range = Lang::getLangSet();
        if (!$range) {
            $range = 'zh-cn';
        }

        $notices = Cache::store('redis')->remember(config("redis_cache_key.notice").md5($range), function () use($range) {
            return $this->has('info')
                ->with([
                    'info' => function ($query) use ($range) {
                        $query->where('lang', $range);
                    }
                ])
                ->order(['create_time' => 'desc'])
                ->where(['status' => 1])
                ->paginate(99);
        });
        return (new NoticeTransform)->transformPages($notices->toArray());
    }
}
