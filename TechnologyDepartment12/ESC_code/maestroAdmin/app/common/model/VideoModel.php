<?php

namespace app\common\model;

use app\admin\model\LevelModel;
use think\Model;
use think\model\relation\BelongsTo;

class VideoModel extends Model
{

    protected $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'video';


    const CATE_AGENT = 1;
    const CATE_PUBLISHER = 2;
    const CATE_MERCHANT = 3;
    const CATE_AD = 4;
    const CATE_INTRODUCTION = 5;
    const CATE_INDEX = 6;

    static array $cateMap = [
        // 2025年11月3日需求：删除广告代理商、广告发行商、广告主
//        self::CATE_AGENT     => '广告代理商',
//        self::CATE_PUBLISHER => '广告发行商',
//        self::CATE_MERCHANT  => '广告主',
        self::CATE_INDEX     => '首页',
        self::CATE_AD        => '广告',
        self::CATE_INTRODUCTION        => '介绍'
    ];

    /**
     * 更新视频状态
     * @param  array  $param
     * @param  array  $where
     * @return array
     */
    public function updateStatus(array $param = [], array $where = []): array
    {
        try {
            $this->where($where)->update($param);
            return msg(1, '', 'ok');
        } catch (\Exception $e) {

            return msg(-1, '', $e->getMessage());
        }
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(LevelModel::class, 'level_id', 'level_id');
    }
}
