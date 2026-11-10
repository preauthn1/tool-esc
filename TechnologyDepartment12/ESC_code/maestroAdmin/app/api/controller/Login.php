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

namespace app\api\controller;

use app\admin\model\MemberModel;
use app\api\transform\UserTransform;
use app\common\model\ConfigModel;
use app\common\model\MoneyLogModel;
use app\common\model\PromotionModel;
use app\common\model\TurntableCountModel;
use app\common\model\UserModel;
use app\event\Promotion;
use email\BrevoEmail;
use email\MailgunEmail;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use org\Verify;
use sms\Smsbao;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Env;
use think\facade\Log;
use think\facade\Request;
use think\response\Json;
use util\Locker;

class Login extends \app\BaseController
{

    public $configInfo = [];

    public function initialize(): void
    {
        $config = new ConfigModel();
        $this->configInfo = $config->getConfig();
    }

    // 登录操作
    public function doLogin()
    {
        $login_type = input('login_type');
        $account = '';
        $phone_code = '';
        if ($login_type == 'email') {
            $email = input('email', '', 'trim');
            // 判断长度
            if (strlen($email) > 254) {
                return json(msg(-1, [], lang('邮箱地址过长，请更换邮箱地址')));
            }
            $account = $email;
        } elseif ($login_type == 'mobile') {
            $mobile = input('mobile', '', 'trim');
            $mobile = str_replace(' ', '', $mobile);
            // 判断长度
            if (strlen($mobile) > 20 || strlen($mobile) < 7) {
                return json(msg(-1, [], lang('手机号过长，请输入正确的手机号')));
            }
            $phone_code = input('phone_code');
            // 判断区号是否存在
            $phone_codes = ConfigModel::$phone_codes;
            if (!in_array($phone_code, $phone_codes)) {
                return json(msg(-1, [], lang('请选择正确的区号')));
            }
            $account = $mobile;
        } else {
            return json(msg(-1, [], lang('请选择正确的登录方式')));
        }

        if (empty($account)) {
            return json(msg(-1, "", lang('请输入账号')));
        }
        //防止频繁登录
        (new Locker('withdrawal_lock_' . md5($account)))->lock();
        $password = $this->request->param("password/s", '', 'trim');  // 密码
        $userModel = new UserModel();

        $redis = Cache::store("redis");
        $key = "login_password_err_" . md5($account);
        $err_count = $redis->get($key, 0);
        if ($this->configInfo['error_to_ban'] > 0 && $err_count >= $this->configInfo['error_to_ban']) {
            //  次冻结账号
            UserModel::where('user_name', $account)->update(['status' => UserModel::FREEZE]);
        }
        $ret = $userModel->dologin($account, $password, $phone_code); // 检查用户信息
        if ($ret['code'] == -1) {
            // 用户名或密码错误，记录次数
            $redis->set($key, $err_count + 1, 300);
        } else {
            $redis->set($key, 0, 300);
        }
        if ($ret['code'] == 200) {
            $user_id = $ret['data']['id'];
//            AdPlanModel::updateUserPlanLevel($user_id);

            $tokens = $this->generateTokens($user_id);

            return json(msg(0, [
                'token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_time' => $tokens['expires_time'],
                'user_info' => (new UserTransform())->transform($ret['data']),
            ], lang('登录成功')));
        } else {
            return json(msg($ret['code'], '', $ret['msg']));
        }
    }

    // 注册
    public function doReg()
    {
        $pid = input('pid/d', 0);
        $platform = $pix = '';
        $test_event_code = '';
        if ($pid) {
            $promotion = PromotionModel::where('id', $pid)->find();
            if ($promotion) {
                $platform = $promotion['platform'];
                $pix = $promotion['pix'];
                $test_event_code = $promotion['test_event_code'];
            }
        }
        $click_id = input('click_id/s', "", "trim");//点击事件id
        $register_type = input('register_type');
        $account = $mobile = $email = '';
        $phone_code = "";
        if ($register_type == 'email') {
            $email = input('email');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return json(msg(-1, [], lang('请入输入正确的邮箱地址')));
            }
            // 过滤标签
            $email = strip_tags($email);
            // 过滤斜杠
            $email = addslashes($email);
            // 过滤html标签
            $email = htmlspecialchars($email);

            // 判断长度
            if (strlen($email) > 254) {
                return json(msg(-1, [], lang('邮箱地址过长，请更换邮箱地址')));
            }
            $account = $email;
        } elseif ($register_type == 'mobile') {
            $mobile = input('mobile/s', '', "trim");
            $mobile = str_replace(' ', '', $mobile);
            if (strpos($mobile, '+86') !== false) {
                $mobile = str_replace('+86', '', $mobile);
                if (!is_numeric($mobile) || strlen($mobile) !== 11) {
                    return json(msg(-1, "", lang('请输入正确的手机号')));
                }
            } else {
                // 格式判断，判断格式 最少10位，最多11位，第一位不能为0
                if (!is_numeric($mobile) || strlen($mobile) < 10 || strlen($mobile) > 11 || $mobile[0] == '0') {
                    return json(msg(-1, "", lang('请输入正确的手机号')));
                }
            }
            $phone_code = input('phone_code', '', 'trim');
            $account = $mobile;
        } else {
            return json(msg(-1, [], lang('请选择正确的注册方式')));
        }

        if (empty($account)) {
            return json(msg(-1, "", lang('请输入账号')));
        }
        if ($this->configInfo['account_rule'] == 2) {
            // TODO 验证手机号格式
//            if (!i18n_valid_mobile($account)) {
//                return json(msg(-1, "", lang('手机号格式有误')));
//            }
        } elseif ($this->configInfo['account_rule'] == 1) {
            if (!validEmail($account)) {
                return json(msg(-1, "", lang('邮箱格式有误')));
            }
        } elseif ($this->configInfo['account_rule'] == 3) {
            if (!validEmail($account)
                // TODO 验证手机号格式
//                && !i18n_valid_mobile($account)
            ) {
                return json(msg(-1, "", lang('账号格式有误')));
            }
        }
        $password = $this->request->param("password/s");         // 密码
        $paypwd = $this->request->param("paypwd/s");         // 安全密码
        if (empty($password)) {
            return json(msg(-1, "", lang('密码不能为空')));
        }
        if (empty($paypwd)) {
            return json(msg(-1, "", lang('安全密码不能为空')));
        }
        if ($this->configInfo['is_repeat_password']) {
            $repassword = $this->request->param("repassword/s");       // 密码
            if ($password !== $repassword) {
                return json(msg(-1, '', lang('两次登录密码不一致')));
            }
//            $repaypwd = $this->request->param("repaypwd/s");       // 密码
//            if ($paypwd != $repaypwd) {
//                return json(msg(-1, '', lang('两次安全密码不一致')));
//            }
        }

        $password_len = strlen($password);
        if ($password_len > 20 || $password_len < 6) {
            return json(msg(-1, '', lang('密码长度应在6-20位之间')));
        }
        $paypwd_len = strlen($paypwd);
        if ($paypwd_len > 20 || $paypwd_len < 6) {
            return json(msg(-1, '', lang('安全密码长度应在6-20位之间')));
        }

        $code = $this->request->param("verificationCode/s"); // 手机验证码
        $invite = $this->request->param("invite_code/s", "", "trim");      // 邀请码
        if (empty($invite) && (!isset($this->configInfo['invite_code_required']) || $this->configInfo['invite_code_required'] == 1)) {
            return json(msg(-1, "", lang('请填写邀请码')));
        }
        if ($invite !== '') {
            $invite_user = Db::name('home_user')->where('invite_code', $invite)->where('status', 1)->find();
            if (empty($invite_user)) {
                return json(msg(-1, "", lang('邀请码不存在')));
            }
            // 判断邀请码是否有效
            if ($invite_user['invite_code_status'] == 0) {
                return json(msg(-1, "", lang('邀请码已失效')));
            }
        }

        $userModel = new UserModel();
        $ret = $userModel->reg($mobile, $password, $code, $phone_code, $paypwd, $account, $invite, $pix, $platform, $click_id); // 检查用户信息
        if ($ret['code'] == 200) {
//            $user_id = $ret['data']['id'];
//            AdPlanModel::updateUserPlanLevel($user_id);
            $user = MemberModel::where(['user_name|mobile' => $account])->find();
            //注册送一次抽奖次数
            TurntableCountModel::addTurntableCount($user->id, 'reg');
            $register_give_money = $this->configInfo['register_give_money'];
            if ($register_give_money > 0) {
                // 注册成功 赠送金额
                $money_log = new MoneyLogModel();
                // 获取用户id
                $money_log->changeMoney($user->id, $register_give_money, MoneyLogModel::TYPE_USER, 'add',
                    '注册赠送金额',
                    '注册赠送金额' . $register_give_money);
            }
            // 判断是否需要充值激活
            if ($this->configInfo['recharge_num_valid_user'] == 0) {
                // 不需要充值就激活
                // 获取用户id
                $user->is_activate = 1;
                $user->save();
            }
//            $mode = ConfigModel::getConfigValue('level_upgrade_type');
            //用户注册送VIP0
//            $userModel->vipUpgradeStatus($user->id, 0, $mode);
            $remember_pwd = Env::get('REMEMBER_PWD', false);
            if ($remember_pwd) {
                cookie("cookie_username", $account);
                cookie("cookie_pwd", $password);
            }
            session('username', $user['user_name'], 'index');
            session('userid', $user['id'], 'index');
            session('is_demo', 0, 'index');
            cookie("index_login_key", $user['login_key']);
            $redis = Cache::store('redis');
            $redis_key = 'hide_notice:' . $user['id'];
            if ($this->configInfo['system_notice_frequency'] == 'once') {
                // 判断历史是否弹过
                $hide_notice = $redis->get($redis_key);
                if (!$hide_notice) {
                    cookie("hide_notice", 0);
                    $redis->set($redis_key, 1);
                } else {
                    cookie("hide_notice", 1);
                }
            } else {
                cookie("hide_notice", 0);
                $redis->set($redis_key, 0);
            }
            Promotion::handle($user, 'register', [], $test_event_code);
            $redis = Cache::store('redis');
            $redis_key = 'hide_notice:' . $ret['data']['id'];
            if ($this->configInfo['system_notice_frequency'] == 'once') {
                // 判断历史是否弹过
                $hide_notice = $redis->get($redis_key);
                if (!$hide_notice) {
                    cookie("hide_notice", 0);
                    $redis->set($redis_key, 1);
                } else {
                    cookie("hide_notice", 1);
                }
            } else {
                cookie("hide_notice", 0);
                $redis->set($redis_key, 0);
            }

            $tokens = $this->generateTokens($ret['data']['id']);

            return json(msg(0, [
                'token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_time' => $tokens['expires_time'],
                'user_info' => (new UserTransform())->transform($ret['data']),
            ], lang('注册成功')));
        } else {
            return json(msg(-1, '', $ret['msg']));
        }
    }

    // 试玩
    public function demo_reg()
    {
        $userModel = new UserModel();
        $mobile = random(11, 'number'); // 手机
        $code = 1; // 手机验证码
        $nick_name = 'CS' . random(5, 'number'); // 昵称
        $invite = ''; // 邀请码
        $not_verify = 1;
        $pix = '';
        $userModel = new UserModel();
        $platform = "";
        $ret = $userModel->reg($mobile, $mobile, $code, '+1', "123456", $nick_name, $invite, $pix, $platform, '', 1); // 检查用户信息
        if ($ret['code'] == 200) {
            $res = $userModel->dologin($mobile, $mobile); // 检查用户信息
            if ($res['code'] == 200) {
                session('username', $res['data']['mobile'], 'index');
                session('userid', $res['data']['id'], 'index');
                session('checkTimes', 86400, 'index');
                session('is_demo', 1, 'index');
                return json(msg(0, $res['data'], lang('登录成功')));
            } else {
                return json(msg($res['code'], '', $res['msg']));
            }
        } else {
            return json(msg(-1, '', $ret['msg']));
        }
    }

    /**
     * 发送短信
     * @param $mobile
     * @return Json
     */
    public function sendSms($mobile)
    {

//        $xinji_sms = new Xinji();
        $smsbao_sms = new Smsbao();
        $code = random(6, 'number');
        $mobile = str_replace(' ', '', $mobile);
        if (strpos($mobile, '+86') !== false) {
            $mobile = str_replace('+86', '', $mobile);
            if (!is_numeric($mobile) || strlen($mobile) !== 11) {
                return json(msg(-1, "", lang('请输入正确的手机号')));
            }
        } else {
            $mobile = str_replace('+', '', $mobile);
            if (!is_numeric($mobile) || strlen($mobile) < 11 || strlen($mobile) > 13 || $mobile[2] == '0') {
                return json(msg(-1, "", lang('请输入正确的手机号')));
            }
        }
        // 5512345567891


        $redis = Cache::store("redis");
        if ($redis->has($mobile)) {
            return json(msg(-1, '', lang('请勿频繁发送')));
        }
        $ret = $smsbao_sms->sendSms($mobile, $code);
        if ($ret) {
            $redis_key = $mobile;
            $verify_code_ttl = ConfigModel::getConfigValue('verify_code_ttl') ?: 180;
            $redis->set($redis_key, $code, intval($verify_code_ttl));
            return json(msg(0, '', lang('发送成功')));
        } else {
            return json(msg(-1, '', lang($smsbao_sms->error)));
        }

    }

    /**
     * 发送邮箱验证码
     * @param $email
     * @return Json
     */
    public function sendEmail($email = '')
    {
        $email = trim($email);
        if (!validEmail($email)) {
            return json(msg(-1, "", lang('邮箱格式有误')));
        }
        $redis_key = 'email_' . md5($email);
        $redis = Cache::store("redis");
        if ($redis->has($redis_key)) {
            return json(msg(-1, '', lang('请勿频繁发送')));
        }
        $smtp_email = new BrevoEmail();
        $code = random(6, 'number');
        $ret = $smtp_email->sendEmail($email, $code);
        if ($ret) {
            $redis = Cache::store("redis");
            $verify_code_ttl = ConfigModel::getConfigValue('verify_code_ttl') ?: 180;
            $redis->set($redis_key, $code, intval($verify_code_ttl));
            return json(msg(0, '', lang('发送成功')));
        } else {
            return json(msg(-1, '', lang('发送失败')));
        }
    }

    // 验证手机验证码
    public function checkVerify($mobile, $code)
    {
        $userModel = new UserModel();
        $ret = $userModel->checkVerify($mobile, $code); // 检查用户信息
        if ($ret['code'] == 200) {
            return json(msg(0, '', lang('验证成功')));
        } else {
            return json(msg(-1, '', $ret['msg']));
        }
    }

    // 验证码
    public function captcha()
    {
        $verify = new Verify();
        $verify->imageH = 32;
        $verify->imageW = 100;
        $verify->length = 4;
        $verify->useNoise = false;
        $verify->fontSize = 14;
        return $verify->entry('index');
    }

    // 退出操作
    public function loginOut()
    {
        $loginUrl = url('login/index');
        $userid = session('userid', '', 'index');
        $userModel = new UserModel();
        $userInfo = $userModel->getOneUser($userid);
        // 判断登录状态
        if (!$userInfo || ($userInfo['login_times'] + 604800) < time()) {
            $this->redirect($loginUrl);
        }
        $param = [
            'index_login_key' => random(32)
        ];
        $res = $userModel->updateStatus($param, $userInfo['id']);
        if ($res) {
            if ($userInfo['is_demo']) {
                clearUderInfo($userInfo['id']);
            }
            session('username', null, 'index');
            session('userid', null, 'index');
            session('checkTimes', null, 'index');
            session('is_demo', null, 'index');
            cookie('index_login_key', null);
            cookie("hide_notice", 0);
            $this->redirect($loginUrl);
        }
    }

    public function mobileconfig($inviteCode = '')
    {
        $filename = md5("app_v2_inv{$inviteCode}:" . $this->request->domain());
        $file = "mobileconfig/{$filename}.mobileconfig";
        if (file_exists(toMedia($file, 2))) {
            return redirect(toMedia($file));
        }
        if ($this->configInfo['logo']) {
            $data = file_get_contents(toMedia($this->configInfo['logo'], 2));
        } else {
            $data = file_get_contents(root_path() . "/public/static/common/mobileconfig/icon.png");
        }
        $icon = wordwrap(base64_encode($data), 77, "\n", true);
        $web_url = getWebUrl();
        $url = $web_url . ($inviteCode ? "?inviteCode=$inviteCode" : "");
        $template_mobileconfig = file_get_contents(root_path() . "/public/static/common/mobileconfig/template.mobileconfig");
        if (empty($template_mobileconfig)) {
            return 406;
        }
        $mobileconfig = str_replace([
            "{{URL}}", "{{ICON}}", "{{CHANNEL_NAME}}", "{{UUID}}"
        ], [
            $url, $icon, $this->configInfo['title'], uuid()
        ], $template_mobileconfig);
        if (!file_exists(toMedia($file, 2))) {
            file_put_contents(toMedia($file, 2), $mobileconfig);
        }
        $download = new \app\common\response\Download($mobileconfig, 206);
        $download->contentType("application/x-apple-aspen-config");
        $download->noCache();
        $download->isContent();
        return $download->name("app{$inviteCode}.mobileconfig");
    }

    public function check_lang()
    {
        $lang = $this->request->param('lang/s');
        if (isset($this->configInfo['checkout_language']) && $this->configInfo['checkout_language'] == 1) {
            $allow_lang = \app\common\model\LangModel::column('lang');
            if (in_array($lang, $allow_lang)) {
                cookie('think_var', $lang);
            }
        }
        return json(msg(0, '', lang('ok')));
    }

    public function payed()
    {
        $message = $this->request->param('message/s', '');
        $type = $this->request->param('type/s');
        $url = url('index/index');
        if ($type === 'success') {
            $this->success($message, $url);
        } else {
            $this->error($message, $url);
        }
    }

    /**
     * 刷新token
     */
    public function refresh_token(): Json
    {
        $refresh_token = Request::instance()->header('authorization');
        if (empty($refresh_token)) {
            return json(msg(-1, '', lang('参数错误')));
        }

        try {
            $jwtKey = config('app.jwt_key');
            $decoded = JWT::decode($refresh_token, new Key($jwtKey, 'HS256'));

            // 验证是否是刷新token
            if (!isset($decoded->type) || $decoded->type !== 'refresh') {
                return json(msg(-1, '', lang('无效的刷新令牌')));
            }

            // 验证token是否过期
            if (!isset($decoded->create_time) || !isset($decoded->expires_in) ||
                ($decoded->create_time + $decoded->expires_in) < time()) {
                return json(msg(-1, '', lang('刷新令牌已过期')));
            }

            // 验证用户是否存在
            $user = UserModel::where('id', $decoded->user_id)->find();
            if (!$user) {
                return json(msg(-1, '', lang('用户不存在')));
            }
            // 验证login_key是否匹配
            if (!isset($decoded->login_key) || $decoded->login_key !== $user->login_key) {
                return json(msg(-1, '', lang('无效的刷新令牌')));
            }

            $tokens = $this->generateTokens($user->id);

            return json(msg(0, [
                'token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_time' => $tokens['expires_time'],
                'user_info' => (new UserTransform())->transform($user),
            ], lang('刷新成功')));

        } catch (\Exception $e) {
            return json(msg(-1, '', lang('无效的令牌')));
        }
    }


    /**
     * 生成token
     * @param $user_id
     * @return array
     * @throws DbException
     */
    private function generateTokens($user_id): array
    {
        // 生成新的login_key
        $login_key = random(32);

        // 更新用户的login_key
        $user = UserModel::find($user_id);
        if ($user) {
            $user->login_key = $login_key;
            $user->save();
        }

        $expires_in = config('app.access_expires');
        $jwtKey = config('app.jwt_key');
        $access_token = JWT::encode([
            'user_id' => $user_id,
            'create_time' => time(),
            'expires_in' => $expires_in,
            'login_key' => $login_key
        ], $jwtKey, 'HS256');

        $refresh_token = JWT::encode([
            'user_id' => $user_id,
            'create_time' => time(),
            'expires_in' => $expires_in,
            'type' => 'refresh',
            'login_key' => $login_key
        ], $jwtKey, 'HS256');

        return [
            'token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_time' => $expires_in
        ];
    }

}
