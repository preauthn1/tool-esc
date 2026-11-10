<?php

namespace app\admin\controller;

use app\admin\transform\InvestmentProductTransform;
use app\common\model\InvestmentProductModel;
use think\db\exception\DbException;
use think\facade\View;

/**
 * 投资产品管理
 * Class InvestmentProduct
 * @package app\admin\controller
 */
class InvestmentProduct extends Base
{
    /**
     * 产品列表
     * @return mixed
     * @throws DbException
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $param = input('param.');
            $page = input('page/d') ? input('page/d') : 1;   // 页数
            $pageSize = input('pageSize/d') ? input('pageSize/d') : 10;  // 每页条数

            $where = [];
            if (!empty($param['product_name'])) {
                $where[] = ['product_name', 'like', '%'.$param['product_name'].'%'];
            }
            $selectResult = InvestmentProductModel::where($where)
                ->order('sort')
                ->paginate(['page' => $page, 'list_rows' => $pageSize]);
            $data = (new InvestmentProductTransform())->transformPages($selectResult->toArray());
            return json($data);
        }

        return View::fetch();
    }

    /**
     * 添加产品
     * @return mixed
     */
    public function add()
    {
        $id = $this->request->param("id/d");
        $model = new InvestmentProductModel();
        if ($this->request->isPost()) {
            $param = input('post.');


            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('投资产品:'.($id > 0 ? '修改' : '添加'), $param);
            }
            $this->success(lang('提交成功'), '', 'close');

        }
        $info = $model->where('id', $id)->find();
        View::assign("info", $info);
        return View::fetch();
    }


    /**
     * 删除产品
     * @return \think\response\Json
     */
    public function del()
    {
        $id = input('param.id');
        $info = InvestmentProductModel::find($id);
        if ($info) {
            insert_operation_log('投资产品:删除', $info->toArray());
            $info->delete();
        }

        return json(msg(1, url('index')->build(), lang('删除成功')));
    }

    /**
     * 修改产品状态
     * @return \think\response\Json
     */
    public function changestatus()
    {
        $param = $this->request->param();

        $field = '';
        switch ($param['type']) {
            case 'is_show':
                $field = 'is_show';
                break;
            case 'is_can_buy':
                $field = 'is_can_buy';
                break;
            case 'status':
                $field = 'status';
                break;
            case 'sales_status':
                $field = 'sales_status';
                break;
            default:
                $this->error(lang('参数错误'));
        }
        $res = InvestmentProductModel::update([$field => !$param['val']], ['id' => $param['id']]);
        if ($res) {
            $this->success(lang('修改成功'));
        }

        $this->error(lang('修改失败'));
    }

}
