<?php

use think\facade\Db;

/**
 * @function 动态的设置env文件中某项配置值
 * @param    $env_path string     env文件路径
 * @param    $key      string     配置项
 * @param    $val      string|int 配置值
 * @param    $old      string|int 原始配置值
 * @return   bool      返回是否成功修改
 * @other    如果env文件中没有这$key这一项，会在env文件末尾追加
 */
function setEnvVal($env_path, $key, $val)
{
//    $env_path = '.env';

// 读取 .env 文件到数组
    $lines = file_get_contents($env_path);
    $len = strpos($lines, $key . ' = ');
    $lines_start = substr($lines, 0, $len + 14);
    $lines_end = substr($lines, $len + 20);
    $lines_all = $lines_start . $val . $lines_end;
    file_put_contents($env_path, $lines_all);
    return true;
}

// 获取排序
function GetOrderID($tbname, $field = 'sort')
{
    $r = Db::name($tbname)->max($field);
    $sortid = (empty($r) ? 1 : ($r + 1));
    return $sortid;
}

/**
 * 生成操作按钮
 * @param array $operate 操作按钮数组
 */
function showOperate($operate = [])
{
    if (empty($operate)) {
        return '';
    }

    $option = '';
    foreach ($operate as $key => $vo) {
        if (authCheck($vo['auth'])) {
            $data = isset($vo['data']) ? $vo['data'] : '';
            $option .= ' <a href="' . $vo['href'] . '"><button ' . $data . ' type="button" class="btn btn-sm btn-' . $vo['btnStyle'] . '">' .
                '<i class="' . $vo['icon'] . '"></i> ' . lang($key) . '</button></a>';
        }
    }

    return $option;
}

/**
 * 将字符解析成数组
 * @param $str
 */
function parseParams($str)
{
    $arrParams = [];
    parse_str(html_entity_decode(urldecode($str)), $arrParams);
    return $arrParams;
}

/**
 * 子孙树 用于菜单整理
 * @param $param
 * @param int $pid
 */
function subTree($param, $pid = 0)
{
    static $res = [];
    foreach ($param as $key => $vo) {

        if ($pid == $vo['pid']) {
            $res[] = $vo;
            subTree($param, $vo['id']);
        }
    }

    return $res;
}

/**
 * 整理菜单住方法
 * @param $param
 * @return array
 */
function prepareMenu($param)
{
    $param = objToArray($param);
    $parent = []; //父类
    $child = [];  //子类

    foreach ($param as $key => $vo) {

        if (0 == $vo['type_id']) {
            $vo['href'] = '#';
            $parent[] = $vo;
        } else {
            $vo['href'] = url($vo['control_name'] . '/' . $vo['action_name']); //跳转地址
            $child[] = $vo;
        }
    }

    foreach ($parent as $key => $vo) {
        foreach ($child as $k => $v) {

            if ($v['type_id'] == $vo['id']) {
                $parent[$key]['child'][] = $v;
            }
        }
    }
    unset($child);

    return $parent;
}

/**
 * 解析备份sql文件
 * @param $file
 */
function analysisSql($file)
{
    // sql文件包含的sql语句数组
    $sqls = array();
    $f = fopen($file, "rb");
    // 创建表缓冲变量
    $create = '';
    while (!feof($f)) {
        // 读取每一行sql
        $line = fgets($f);
        // 如果包含空白行，则跳过
        if (trim($line) == '') {
            continue;
        }
        // 如果结尾包含';'(即为一个完整的sql语句，这里是插入语句)，并且不包含'ENGINE='(即创建表的最后一句)，
        if (!preg_match('/;/', $line, $match) || preg_match('/ENGINE=/', $line, $match)) {
            // 将本次sql语句与创建表sql连接存起来
            $create .= $line;
            // 如果包含了创建表的最后一句
            if (preg_match('/ENGINE=/', $create, $match)) {
                // 则将其合并到sql数组
                $sqls [] = $create;
                // 清空当前，准备下一个表的创建
                $create = '';
            }
            // 跳过本次
            continue;
        }

        $sqls [] = $line;
    }
    fclose($f);

    return $sqls;
}

/**
 * 对象转换成数组
 * @param $obj
 */
function objToArray($obj)
{
    return json_decode(json_encode($obj), true);
}

/**
 * 权限检测
 * @param $rule
 */
function authCheck($rule)
{
    $control = explode('/', $rule)['0'];
    if (in_array($control, ['login', 'index'])) {
        return true;
    }
    if (session('role_id') == 1) {
        return true;
    }
    $rule = str_replace('_', '', strtolower($rule));
    $redis = \think\facade\Cache::store('redis');
    $redis_key = 'admin_role:' . session('role_id');
    $rules = $redis->get($redis_key);
    if (empty($rules) || !is_array($rules)) {
        return false;
    }
    if (in_array($rule, $rules)) {
        return true;
    }

    return false;
}

/**
 * 整理出tree数据 ---  layui tree
 * @param $pInfo
 * @param $spread
 */
function getTree($pInfo, $spread = true)
{

    $res = [];
    $tree = [];
    //整理数组
    foreach ($pInfo as $key => $vo) {

        if ($spread) {
            $vo['spread'] = true;  //默认展开
        }
        $res[$vo['id']] = $vo;
        $res[$vo['id']]['children'] = [];
    }
    unset($pInfo);

    //查找子孙
    foreach ($res as $key => $vo) {
        if (isset($vo['pid']) && 0 !== $vo['pid']) {
            $res[$vo['pid']]['children'][] = &$res[$key];
        }
    }

    //过滤杂质
    foreach ($res as $key => $vo) {
        if (isset($vo['pid']) && 0 == $vo['pid']) {
            $tree[] = $vo;
        }
    }
    unset($res);

    return $tree;
}

function show_img($text, $return = false)
{
    if (strexists($text, 'https://') || strexists($text, 'http://')) {
        if (strexists(strtolower($text), ".mp4")) {
            return "<a href='{$text}' target='_blank' style='color:blue;'>[视频链接]</a>";
        } else {
            return "<a href='{$text}' target='_blank'><img src='{$text}' style='height:20px;width:auto;'/></a>";
        }
    }

    return $return ? $text : '';
}

/**
 * 保存安全日志
 * @param string $action 操作名称
 * @param array|string $content 操作记录
 */
function insert_operation_log(string $action = '', array|string $content = [], $user_id = 0): void
{
    $data = [
        'username' => session('username'),
        'admin_id' => session('id'),
        'action' => $action,
        'content' => is_array($content) ? json_encode($content) : $content,
        'user_id' => $user_id,
    ];
    app\admin\model\OperationModel::create($data);
}

/**
 * 获取用户数
 * @param type $type 1 当天注册人数 2当天登录人数
 * @return type
 */
function getTotalUser($type = 0)
{
    $result = 0;
    if ($type == 1) { //当天注册人数
        $result = Db::name('home_user')->where('is_xuni', 0)->whereTime('create_time', 'today')->count('id');
    } elseif ($type == 2) { //活跃人数，当天登录用户
        $result = Db::name('home_user_login')->alias('l')->join('home_user u', 'u.id=l.user_id')->where('u.is_xuni', 0)
            ->whereTime('l.create_time', 'today')->count('DISTINCT user_id');
    } else {
        $result = Db::name('home_user')->where('is_xuni', 0)->count('id');
    }
    return intval($result);
}

/**
 * 获取充值用户数
 * @param type $type 1 当天充值人数 2当天首充人数 3当天复充人数
 * @return type
 */
function getTotalRechargeUser($type = 0)
{
    if ($type == 1) { //当天充值人数
        return Db::name('recharge')->alias('r')->join('home_user u', 'u.id=r.user_id')
            ->where('u.is_xuni', 0)->where('r.status', 2)->whereTime('r.create_time', 'today')->count('DISTINCT user_id');
    } elseif (in_array($type, [2, 3])) { //当天首充人数
        $today_userids = Db::name('recharge')->alias('r')->join('home_user u', 'u.id=r.user_id')
            ->where('u.is_xuni', 0)->where('r.status', 2)->whereTime('r.create_time', 'today')->column('DISTINCT user_id');
        $before_today_userids = Db::name('recharge')->alias('r')->join('home_user u', 'u.id=r.user_id')
            ->where('u.is_xuni', 0)->where('r.status', 2)->whereTime('r.create_time', "<", date("Y-m-d 00:00:00"))->column('DISTINCT user_id');
        $num = 0;
        foreach ($today_userids as $id) {
            if ((!in_array($id, $before_today_userids) && $type == 2) || (in_array($id, $before_today_userids) && $type == 3)) {
                $num++;
            }
        }
        return $num;
    } else {
        return Db::name('recharge')->alias('r')->join('home_user u', 'u.id=r.user_id')
            ->where('u.is_xuni', 0)->where('r.status', 2)->count('DISTINCT user_id');
    }
}
