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

use app\admin\model\RoleModel;
use app\admin\model\UserModel;
use app\BaseController;
use org\Verify;
use think\facade\Route;
use think\facade\View;

class Login extends BaseController
{
    // 登录页面
    public function index()
    {
        View::assign('langs', getLangs(1));
        return View::fetch('/login');
    }

    // 登录操作
    public function doLogin()
    {
        $userName = input("param.user_name");
        $password = input("param.password");
        $code = input("param.code");
        $authy = input("param.authy");

        $result = $this->validate(compact('userName', 'password'), 'AdminValidate');
        if(true !== $result){
            return json(msg(-1, '', $result));
        }

//        $verify = new Verify();
//        if (!$verify->check($code)) {
//            return json(msg(-2, '', '验证码错误'));
//        }

        $userModel = new UserModel();
        $user = $userModel->checkUser($userName);

        if(empty($user)){
            return json(msg(-3, '', '管理员不存在'));
        }
        $user = $user->toArray();
        if ($user['authkey'] != '') {
            if (!$authy) {
                return json(msg(-1, '', '请填写谷歌验证码'));
            }
            $ga = new \PHPGangsta_GoogleAuthenticator();
            $checkResult = $ga->verifyCode($user['authkey'], $authy, 0);
            if (!$checkResult) {
                return json(msg(-1, '', '谷歌验证码错误'));
            }
        }

        if(md5($password . config('app.salt')) != $user['password']){
            return json(msg(-4, '', '密码错误'));
        }

        if(1 != $user['status']){
            return json(msg(-5, '', '该账号被禁用'));
        }

        session('username', $user['user_name']);
        session('id', $user['id']);
        session('head', $user['head']);
        session('role', $user['role_name']);  // 角色名
        session('role_id', $user['role_id']);
        session('rule', $user['rule']);

        // 更新管理员状态
        $param = [
            'login_times' => $user['login_times'] + 1,
            'last_login_ip' => getip(),
            'last_login_time' => time()
        ];

        $res = $userModel->updateStatus($param, $user['id']);
        if(1 != $res['code']){
            return json(msg(-6, '', $res['msg']));
        }

        insert_operation_log('登录');
        return json(msg(1, url('index/index')->build(), '登录成功'));
    }

    // 验证码
    public function checkVerify()
    {
        $verify = new Verify();
        $verify->imageH = 32;
        $verify->imageW = 100;
        $verify->length = 4;
        $verify->useNoise = false;
        $verify->fontSize = 14;
        return $verify->entry();
    }

    // 退出操作
    public function loginOut()
    {
        session('username', null);
        session('id', null);
        session('head', null);
        session('role', null);  // 角色名
        session('role_id', null);
        session('rule', null);

        $this->redirect(url('index')->build());
    }


    public function check_lang() {
        $lang = $this->request->param('lang/s');
        $allow_lang = \app\common\model\LangModel::column('lang');
        if (in_array($lang, $allow_lang)) {
            cookie('think_var', $lang);
        }
        return $this->success('ok');
    }
}
