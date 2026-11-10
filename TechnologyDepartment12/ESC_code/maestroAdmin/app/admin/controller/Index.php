<?php

namespace app\admin\controller;

use app\admin\model\ConfigModel;
use app\admin\model\NodeModel;
use app\admin\model\UserModel;
use app\admin\model\WithdrawModel;
use think\facade\Cache;
use think\facade\View;

class Index extends Base {

    public function index() {
        // 获取权限菜单
        $node = new NodeModel();

        View::assign([
            'menu' => $node->getMenu(session('rule'))
        ]);
        $app_name = ConfigModel::where('field', 'name')->value('value');
        $app_icon = ConfigModel::where('field', 'admin_icon')->value('value');
        View::assign('app_name', $app_name);
        View::assign('app_icon', $app_icon);
        View::assign('langs', getLangs(1));
        $recharge_voice_file = getConfig('recharge_voice_file');
        $withdraw_voice_file = getConfig('withdraw_voice_file');
        View::assign('recharge_voice_file', $recharge_voice_file);
        View::assign('withdraw_voice_file', $withdraw_voice_file);
        return View::fetch('/index');
    }

    /**
     * 后台默认首页
     * @return mixed
     */
    public function indexPage() {
        $clear = $this->request->param('clear/d', 0);
        $redis = Cache::store('redis');
        if ($clear == 1) {
            $redis->clear('admin_index_stat');
        }
        if ($clear == 1) {
            return redirect(url('indexPage')->build());
        }

        $stats = [
            ['title' => '用户数（不含虚拟用户）', 'label' => '总计', 'type' => 'danger', 'value' => '', 'key' => "getTotalUser"],
            ['title' => '盘内总余', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'getTotalRemainMoney'],
            ['title' => '注册人数', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalUser1"],
            ['title' => '活跃人数', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalUser2"],
//            ['title' => '充值人数', 'label' => '总计', 'type' => 'danger', 'value' => '', 'key' => "getTotalRechargeUser"],
            ['title' => '充值人数', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalRechargeUser1"],
            ['title' => '充值总额', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'recharge_today'],
            ['title' => '首充人数', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalRechargeUser2"],
            ['title' => '复充人数', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalRechargeUser3"],
//            ['title' => '线上充值', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalRecharge3_today"],
//            ['title' => '线下转帐', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalRecharge1_today"],
//            ['title' => 'USDT充值', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => "getTotalRecharge4_today"],
//            ['title' => '充值总额', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'recharge_today'],
//            ['title' => '充值总额', 'label' => '本月', 'type' => 'warning', 'value' => '', 'key' => 'recharge_month'],
            ['title' => '充值总额', 'label' => '总计', 'type' => 'danger', 'value' => '', 'key' => 'recharge_total'],
//            ['title' => '提现总额', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'withdraw_today'],
//            ['title' => '提现总额', 'label' => '本月', 'type' => 'warning', 'value' => '', 'key' => 'withdraw_month'],
            ['title' => '提现总额', 'label' => '总计', 'type' => 'danger', 'value' => '', 'key' => 'withdraw_total'],
//            ['title' => '提现手续费', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'withdraw_taxfee_today'],
//            ['title' => '提现手续费', 'label' => '本月', 'type' => 'warning', 'value' => '', 'key' => 'withdraw_taxfee_month'],
//            ['title' => '提现手续费', 'label' => '总计', 'type' => 'danger', 'value' => '', 'key' => 'withdraw_taxfee_total'],
//            ['title' => '总盈亏', 'label' => '今天', 'type' => 'info', 'tips' => '计算方式：充值-提现=盈亏', 'value' => '', 'key' => 'total_profit_and_loss_today'],
//            ['title' => '总盈亏', 'label' => '本月', 'type' => 'warning', 'tips' => '计算方式：充值-提现=盈亏', 'value' => '', 'key' => 'total_profit_and_loss_month'],
//            ['title' => '总盈亏', 'label' => '总计', 'type' => 'danger', 'tips' => '计算方式：充值-提现=盈亏', 'value' => '', 'key' => 'total_profit_and_loss_total'],
//            ['title' => '总流水', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'getTotalMoneyOrder_today_retail_price'],
//            ['title' => '总流水', 'label' => '本月', 'type' => 'warning', 'value' => '', 'key' => 'getTotalMoneyOrder_month_retail_price'],
//            ['title' => '总佣金', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'getTotalMoneyOrder_today_return_bonus'],
//            ['title' => '总佣金', 'label' => '本月', 'type' => 'warning', 'value' => '', 'key' => 'getTotalMoneyOrder_month_return_bonus'],
            ['title' => '赠送彩金', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'getTotalGray_today_all_0'],
//            ['title' => '虚拟充值', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'day_is_xuni_recharge'],
//            ['title' => '虚拟充值', 'label' => '本月', 'type' => 'warning', 'value' => '', 'key' => 'month_is_xuni_recharge'],
//            ['title' => '虚拟充值', 'label' => '总计', 'type' => 'danger', 'value' => '', 'key' => 'total_is_xuni_recharge'],
//            ['title' => '虚拟提现', 'label' => '今天', 'type' => 'info', 'value' => '', 'key' => 'day_is_xuni_withdrawals'],
//            ['title' => '虚拟提现', 'label' => '本月', 'type' => 'warning', 'value' => '', 'key' => 'month_is_xuni_withdrawals'],
//            ['title' => '虚拟提现', 'label' => '总计', 'type' => 'danger', 'value' => '', 'key' => 'total_is_xuni_withdrawals'],
        ];
        foreach ($stats as $key => $value) {
            $redis_key = "admin_index_stat:{$value['key']}";
            if ($redis->has($redis_key)) {
                $stats[$key]['value'] = $redis->get($redis_key);
            }
        }
        $userModel = new UserModel();
        $user = $userModel->where("id", session('id'))->find();
        View::assign('isAuthy', $user->authkey != '');
        View::assign('stats', $stats);
        return View::fetch('index');
    }


    public function query() {
        $type = $this->request->param('type');
        $redis = Cache::store('redis');
        $redis_key = "admin_index_stat:{$type}";
        if ($redis->has($redis_key)) {
            $result = $redis->get($redis_key);
            $this->success('ok', '', $result);
        }
        $result = 0;
        switch ($type) {
            case 'recharge_today': $result = getTotalRecharge(false, 'today'); break;
            case 'recharge_month': $result = getTotalRecharge(false, 'month'); break;
            case 'recharge_total': $result = getTotalRecharge(false, ''); break;
            case 'withdraw_today': $result = getTotalWithdraw('today', 'original_money'); break;
            case 'withdraw_month': $result = getTotalWithdraw('month', 'original_money'); break;
            case 'withdraw_total': $result = getTotalWithdraw('', 'original_money'); break;
            case 'withdraw_taxfee_today': $result = getTotalWithdraw('today', 'taxfee'); break;
            case 'withdraw_taxfee_month': $result = getTotalWithdraw('month', 'taxfee'); break;
            case 'withdraw_taxfee_total': $result = getTotalWithdraw('', 'taxfee'); break;
            case 'total_profit_and_loss_today':
                    $recharge_today = getTotalRecharge(false, 'today');
                    $withdraw_today = getTotalWithdraw('today', 'original_money');
                    $result = i18n_sprintf($recharge_today - $withdraw_today);
                break;
            case 'total_profit_and_loss_month':
                    $recharge_month = getTotalRecharge(false, 'month');
                    $withdraw_month = getTotalWithdraw('month', 'original_money');
                    $result = i18n_sprintf($recharge_month - $withdraw_month);
                break;
            case 'total_profit_and_loss_total':
                    $recharge_total = getTotalRecharge(false, '');
                    $withdraw_total = getTotalWithdraw('', 'original_money');
                    $result = i18n_sprintf($recharge_total - $withdraw_total);
                break;
            case 'getTotalUser': $result = getTotalUser(); break;
            case 'getTotalUser1': $result = getTotalUser(1); break;
            case 'getTotalUser2': $result = getTotalUser(2); break;
            case 'getTotalRemainMoney': $result = i18n_sprintf(getTotalRemainMoney()); break;
            case 'getTotalRechargeUser': $result = getTotalRechargeUser(); break;
            case 'getTotalRechargeUser1': $result = getTotalRechargeUser(1); break;
            case 'getTotalRechargeUser2': $result = getTotalRechargeUser(2); break;
            case 'getTotalRechargeUser3': $result = getTotalRechargeUser(3); break;
            case 'getTotalRecharge3_today': $result = getTotalRecharge(3, 'today'); break;
            case 'getTotalRecharge1_today': $result = getTotalRecharge(1, 'today'); break;
            case 'getTotalRecharge4_today': $result = getTotalRecharge(4, 'today'); break;
            case 'getTotalMoneyOrder_today_retail_price': $result = getTotalMoneyOrder('today', 'retail_price'); break;
            case 'getTotalMoneyOrder_month_retail_price': $result = getTotalMoneyOrder('month', 'retail_price'); break;
            case 'getTotalMoneyOrder_today_return_bonus': $result = getTotalMoneyOrder('today', 'return_bonus'); break;
            case 'getTotalMoneyOrder_month_return_bonus': $result = getTotalMoneyOrder('month', 'return_bonus'); break;
            case 'getTotalGray_today_all_0': $result = getTotalGray('today', 0); break;
            case 'day_is_xuni_recharge': $result = getTotalRecharge(false,'today',1); break;
            case 'month_is_xuni_recharge': $result = getTotalRecharge(false,'month',1); break;
            case 'total_is_xuni_recharge': $result = getTotalRecharge(false,'',1); break;
            case 'day_is_xuni_withdrawals': $result = getTotalWithdraw('today','original_money',2,1); break;
            case 'month_is_xuni_withdrawals': $result = getTotalWithdraw('month','original_money',2,1); break;
            case 'total_is_xuni_withdrawals': $result = getTotalWithdraw('','original_money',2,1); break;
            default: break;
        }
        if (!in_array($type, ['getTotalUser', 'getTotalUser1', 'getTotalUser2', 'getTotalRechargeUser', 'getTotalRechargeUser1', 'getTotalRechargeUser2', 'getTotalRechargeUser3'])) {
            $str = get_number_unit($result);
            if ($str != $result) {
                $result .= " <span class='label label-primary'>" . $str . "</span>";
            }
        }
        $redis->tag('admin_index_stat')->set("admin_index_stat:{$type}", $result, 600);
        $this->success('ok', '', $result);
    }

    public function authy() {
        $userModel = new UserModel();
        $user = $userModel->where("id", session('id'))->find();
        View::assign("userid", $user['id']);
        if ($this->request->isAjax() && $this->request->isPost()) {
            if ($user['authkey']) {
                $this->error("您已经绑定了谷歌密钥，不能重复绑定！");
            }
            $secret = $this->request->param("secret/s");
            $code = $this->request->param("code/s");

            $ga = new \PHPGangsta_GoogleAuthenticator();
//            $oneCode = $ga->getCode($secret);
            $checkResult = $ga->verifyCode($secret, $code);
            if ($checkResult) {
                $user->authkey = $secret;
                $user->save();
                insert_operation_log('管理员:绑定谷歌验证码', "账户:{$user->user_name}");
                $this->success("绑定成功");
            }else{
                $this->error("谷歌验证码错误");
            }
        }
        if (!$user['authkey']) {
            $config = new \app\common\model\ConfigModel();
            $configInfo = $config->getConfig();
            $ga = new \PHPGangsta_GoogleAuthenticator();
            $secret = $ga->createSecret();
            $data = $ga->getQRCodeGoogleUrl(session('username'), $secret, $configInfo['title']);
        }else{
            die("您已经绑定了谷歌密钥，不能重复绑定！");
        }

        View::assign("title", "绑定谷歌验证码");
        View::assign("secret", $secret);
        View::assign("google_auth_key", $user['authkey']);
        View::assign("data", $data);
        return View::fetch();
    }

    /**
     * Ajax获取最新客服消息
     * @return type
     */
    public function get_message() {
        $map = ["read" => 0, "master_id" => 0];

        $data = db("message")->field("DISTINCT user_id, MAX(id) AS MessageID")->where($map)
            ->order(["MessageID" => "DESC"])->group("user_id")->select();
        $desc = "";
        foreach ($data as &$value) {
            $value['content'] = db("message")->where("id", $value['MessageID'])->value("content");
            if (stripos($value['content'], "img[") !== false) {
                $value['content'] = "图片信息";
            }else{
                $value['content'] = removeXSS($value['content']);
            }
            if ($desc == "") {
                $desc = $value['content'];
            }
            $value['Title'] = \think\helper\Str::substr($value['content'], 0, 10) . "...";
            $value['ChatUrl'] = url("message/chat", ['userid' => $value['user_id']])->build();
        }
        unset($value);
        $json = [
            'count' => count($data),
            "list" => $data,
            "token" => md5("get_message:" . json_encode($data)),
            "url" => url("message/index", "", false, true)->build(),
            "title" => "有新的客服消息！",
            "desc" => $desc,
        ];
        return json($json);
    }

    /**
     * Ajax获取最新客服消息
     * @return type
     */
    public function get_graborder() {
        $data = \app\common\model\GrabOrderModel::where('status', 0)->column('id');
        $token = md5("get_graborder:" . json_encode($data));
        $json = [
            'count' => count($data),
            "list" => $data,
            "token" => $token,
            "tips" => "有新拼单申请待处理",
            "url" => url("order/grab", "", false, true)->build(),
        ];
        return json($json);
    }
    /**
     * Ajax获取最新客服消息
     * @return type
     */
    public function get_withdraw() {
        $type = $this->request->param('type/d', 1);
        $data = WithdrawModel::where('status', 1)->where('type', $type)->column('id');
        $token = md5("get_withdraw:" . json_encode($data));
        $json = [
            'count' => count($data),
            "list" => $data,
            "token" => $token,
            "tips" => "有新提现申请待处理",
            "url" => url($type == 1 ? "withdraw/index" : "withdraw/usdt", "", false, true)->build(),
        ];
        return json($json);
    }

    public function reset_opcache()
    {
        opcache_reset();
        echo '手动重置 OPcache';
    }

}
