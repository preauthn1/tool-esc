<?php

namespace app\admin\controller;

use app\common\model\BankModel AS Model;
use think\db\Where;
use think\facade\View;

class Bank extends Base {

    public function index() {
        if (request()->isAjax()) {
            $langs = getLangsColumn();
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['lang_str']) && $param['lang_str'] != '') {
                $where[] = ['lang', '=', $param['lang_str']];
            }elseif(isArrayAndNotEmpty($langs)){
                $where[] = ['lang', "IN", array_keys($langs)];
            }
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name|bank_code', 'LIKE', "%{$param['name']}%"];
            }
            $model = new Model();
            $result = $model->where($where)
                ->limit($offset, $limit)
                ->order([
                    'lang' => 'DESC',
                    'sort' => 'ASC'
                ])->select()->toArray();
            $total = $model->where($where)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $result[$key]['status'] = '<div class="layui-form"><input type="checkbox" name="status" value="1" title="开启|关闭" lay-skin="switch" data-id="'.$vo['id'].'" data-val="'.$vo['status'].'" lay-filter="changeStatus" '.($vo['status'] == 1 ? 'checked' : '').'></div>';
                $result[$key]['lang'] = isset($langs[$vo['lang']]) ? $langs[$vo['lang']] : '-';
                $result[$key]['logo'] = $vo['logo'] ? '<img src="' . toMedia($vo['logo']) . '" width="40px" height="40px">' : '-';
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        View::assign("langs", getLangs());
        return View::fetch();
    }

    public function add() {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            unset($param['file']);
            $result = $this->validate($param, 'BankValidate');
            if (true !== $result) {
                return json(msg(-1, '', lang($result)));
            }
            $model = new Model;
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('提现银行:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $this->success(lang('提交成功'),'','close');
        }
        $info = Model::where('id', $id)->find();
        if (empty($info)) {
            $model = new Model();
            $info = array_fill_keys($model->getTableFields(), '');
            $info['status'] = 1;
        }
        View::assign("langs", getLangs());
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除用户
    public function del() {
        $id = input('param.id');
        $info = Model::find($id);
        if ($info) {
            insert_operation_log('提现银行:删除', $info->toArray());
            $info->delete();
        }
        return json(msg(1, url('index')->build(), lang('删除成功')));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id) {
        return [
            '编辑' => [
                'auth' => 'bank/add',
                'href' => url('bank/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'bank/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }


    public function test() {
        $banks = config('bank');
        $bank_pattern = config('bank_pattern');
        $data = [];
        $model = new Model;
        foreach ($banks as $lang => $arr) {
            $i = 0;
            $bank_pattern2 = $bank_pattern[$lang];
            foreach ($arr as $name => $code) {
                $logo = '';
                if (file_exists(root_path() . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . $name)) {
                    $logo = str_replace("/upload", "", $name);
                    $name = $code;
                }
                $row = [
                    'lang' => $lang,
                    'name' => $name,
                    'bank_code' => $code,
                    'logo' => $logo,
                    'sort' => $i,
                    'status' => 1,
                    'bank_number_rule' => isset($bank_pattern2[$code]) ? $bank_pattern2[$code] : ''
                ];
                $exists = $model->where([
                    'lang' => $lang,
                    'bank_code' => $code,
                ])->find();
                if ($exists) {
                    continue;
                }
                $i++;
                $data[] = $row;
            }
        }
        $model = new Model;
        $res = $model->saveAll($data);
        dump($res);
        die;
    }

    /**
     * 切换状态
     */
    public function changeStatus() {
        $id = input('param.id/d');
        $val = input('param.val/d');

        $model = new Model();
        $info = $model->where('id', $id)->find();
        if (!$info) {
            return json(['code' => 0, 'msg' => lang('记录不存在')]);
        }

        $status = $val == 1 ? 0 : 1; // 切换状态
        $info->status = $status;
        $info->save();

        insert_operation_log('提现银行:修改状态', ['id' => $id, 'status' => $status]);
        return json(['code' => 1, 'msg' => lang('操作成功')]);
    }
}
