<?php

namespace app\api\controller;

use app\admin\model\WithdrawModel;
use app\common\model\ConfigModel;
use app\common\model\UserModel;
use think\facade\Db;
use think\db\Where;
use think\exception\DbException;
use think\response\Json;

class MoneyLog extends Base
{
    /**
     * 钱包记录
     * @return Json
     * @throws DbException
     */
    public function index(): Json
    {
        $userid = $this->userid; // 用户ID
        $start_time = strtotime('-30 days');
        $end_time = strtotime('+1 days');
        $get_start_time = input('start_time') ? strtotime(input('start_time')) : '';
        $get_end_time = input('end_time') ? strtotime(input('end_time')) + 24 * 3600 : '';
        if ($get_start_time) {
            $start_time = $get_start_time;
        }
        if ($get_end_time) {
            $end_time = $get_end_time;
        }

        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $type = $this->request->param('type/d', 1);
        $detail_type = $this->request->param('detail_type/d');
        $where[] = ['user_id', '=', $userid];
        $where[] = ['type', '=', $type];
        if ($detail_type) {
            $where[] = ['detail_type', '=', $detail_type];
        }
        $where[] = ['status', '=', 1];
        $where[] = ['create_time', 'between', [$start_time, $end_time]];
        $user =  UserModel::where('id', $this->userid)->field('is_xuni,show_virtual_data')->find();
        if ($user['is_xuni'] == 1 && $user['show_virtual_data'] == 1) {
            $field = "id,user_id,admin_id,title,change,money,user_money,type,status,create_time";
            $db_lists = Db::table('qd_home_user_virtual_log');
        } else {
            $field = "id,is_xuni,user_id,admin_id,order_id,title,change,from_user_id,money,user_money,type,detail_type,status,create_time";
            $db_lists = Db::table('qd_money_log');
        }
        $lists = $db_lists->field($field)
            ->where($where)
            ->order('create_time desc')
            ->paginate(['page' => $page, 'list_rows' => $limit]);
        $sql = $db_lists->getLastSql();
        foreach ($lists as $k => $val) {
            $val['money'] = i18n_number_format($val['money']);
            if ($val['change'] == 1) {
                $val['money'] = '+' . $val['money'];
            } else if ($val['change'] == 2) {
                $val['money'] = '-' . $val['money'];
            }
            $val['create_time'] = i18n_datetime2str($val['create_time']);
            if (!ConfigModel::getConfigValue('is_distinguish_admin_add_money') && $val['detail_type'] == 1) {
                $val['title'] = '用户充值';
            }
            $val['title'] = lang($val['title']);
            $val['title'] = str_replace('&#39;', "'", $val['title']);
            $val['withdrawals_status'] = 0;
            // 获取提现状态
            if ($val['detail_type'] == 2) { // 提现
               // 查询提现状态
                $withdraw_status = WithdrawModel::where('id', $val['order_id'])->value('status');
                if ($withdraw_status == 7) {
                    $withdraw_status = 2;
                }
                $val['withdrawals_status'] = $withdraw_status;
            }
            $lists[$k] = $val;
        }
        return json(msg(0, $lists,''));
    }

}
