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

namespace app\admin\controller;

use app\admin\model\ArticleModel;
use app\admin\model\ArticleModel as Model;
use app\common\model\ArticleInfoModel;
use app\common\model\LangModel;
use app\services\GoogleTranslate;
use think\facade\Cache;
use think\facade\View;

class Articles extends Base
{

    // 文章列表
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['searchText'])) {
                $where[] = ['title', 'like', '%' . $param['searchText'] . '%'];
            }

            if (!empty($param['type'])) {
                $where[] = ['type', '=', $param['type']];
            }
            $article = new Model();
            $selectResult = $article->getArticlesByWhere($where, $offset, $limit);
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['status'] = $vo['status'] == 1 ? '启用' : '停用';
                $selectResult[$key]['type'] = ArticleModel::$typeMap[$vo['type']];
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                if ($selectResult[$key]['url'] == '') {
                    $selectResult[$key]['url'] = "/article/index/id/{$vo['id']}";
                }
                $selectResult[$key]['thumb'] = toMedia($vo['thumb']);
            }
            $return['total'] = $article->getAllArticles($where);  // 总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        $article_types = ArticleModel::$typeMap;
        View::assign('article_types', $article_types);
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            //id: 88
            //type: 14
            //lang: zh-cn
            //title: 信用
            //status: 1
            //sort: 0
            //thumb:
            //file:
            //content:
            $param['id'] = $id;
            $param['type'] = $this->request->param('type', 0);
            $param['lang'] = $this->request->param('lang', 'pt');
            $param['title'] = $this->request->param('title/s', '');
            $param['status'] = $this->request->param('status/d', 0);
            $param['sort'] = $this->request->param('sort/d', 0);
            $param['thumb'] = $this->request->param('thumb/s', '');
            $param['content'] = $this->request->param('content/s', '');
            $result = $this->validate($param, 'ArticleValidate');
            if (true !== $result) {
                return json(msg(-1, '', lang($result)));
            }
            $model = new Model;
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
                $param['id'] = $model->getLastInsID();
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('文章:' . ($id > 0 ? '修改' : '添加'), $param['title']);
            }
            $param['tr_title'] = $this->request->param('tr_title/a', []);
            $param['tr_content'] = $this->request->param('tr_content/a', []);
            $tr_title = $param['tr_title'];
            $tr_content = $param['tr_content'];
            $add_list = [];
            foreach ($tr_title as $key => $value) {
                $add_list[] = [
                    'lang' => $key,
                    'article_id' => $param['id'],
                    'title' => $tr_title[$key],
                    'content' => $tr_content[$key],
                ];
            }
            ArticleInfoModel::addInfo($param['id'], $add_list);
            $this->clearCache('article_type', $param['type'], $param['lang']);
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = Model::where('id', $id)->find();
        if (empty($info)) {
            $model = new Model();
            $info = array_fill_keys($model->getTableFields(), '');
            $info['status'] = 1;
        }
        $lang_list = LangModel::where('status', 1)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
        $tr_title = [];
        $tr_step = [];
        $tr_content = [];
        foreach ($lang_list as $value) {
            $tr_title[$value['lang']] = '';
            $tr_step[$value['lang']] = '';
            $tr_content[$value['lang']] = '';
        }
        if (!empty($id)) {
            $share_id = $id;
            $list = ArticleInfoModel::where('article_id', $share_id)->select();
            foreach ($list as $value) {
                $tr_title[$value['lang']] = $value['title'];
                $tr_content[$value['lang']] = $value['content'];
            }
        }

        View::assign("info", $info);
        View::assign("article_types", ArticleModel::$typeMap);
        View::assign("langs", $lang_list);
        View::assign('tr_title', $tr_title);
        View::assign('tr_content', $tr_content);
        View::assign('base_url', config('app.aws_s3.url'));

        return View::fetch();
    }

    public function del()
    {
        $id = input('param.id');
        $article = new Model();
        $flag = $article->delArticle($id);
        return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '编辑' => [
                'auth' => 'articles/add',
                'href' => url('articles/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'articles/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

    public function uploadimg()
    {
        $ue_path = root_path() . "/public/static/admin/js/plugins/ueditor/php/";
        $CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents($ue_path . "config.json")), true);
        $action = $this->request->param("action/s");

        switch ($action) {
            case 'config':
                $result = $CONFIG;
                break;

            /* 上传图片 */
            case 'uploadimage':
                /* 上传涂鸦 */
            case 'uploadscrawl':
                /* 上传视频 */
            case 'uploadvideo':
                /* 上传文件 */
            case 'uploadfile':
                $UploadModel = new \app\common\model\UploadModel();
                $ret = $UploadModel->Upload('upfile');
                $ret['data']['src'] = toMedia($ret['data']['path']);
                $ret['data']['url'] = toMedia($ret['data']['path']);
                if ($ret['code'] == 200) {
                    $result = [
                        "state" => "SUCCESS",
                        "url" => toMedia($ret['data']['path']),
                        "title" => $ret['data']['name'],
                        "original" => $ret['data']['name'],
                        "type" => $ret['data']['ext'],
                        "size" => $ret['data']['size']
                    ];
                } else {
                    $result = ['state' => lang($ret['msg'])];
                }
                break;

            /* 列出图片 */
//            case 'listimage':
            /* 列出文件 */
//            case 'listfile':
            /* 抓取远程文件 */
//            case 'catchimage':
            default:
                $result = ['state' => lang('请求地址出错')];
                break;
        }
        return json($result);
    }


    public function translate()
    {
        $trans = new GoogleTranslate();
        $source = 'zh-CN';//Source language, 源语言
        $data = [];
        $text = input('text', '');
        $lang_list = LangModel::where('status', 1)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
        if (!$text) {
            $this->error(lang('请输入需要翻译的内容'));
        }
        foreach ($lang_list as $lang) {
            if ($lang->lang == 'zh-cn') {
                $lang_text = "zh-CN";
            } else if ($lang->lang == 'zh-tw') {
                $lang_text = "zh-TW";
            } else {
                $lang_text = $lang->lang;
            }
            $result = $trans->translate($source, $lang_text, $text);
            $data[$lang->lang] = $result;
        }
        $this->success(lang('翻译成功'), '', $data);
    }

    public function clearCache($key, $value, $lang)
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key." . $key) . '_' . $value . '_' . $lang;
        $redis->delete($redis_key);
    }
}
