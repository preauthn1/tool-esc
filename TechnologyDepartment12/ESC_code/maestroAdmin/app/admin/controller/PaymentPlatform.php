<?php

namespace app\admin\controller;

use app\admin\model\ConfigModel;
use app\admin\validate\PaymentPlatformValidate;
use app\common\model\PaymentPlatformModel;
use think\facade\View;

class PaymentPlatform extends Base
{
    public function index()
    {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }

            $result = PaymentPlatformModel::where($where)->limit($offset, $limit)->select();
            $total = PaymentPlatformModel::where($where)->count();
            // 拼装参数
            foreach ($result as $key => $vo) {
                $currency_type = '';
                switch ($vo['currency_type']) {
                    case 1:
                        $currency_type = '虚货币';
                        break;
                    case 2:
                        $currency_type = '货币';
                        break;
                    case 3:
                        $currency_type = '银行卡';
                        break;
                }
                $result[$key]['currency_type_str'] = $currency_type;
                $result[$key]['image'] = $vo['image'] ? '<img src="' . toMedia($vo['image']) . '" width="40px" height="40px">' : '-';
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            unset($param['file']);
            if (!array_key_exists('recharge_is_show', $param)) {
                $param['recharge_is_show'] = 0;
            }
            if (!array_key_exists('withdraw_is_show', $param)) {
                $param['withdraw_is_show'] = 0;
            }
            if (!array_key_exists('is_first_charge', $param)) {
                $param['is_first_charge'] = 0;
            }
            $validate = new PaymentPlatformValidate();
            if (!$validate->check($param)) {
                $this->error($validate->getError());
            }
            $model = new PaymentPlatformModel;
            $config = json_decode($param['config'], true);
            $param['config'] = json_encode($config);
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                unset($param['config']);
                insert_operation_log('充提渠道:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = PaymentPlatformModel::where('id', $id)
            ->find();
        if (empty($info)) {
            $model = new PaymentPlatformModel;
            $info = array_fill_keys($model->getTableFields(), '');
            $info['status'] = 1;
        }

        View::assign("info", $info);
        return View::fetch();
    }

    // 删除地区
    public function del()
    {
        $id = input('param.id');
        $info = PaymentPlatformModel::find($id);
        if ($info) {
            insert_operation_log('通道:删除', $info->toArray());
            $info->delete();
        }
        return json(msg(1, url('index')->build(), '删除成功'));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id): array
    {
        return [
            '编辑' => [
                'auth' => 'payment_platform/add',
                'href' => url('payment_platform/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
//            '删除' => [
//                'auth' => 'payment_platform/del',
//                'href' => "javascript:del(" . $id . ")",
//                'btnStyle' => 'danger',
//                'icon' => 'fa fa-trash-o'
//            ]
        ];
    }

    // 修改充值提现状态
    public function changeStatus()
    {
        $param = $this->request->param();

        $field = '';
        switch ($param['type']) {
            case 'recharge':
                $field = 'recharge_is_show';
                break;
            case 'withdraw':
                $field = 'withdraw_is_show';
                break;
            case 'is_first_charge':
                $field = 'is_first_charge';
                break;
            default:
                $this->error(lang('参数错误'));
        }
        $res = PaymentPlatformModel::update([$field => !$param['val']], ['id' => $param['id']]);
        if ($res) {
            insert_operation_log('充提渠道:' . ($param['id'] > 0 ? '修改' : '添加'), $param);
            $this->success(lang('修改成功'));
        }

        $this->error(lang('修改失败'));
    }

    /**
     * 线下支付-支付方式配置
     */
    public function offline_pay_account(): string
    {
        $config_model = new ConfigModel();
        $config = $config_model->getConfigInfo();
        $offline_pay_account = [];
        if (array_key_exists('offline_pay_account', $config)) {
            $offline_pay_account = json_decode($config['offline_pay_account'], true);
        }
        View::assign([
            'config' => $config,
            'offline_pay_account' => $offline_pay_account,
        ]);
        return View::fetch();
    }
}
