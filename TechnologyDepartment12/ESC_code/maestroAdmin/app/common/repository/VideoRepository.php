<?php

namespace app\common\repository;

use app\common\model\VideoModel;

use think\facade\Cache;

class VideoRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        
        //绑定模型
        $this->model = new VideoModel();
    }

    public function getIndexVideoList()
    {
        return Cache::store('redis')->remember(config('redis_cache_key.index_video_list'),function(){
//            $limit = 10;
            $result = VideoModel::where('cate', 'in',[VideoModel::CATE_AD, VideoModel::CATE_INTRODUCTION])
                ->where('status', 1)
//                ->limit($limit)
                ->select();
            return $result;
        });
    }

    public function getIndexCateVideo(): array
    {
        return Cache::store('redis')->remember(config('redis_cache_key.index_cate_video'),function(){
            $categoryIds = [VideoModel::CATE_AGENT, VideoModel::CATE_PUBLISHER, VideoModel::CATE_MERCHANT];
            $limit = 10;
            $videos = [];
            foreach ($categoryIds as $categoryId) {
                $result = VideoModel::where('cate', $categoryId)
                    ->where('status', 1)
                    ->field('id')
                    ->find();

                $videos[$categoryId] = $result; // 合并每个分类的商品
            }
            return $videos;
        });
    }
}
