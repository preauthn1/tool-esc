<?php

namespace app\admin\controller;

use app\admin\model\ConfigModel;
use app\admin\model\NodeModel;
use app\admin\model\RoleModel;
use app\BaseController;
use think\facade\Db;
use think\facade\Cache;
use think\facade\View;

class Base extends BaseController {

    public function initialize() {
        setTimezone();
        if (empty(session('username')) || empty(session('id'))) {
            $loginUrl = url('login/index')->build();
            if (request()->isAjax()) {
                echo json_encode(msg(111, $loginUrl, lang('登录超时')));
                die;
            }
            $this->redirect($loginUrl);
        }

        // 检查缓存
        $this->cacheCheck();

        // 检测权限
        $control = lcfirst(request()->controller());
        $action = lcfirst(request()->action());

        if (empty(authCheck($control . '/' . $action))) {
            if (request()->isAjax()) {
                echo json_encode(msg(403, '', lang('您没有权限')));
                die;
            }

            $this->error(lang('您没有权限'));
        }

        $node_name = NodeModel::where(['control_name' => $control, 'action_name' => $action])->value('node_name');
        $admin_icon = ConfigModel::getConfigValue('admin_icon');
        View::assign([
            'admin_icon' => $admin_icon,
            'title' => $node_name,
            'head' => session('head'),
            'username' => session('username'),
            'rolename' => session('role'),
            'win' => $this->request->param('win/d', 0),
        ]);
        $this->upgrade();
    }

    private function cacheCheck() {
        $redis = Cache::store('redis');
        $redis_key = 'admin_role:' . session('role_id');
        $action = $redis->get($redis_key);

        if (is_null($action) || empty($action)) {
            // 获取该管理员的角色信息
            $roleModel = new RoleModel();
            $info = $roleModel->getRoleInfo(session('role_id'));
            $redis->set($redis_key, $info['action']);
        }
    }

    protected function removRoleCache() {
        $roleModel = new RoleModel();
        $roleList = $roleModel->getRole();

        $redis = Cache::store('redis');
        foreach ($roleList as $value) {
            $redis_key = 'admin_role:' . $value['id'];
            $redis->set($redis_key, null);
        }
    }

    /**
     * 代码升级处理
     */
    private function upgrade() {
        $redis = Cache::store('redis');
        $cache_app_version = $redis->get('app_version', 0);
        $app_version = config('my.app_version');
        if ($app_version > $cache_app_version) {
            $redis->set('app_version', $app_version);
            if ($cache_app_version < 1) {
                $iprank = NodeModel::where(['control_name' => 'stat', 'action_name' => 'iprank'])->find();
                if (empty($iprank)) {
                    $node = new NodeModel();
                    $node->insertNode([
                        'node_name' => '注册IP统计',
                        'control_name' => 'stat',
                        'action_name' => 'iprank',
                        'is_menu' => 2,
                        'type_id' => 49,
                        'sort' => 999,
                    ]);
                }
                $this->removRoleCache();
            }
            if ($cache_app_version < 7) {
                $iprank = NodeModel::where(['control_name' => 'member', 'action_name' => 'setcp'])->find();
                if (empty($iprank)) {
                    $node = new NodeModel();
                    $node->insertNode([
                        'node_name' => '设置CP',
                        'control_name' => 'member',
                        'action_name' => 'setcp',
                        'is_menu' => 1,
                        'type_id' => 38,
                        'sort' => 999,
                    ]);
                }
                $this->removRoleCache();
            }
            if ($cache_app_version < 8) {
                $model = new \app\common\model\DomainModel();
                $fields = $model->getTableFields();
                if (!in_array('ios_url', $fields)) {
                    $sql = <<<EOC
                        ALTER TABLE `qd_domain` 
ADD COLUMN `ios_url` varchar(255) NULL DEFAULT '',
ADD COLUMN `android_url` varchar(255) NULL DEFAULT '';
EOC;
                    Db::execute($sql);
                }
            }
        }
    }

}
