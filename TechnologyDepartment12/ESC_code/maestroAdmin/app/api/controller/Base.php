<?php

namespace app\api\controller;

use app\admin\model\UserMessageModel;
use app\common\model\CustomerServiceModel;
use app\common\model\PromotionModel;
use app\common\model\UserModel;
use app\lib\exception\TokenException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\App;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Request;
use util\Agent;

class Base extends \app\BaseController
{

    public $userInfo = [];
    public $userLevel = [];
    public $configInfo = [];
    public $userid = 0;
    public $tyj_expire_tips = false;
    public $lang = '';

    public function initialize()
    {
        $pix = input('pix') ? input('pix') : '';
        $pf = input('pf') ? input('pf') : '';
        $click_id = input('click_id') ? input('click_id') : '';
        if (empty($pix)) {
            $pix = session('pix');
            $pf = session('pf');
            $click_id = session('click_id');
            if (empty($pix)) {
                $pix = PromotionModel::getInfoDefaultPix();
                $pf = PromotionModel::getInfoDefaultPf();
            }
        } else {
            session('pix', trim($pix));
            session('pf', trim($pf));
            session('click_id', trim($click_id));
        }

        $invite_code = input('inviteCode') ? input('inviteCode') : '';
        if (!$invite_code && session('?invite_code')) {
            $invite_code = session('invite_code');
        }
        if ($invite_code) {
            session('invite_code', trim($invite_code));
        }
        $config = new \app\common\model\ConfigModel();
        $this->configInfo = $config->getConfig();
        $sys_theme_color = empty($this->configInfo['sys_theme_color']) ? 0 : $this->configInfo['sys_theme_color'];
        if (!empty($this->request->domainInfo)) {
            $this->configInfo['title'] = $this->request->domainInfo['title'];
            $this->configInfo['logo'] = $this->request->domainInfo['logo'];
        }
        if (!$this->isLogin()) {
            throw new TokenException();
        }
//        $userModel = new UserModel();
//        $this->userInfo = $userModel->getOneUser($this->userid);
        $this->userLevel = getUserLevel($this->userInfo['level_id']);
        if (!empty($click_id)) {
            UserModel::where('id', $this->userid)->update(['click_id' => $click_id]);
        }
        if (empty($this->userLevel)) {
            checkLevel($this->userid);
        }

        if ($this->userInfo['tyj_money'] > 0 && $this->configInfo['tyj_expire'] > 0) { //清理体验金
            $tyj_expire = time() - ($this->configInfo['tyj_expire'] * 24 * 3600);
            if ($this->userInfo['create_time'] < $tyj_expire) { //体验金已过期
                Db::name('home_user')->where('id', $this->userInfo['id'])->update(['tyj_money' => 0]);
                Cache::store('redis')->set('tyj_expire_tips' . $this->userInfo['id'], 1);
            }
        }
        $this->tyj_expire_tips = cache('tyj_expire_tips' . $this->userInfo['id']);
        $web_url = getWebUrl();
        $download_url = $web_url . "/download/{$invite_code}";

        //客服列表
        $customer_services = CustomerServiceModel::field('image,link,name')->where('status', 1)->order('sort')->select();
        $langs = getLangs(1);
        $user_unread = UserMessageModel::where('user_id', $this->userid)->where('is_read', 0)->order('id desc')->count();
    }

    public function getGoodsShareUrl($goods_id)
    {
        $weburl = getWebUrl();
        $configInfo = $this->configInfo;
        $web_clip_url = "{$weburl}/mobileconfig";
        if (Agent::osType() == Agent::OS_TYPE_ANDROID) {
            $download_url = $configInfo['android_url'];
        } else {
            $download_url = $configInfo['ios_url'] != '' ? $configInfo['ios_url'] : $web_clip_url;
        }
        return $download_url;
//        $params = ['id' => $goods_id];
//        if ($this->userInfo) {
//            $params['inviteCode'] = $this->userInfo['invite_code'];
//        }elseif(session('?invite_code')){
//            $params['inviteCode'] = session('invite_code');
//        }
//        return url('goods/info', ['id' => $goods_id, 'inviteCode' => $this->userInfo['invite_code']], true, true);
    }

    // 判断登录状态
    public function isLogin()
    {
        $accessToken = Request::instance()->header('authorization');
        if (!$accessToken) {
            return false;
        }
        try {
            // TODO
            // FIXME 判断token是否被加入了黑名单
            $tokenInfo = (array)JWT::decode($accessToken, new Key(config('app.jwt_key'), 'HS256'));
            if ($tokenInfo['create_time'] + $tokenInfo['expires_in'] < time()) {
                return false;
            }
            $userModel = new UserModel();
            $this->userInfo = $userModel->where('id', $tokenInfo['user_id'])->find();
            if (!$this->userInfo) {
                return false;
            }

            // 验证login_key是否匹配
            if (!isset($tokenInfo['login_key']) || $tokenInfo['login_key'] !== $this->userInfo['login_key']) {
                return false;
            }

            $userModel->where('id', $tokenInfo['user_id'])->update(['last_login_time' => time()]);
            $this->userid = $tokenInfo['user_id'];
        } catch (\Exception $e) {
            return false;
        }
        return true;
//        print_r($tokenInfo);

//        $login_key = header("index_login_key");
//        $login_key = $accessToken;
//        dd($login_key);


//        if ($this->userInfo) {
//            if ($this->userInfo['avatar']) {
//                $this->userInfo['avatar'] = toAvatar($this->userInfo['avatar']);
//            }
//            $this->setSession($this->userInfo);
//            $param = [
//                'last_login_ip' => getip(),
//                'last_login_time' => time(),
//            ];
//            // 更新用户登录信息
//            $userModel->updateStatus($param, $this->userInfo['id']);
//            $userModel->insertLoginLog($this->userInfo['id']);
//        }
        // 判断用户信息
//        if (!session('?username', '', 'index') || !session('?userid', '', 'index')) {
//            $this->clearSession();
//            return false;
//        }
//        $this->userid = intval(session('userid', '', 'index'));
//        if (!$this->userInfo) {
//            $this->userInfo = $userModel->getOneUser($this->userid);
//        }
//        if (!$this->userInfo) {
//            $this->clearSession();
//            return false;
//        }
//        // 验证账号状态
//        if (2 == $this->userInfo['status']) {
//            $this->clearSession();
//            $param = [
//                'login_key' => random(32)
//            ];
//            // 更新用户登录信息
//            $userModel->updateStatus($param, $this->userInfo['id']);
//            return false;
//        }
//        if (strtolower($this->request->controller()) == 'goods') {
//            $user_money = $this->userInfo['user_money'] + $this->userInfo['tyj_money'];
//            if (isset($this->configInfo['yeb_qd']) && $this->configInfo['yeb_qd'] == 1) {
//                $yeb_total = Db::name('yuebao')->where('user_id', $this->userInfo['id'])->where('status', 1)->sum('principal');
//                $user_money += $yeb_total;
//            }
//            $this->userInfo['user_money'] = sprintf4f($user_money);
//        }
//        $this->userInfo['tyj_money'] = sprintf4f($this->userInfo['tyj_money']);
//        if ($login_key != $this->userInfo['login_key']) {
//            if ($this->userInfo['is_demo']) {
//                clearUderInfo($this->userInfo['id']);
//            }
//            $this->clearSession();
//            return false;
//        }
//        return true;
    }

    private function setSession($info)
    {
        session('username', $info['mobile'], 'index');
        session('userid', $info['id'], 'index');
        session('is_demo', $info['is_demo'], 'index');
    }

    private function clearSession()
    {
        session('username', null, 'index');
        session('userid', null, 'index');
        session('checkTimes', null, 'index');
        session('is_demo', null, 'index');
    }
}
