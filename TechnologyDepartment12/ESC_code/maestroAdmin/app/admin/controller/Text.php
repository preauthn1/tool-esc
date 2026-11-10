<?php

namespace app\admin\controller;

use app\common\model\LangItemDetailModel;
use app\common\model\LangItemModel;
use app\common\model\LangModel;
use think\facade\Env;
use think\facade\View;

class Text extends Base
{

    public function index()
    {
        $act = $this->request->param('act/s');
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $searchText = $param['searchText'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if ($searchText) {
                $where[] = ['title', 'like', "%{$searchText}%"];
            }
            $model = new LangItemModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->count();
            foreach ($result as $key => $vo) {
                $details = $vo->detail()->column('text', 'lang_id');
                foreach ($details as $lid => $text) {
                    $result[$key]['text_' . $lid] = show_img($text, true);
                }
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        $langs = LangModel::where('status', 1)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
        View::assign('langs', $langs);
        if ($act == 'all') {
            $items = LangItemModel::order('id ASC')->select();
            foreach ($items as $key => $item) {
                $items[$key]['details'] = $item->detail()->column('text', 'lang_id');
            }
            View::assign('items', $items);
            return View::fetch('index_all');
        } elseif ($act == 'export') {
            $header = [
                'item_id' => '文字id',
                'text' => '文字',
            ];
            foreach ($langs as $lang) {
                $header["lang_{$lang->id}"] = $lang->title;
            }
            $list = [];
            $items = LangItemModel::order('id ASC')->select();
            foreach ($items as $key => $item) {
                $details = $item->detail()->column('text', 'lang_id');
                $row = [
                    'item_id' => $item->id,
                    'text' => $item->title
                ];
                foreach ($details as $lid => $text) {
                    $row["lang_{$lid}"] = $text;
                }
                $list[] = $row;
            }
            genXlsx($header, $list);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        $langs = LangModel::where('status', 1)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
        if (request()->isPost()) {
            $title = $this->request->param("title/s");
            $newlang = $this->request->param("newlang/a");
            if (empty($title)) {
                $this->error('请填写文字');
            }
            if ($id > 0) {
                $item = LangItemModel::where(['id' => $id])->find();
                $item->title = $title;
                $item->save();
            } else {
                $item = LangItemModel::where(['title' => trim($title)])->find();
                if (!$item) {
                    $item = LangItemModel::create(['title' => trim($title)]);
                }
            }

            foreach ($langs as $j => $lang) {
                $detail = [
                    'item_id' => $item->id,
                    'lang_id' => $lang->id,
                ];
                $item_detail = LangItemDetailModel::where($detail)->find();
                $text = isset($newlang[$lang->id]) ? trim($newlang[$lang->id]) : '';
                if ($item_detail) {
                    $item_detail->text = $text;
                    $item_detail->save();
                } else {
                    $detail['text'] = $text;
                    LangItemDetailModel::create($detail);
                }
            }
            $this->reWriteLang();
            $this->success(lang('提交成功'), '', 'close');
        }
        $item = LangItemModel::find($id);
        $details = [];
        if ($item) {
            $details = $item->detail()->column('text', 'lang_id');
        }
        View::assign('info', $item);
        View::assign('details', $details);
        View::assign('langs', $langs);
        return View::fetch();
    }

    public function add2()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $olditems = $this->request->param("olditems/a");
            $oldlang = $this->request->param("oldlang/a");
            $newitems = $this->request->param("newitems/a");
            $newlang = $this->request->param("newlang/a");
            $langs = LangModel::where('status', 1)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
            if (!empty($olditems)) {
                foreach ($olditems as $item_id => $title) {
                    if (empty($title)) {
                        continue;
                    }

                    LangItemModel::where('id', $item_id)->update(['title' => trim($title)]);
                    foreach ($langs as $j => $lang) {
                        $detail = [
                            'item_id' => $item_id,
                            'lang_id' => $lang->id,
                        ];
                        $item_detail = LangItemDetailModel::where($detail)->find();
                        $text = isset($oldlang[$lang->id][$item_id]) ? trim($oldlang[$lang->id][$item_id]) : '';
                        if ($item_detail) {
                            $item_detail->text = $text;
                            $item_detail->save();
                        } else {
                            $detail['text'] = $text;
                            LangItemDetailModel::create($detail);
                        }
                    }
                }
            }
            if (!empty($newitems)) {
                foreach ($newitems as $i => $title) {
                    if (empty($title)) {
                        continue;
                    }

                    $item = LangItemModel::where(['title' => trim($title)])->find();
                    if (!$item) {
                        $item = LangItemModel::create(['title' => trim($title)]);
                    }
                    foreach ($langs as $j => $lang) {
                        $detail = [
                            'item_id' => $item->id,
                            'lang_id' => $lang->id,
                        ];
                        $item_detail = LangItemDetailModel::where($detail)->find();
                        $text = isset($newlang[$lang->id][$i]) ? trim($newlang[$lang->id][$i]) : '';
                        if ($item_detail) {
                            $item_detail->text = $text;
                            $item_detail->save();
                        } else {
                            $detail['text'] = $text;
                            LangItemDetailModel::create($detail);
                        }
                    }
                }
            }
            $this->reWriteLang();
            return json(msg(1, url('index'), '保存成功'));
        }

    }

    public function del()
    {
        $id = $this->request->param("id/d");
        $item = LangItemModel::find($id);
        if ($item) {
            $item->detail()->delete();
            $item->delete();
            return json(msg(1, url('index'), lang('删除成功')));
        } else {
            return json(msg(-1, url('index'), lang('删除失败')));
        }
    }

    private function reWriteLang()
    {
        $langs = LangModel::where('status', 1)->order(['is_default' => 'DESC', 'id' => 'ASC'])->select();
        $items = LangItemModel::order(['id' => 'DESC'])->distinct(true)->select();
        $items_detail = LangItemDetailModel::field('lang_id,item_id,text')->select();
        $items_detail_arr = [];
        foreach ($items_detail as $item) {
            $items_detail_arr[$item['lang_id'] . "_" . $item['item_id']] = $item['text'];
        }
        $lang_config = [];
        foreach ($langs as $lang) {
            $lang_config[$lang->lang] = [];
            foreach ($items as $item) {
                if(isset($items_detail_arr[$lang->id . "_" . $item['id']])) {
                    $lang_config[$lang->lang][$item->title] = $items_detail_arr[$lang->id . "_" . $item['id']];
                }
            }
        }
        foreach ($lang_config as $lang => $config) {
            $admin_lang_file = base_path() . "admin/lang/{$lang}.php";
            $api_lang_file = base_path() . "api/lang/{$lang}.php";
            $code = "<?php \r\nreturn " . var_export(myArrayFilter($config), true) . ";";
            file_put_contents($admin_lang_file, $code);
            file_put_contents($api_lang_file, $code);
//            $handle = fopen($lang_file, 'w');
//            fwrite($handle, $code);
//            fclose($handle);
        }
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
                'auth' => 'text/add',
                'href' => url('text/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'text/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

    private function getLangTextData($p, $lang)
    {
        $lang_file = root_path() . "data/{$p}-$lang.txt";
        if (file_exists($lang_file)) {
            $content = file_get_contents($lang_file);
            $data = explode("\n", $content);
        } else {
            $data = null;
        }
        return $data;
    }


    public function test()
    {
        $pl = $this->request->param('pl/s');
        $update = $this->request->param('up/d', 0);
        $sl = $this->request->param('l/s', '');
        if (empty($pl)) {
            die('请输入pl');
        }
        $pa = [
            'app100', 'yigou', 'cme', 'legou', 'qmg', 'thehome', 'pd'
        ];
        foreach ($pa as $p) {
            if ($pl != $p) {
                continue;
            }
            $text_file = root_path() . "data/{$p}-text.txt";
            if (!file_exists($text_file)) {
                continue;
            }
            $text = file_get_contents($text_file);
            $text_data = explode("\n", $text);
            $l = ['zh-cn', 'ind', 'vn', 'en', 'thb', 'idn'];
            foreach ($l as $l2) {
                if ($l2 == 'zh-cn') {
                    $l3 = 'zh';
                } else {
                    $l3 = $l2;
                }
                if ($sl != '' && $sl != $l3) {
                    continue;
                }
                ${"{$l3}_data"} = $this->getLangTextData($p, $l2);
            }
            dump("p===={$p}========");
            foreach ($text_data as $k => $title) {
                $title = trim($title);
                $item = LangItemModel::where(['title' => trim($title)])->find();
                if ($item) {
                    foreach ($l as $export_lang) {
                        if ($export_lang == 'zh-cn') {
                            $export_lang2 = 'zh';
                        } else if ($export_lang == 'zh-tw') {
                            $export_lang2 = "zh-TW";
                        } else {
                            $export_lang2 = $export_lang;
                        }
                        $lang_data = ${"{$export_lang2}_data"};
                        if (is_null($lang_data)) {
                            continue;
                        }
                        $lang_id = LangModel::where('lang', $export_lang)->value('id');
                        if (empty($lang_id)) {
                            continue;
                        }
                        $detail_data = [
                            'lang_id' => $lang_id,
                            'item_id' => $item->id,
                        ];
                        $detail = LangItemDetailModel::where($detail_data)->find();
                        $text = trim($lang_data[$k]);
                        if ($detail) {
                            if (!strexists($text, 'upload') && $detail->text != $text && $text != '' && $update == 1) {
                                $detail->text = $text;
                                $detail->save();
                                dump("update==================={$title}==={$text}===========");
                            } else {
                                echo("<font color='red'>no update==================={$title}===={$text}==========</font><br>");
                            }
                        } else {
                            $detail_data['text'] = $text;
                            LangItemDetailModel::create($detail_data);
                            dump("create==================={$title}======={$text}=======");
                        }
                    }
                } elseif ($update == 1) {
                    LangItemModel::create([
                        'title' => $title
                    ]);
                    dump("no find==================={$title}==============");
                }
            }
        }
    }
}
