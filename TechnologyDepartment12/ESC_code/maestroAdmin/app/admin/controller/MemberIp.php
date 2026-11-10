<?php

namespace app\admin\controller;

use app\common\model\MemberLogModel;
use app\common\model\UserModel;
use think\facade\Db;
use think\facade\View;

class MemberIp extends Base
{

    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = '';
            if (array_key_exists('user_id', $param) && $param['user_id']) {
                $where = ' and user_id = '.$param['user_id'];
            }
            if (array_key_exists('ip', $param) && $param['ip']) {
                $where = ' and a.ip = "'.$param['ip'] . '"';
            }
            if (array_key_exists('uuid', $param) && $param['uuid']) {
                $where = ' and b.uuid = "'.$param['uuid'] . '"';
            }
            if (array_key_exists('user_name', $param) && $param['user_name']) {
                $user_ids = UserModel::whereLike('user_name', "%{$param['user_name']}%")->column('id');
                if (!empty($user_ids)) {
                    $user_ids = implode(',', $user_ids);
                    $where = ' and user_id in ('.$user_ids . ')';
                }
            }

            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime']);
                $end_time = strtotime($param['endTime']);
                $where = ' and latest_login_time between '.$start_time.' and '.$end_time;
            }

            $ip = 'and ip != "203.170.142.100" and ip != "203.170.142.101" and ip != "171.102.239.157" and ip != "171.102.239.156" and ip != "203.170.142.107" and ip != "110.78.118.35" and ip != "110.78.118.36" and ip != "203.170.225.197" and ip != "203.170.142.185" and ip != "203.170.142.184" and ip != "203.170.225.194" and ip != "203.156.12.21" and ip != "171.102.10.168"';
            $sql = 'SELECT
	a.ip,
	b.user_id,
	b.latest_login_time,
	b.device,
	b.uuid,
	c.user_name
FROM
	( SELECT ip, count(*) ip_count FROM `qd_home_user_ip` GROUP BY ip HAVING ip_count > 1 '.$ip.' ORDER BY ip_count DESC ) AS a
	LEFT JOIN ( SELECT user_id, ip, device, uuid, MAX( create_time ) latest_login_time FROM qd_home_user_log WHERE type = 0 GROUP BY user_id, ip ORDER BY user_id ) AS b ON a.ip = b.ip
	LEFT JOIN qd_home_user c on c.id = b.user_id
WHERE
	b.user_id > 0
	'.$where.'
ORDER BY
	a.ip_count DESC
	LIMIT '.$offset.' , '.$limit;

            $total_sql = 'SELECT
	count(*) as total
FROM
	( SELECT ip, count(*) ip_count FROM `qd_home_user_ip` GROUP BY ip HAVING ip_count > 1 '.$ip.' ORDER BY ip_count DESC ) AS a
	LEFT JOIN ( SELECT user_id, ip, device, uuid, MAX( create_time ) latest_login_time FROM qd_home_user_log WHERE type = 0 GROUP BY user_id, ip ORDER BY user_id ) AS b ON a.ip = b.ip
	LEFT JOIN qd_home_user c on c.id = b.user_id
WHERE
	b.user_id > 0
	'.$where;
            $result = DB::query($sql);
            $count = DB::query($total_sql);
            // 拼装参数
            foreach ($result as $key => $vo) {
                $result[$key]['area'] =ip2location($vo['ip']);
                $result[$key]['latest_login_time'] = date('Y-m-d H:i:s', $vo['latest_login_time']);
                $top_leader = UserModel::getTopLeader($vo['user_id']);
                $result[$key]['top_leader_remark'] = '';
                if ($top_leader) {
                    $result[$key]['top_leader_remark'] = $top_leader['remark'];
                }
                $result[$key]['ip_count'] = MemberLogModel::where('ip', $vo['ip'])->where('type', 0)->count('distinct user_id');;
            }
            $return['total'] = $count[0]['total'];  //总数据
            $return['rows'] = $result;
           return $return;
        }
        return View::fetch();
    }
}
