<?php

namespace app\api\controller;

use app\common\model\ArticleModel;
use think\facade\Cache;
use app\BaseController;
use think\facade\Lang;
use think\response\Json;

class Article extends BaseController
{
    public function index(): Json
    {
        $page = input('page/d') ? input('page/d') : 1;   // 页数
        $pageSize = input('pageSize/d') ? input('pageSize/d') : 10;  // 每页条数
        $type = input('type/id');

        $lang = Lang::getLangSet();
        $where = [];
        if ($lang) {
            $where[] = ['lang', '=', $lang];
        }
        if ($type) {
            $where[] = ['type', '=', $type];
        }
        $articles = ArticleModel::field('id,title,thumb,content')
            ->where($where)
            ->where('status', 1)
            ->order(['sort' => 'asc'])
            ->paginate(['page' => $page, 'list_rows' => $pageSize]);

        $data = [];
        foreach ($articles as $item) {
            $data[] = [
                'id' => $item->id,
                'title' => $item->title ? $item->title : "",
                'thumb' => toMedia($item->thumb),
                'content' => $item->content ? nl2br($item->content) : "",
            ];
        }

        if (!$articles) {
            return json(msg(-1, '', lang('暂无数据')));
        }

        return json(msg(0, [
            'total' => $articles->total(),
            'per_page' => $articles->listRows(),
            'current_page' => $articles->currentPage(),
            'last_page' => $articles->lastPage(),
            'data' => $data,
        ], ''));
    }

    public function detail(): Json
    {
        $lang = Lang::getLangSet();
        $id = input('id/d', 0);
        $type = input('type/id', 0);

        $where = [];
        if ($id) {
            $where[] = ['id', '=', $id];
        }
        if ($type) {
            $where[] = ['type', '=', $type];
        }
        if ($lang) {
            $where[] = ['lang', '=', $lang];
        }
//        print_r($where);
        if ($lang && $type) {
            $redis = Cache::store('redis');
            $redis_key = config("redis_cache_key.article_type").'_'.$type.'_'.$lang;
            $article = $redis->remember($redis_key, function() use($where) {
                return ArticleModel::field('id,title,thumb,content')->where($where)
                    ->where('status', 1)
                    ->find();
            }, 300);
        } else {
            $article = ArticleModel::field('id,title,thumb,content')->where($where)
                ->where('status', 1)
                ->find();
        }

        if (!$article) {
            return json(msg(-1, '', lang('暂无数据')));
        }
        $info['title'] = $article->title ? $article->title : '';
        $info['thumb'] = toMedia($article['thumb'], 1);
        $info['content'] = $article->content ? $article->content : "";
        $info['content'] = html_entity_decode($info['content']);
        return json(msg(0, $info, ''));
    }
}
