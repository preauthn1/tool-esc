<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://baiyf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------
namespace app\common\model;

use think\Model;
use think\facade\Db;
use think\model\relation\HasMany;

class ArticleModel extends Model
{
    // 确定链接表名
    protected $name = 'articles';

    /**
     * 查询文章
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getArticlesByWhere($where, $offset, $limit)
    {
        $lists = $this->alias('a')
            ->where($where)
            ->limit($offset, $limit)
            ->field('a.*')
            ->order('a.id desc')
            ->select();
        return $lists;
    }

    /**
     * 根据搜索条件获取所有的文章数量
     * @param $where
     */
    public function getAllArticles($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 根据文章的id 获取文章的信息
     * @param $id
     */
    public function getOneArticle($id)
    {
        $info = $this->alias('a')
            ->field('a.*')
            ->order('a.id desc')
            ->where('a.id', $id)
            ->find();
        return $info;
    }

    public function info(): HasMany
    {
        return $this->hasMany(ArticleInfoModel::class, 'article_id', 'id');
    }
}
