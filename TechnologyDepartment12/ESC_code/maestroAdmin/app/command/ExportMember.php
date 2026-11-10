<?php

namespace app\command;

use app\admin\model\LevelModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\admin\model\MemberModel;

class ExportMember extends Command {

    public $status = [];
    public $recharge = [];
    public $withdrawals = [];
    public $levels = [];
    public $header = [];
    public $save_path = '';
    public $index = 1;
    public $limit = 5000;

    protected function configure() {
        $this->setName('export:member')->setDescription('导出用户数据');
    }

    protected function execute(Input $input, Output $output) {
        $this->status = config('app.user_status');
        $this->recharge = Db::name('recharge')->where("status", 2)->group("user_id")->column("sum(money)", "user_id");
        $this->withdrawals = Db::name('withdrawals')->where("status", 2)->group("user_id")->column("sum(money)", "user_id");
        $this->levels = LevelModel::column("level_name", "level_id");
        $this->header = [
            "id" => "ID",
            "invite_code" => "邀请码",
            "mobile" => "手机号",
            "level_name" => "会员级别",
            "user_money" => "余额",
            "recharge" => "充值总额",
            "withdraw" => "提现总额",
            "true_name" => "真实姓名",
            "bank_number" => "银行卡号",
            "leader1" => "上级ID",
            "is_xuni" => "虚拟账户",
            "create_time" => "注册时间",
            "ip" => "注册IP",
            "last_login_time" => "最后登录时间",
            "last_login_ip" => "最后登录IP",
            "status" => "状态",
        ];
        $this->save_path = root_path() . 'export/';
        if (!file_exists($this->save_path)) {
            @mkdir($this->save_path);
        }
        $user = new MemberModel();
        $user->withTrashed()->where('is_xuni', 0)->chunk($this->limit, function($list){
            $this->myUserIterator($list);
        });
        $output->writeln('done!');
    }

    public function myUserIterator($list) {
        // 拼装参数
        foreach ($list as $key => $vo) {
            $list[$key]['is_xuni'] = $vo['is_xuni'] == 1 ? '是' : '否';
            $list[$key]['last_login_time'] = date('Y-m-d H:i:s', $vo['last_login_time']);
            $list[$key]['status'] = $this->status[$vo['status']];
            if ($vo['delete_time']) {
                $list[$key]['status'] = '已删除';
            }
            $list[$key]['level_name'] = isset($this->levels[$vo['level_id']]) ? $this->levels[$vo['level_id']] : '-';
            $list[$key]['recharge'] = isset($this->recharge[$vo['id']]) ? floatval($this->recharge[$vo['id']]) : 0;
            $list[$key]['withdraw'] = isset($this->withdrawals[$vo['id']]) ? floatval($this->withdrawals[$vo['id']]) : 0;
            $list[$key]['user_money'] = i18n_sprintf($vo['user_money']);
        }
        $file_name = "user_list_{$this->index}_" . date("YmdHis");
        genXlsx($this->header, $list, $file_name, '', 1, true, $this->save_path);
        echo "第{$this->index}个{$this->limit}条用户数据.文件路径:{$this->save_path}/{$file_name}.xlsx\n";
        $this->index++;
    }
}
