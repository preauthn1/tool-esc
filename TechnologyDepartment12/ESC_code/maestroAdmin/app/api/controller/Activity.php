<?php

namespace app\api\controller;

use app\common\model\ShareModel;
use app\common\model\ShareTaskModel;
use think\facade\Lang;
use think\response\Json;

class Activity extends Base
{
    public function index(): Json
    {
        $page = $this->request->param('page/d', 1);
        $pageSize = $this->request->param('pageSize/d', 10);
        $list = ShareModel::where('status', 1)
            ->order(['id' => 'asc'])
            ->paginate(['page' => $page, 'list_rows' => $pageSize]);
        if (empty($list)) {
            $this->error(lang('没有数据'));
        }
        Lang::setLangSet(cookie('think_var'));
        $lang = Lang::getLangSet();
        foreach ($list as &$value) {
            $info = ShareModel::getInfoField($value['id'], $lang);
            $value['title'] = $info['title'];
        }
        return json(msg(0, $list, ''));
    }

    public function detail($id): Json
    {
        $activity = ShareModel::where('status', 1)->where('id', $id)->find();
        if (!$activity) {
            return json(msg(-1, '', lang('活动不存在')));
        }
        Lang::setLangSet(cookie('think_var'));
        $lang = Lang::getLangSet();
        $info = ShareModel::getInfoField($id, $lang);
        $activity['title'] = $info['title'];
        $activity['step'] = $info['step'];
        $activity['content'] = $info['content'];
        $share_link = getHost() . url('Login/register', ['inviteCode' => $this->userInfo['invite_code']]);

        $share_task_model = new ShareTaskModel();
        $task = $share_task_model->getEndTask($this->userid, $id);
        if (empty($task['data'])) {
            $task['data']['status'] = 0;
        }
        if (!isset($task['data']['explain'])) {
            $task['data']['explain'] = "";
        }
        if (!isset($task['data']['img'])) {
            $task['data']['img'] = "";
        }
        return json(msg(0, [
            'share_link' => $share_link,
            'activity' => $activity,
            'task' => $task['data']
        ], ''));
    }

    /**
     * 参加分享活动
     */
    public function join(): Json
    {
        $lock = userLock($this->userid, 'receive', 5);
        if (!$lock) {
            return json(msg(-1, '', lang('请勿频繁提交')));
        }
        $share_id = $this->request->param('share_id');
        $explain = $this->request->param('explain');
        $img = $this->request->param('img');
        $share_task_model = new ShareTaskModel();
        $data = $share_task_model->addShareTask($share_id, $this->userid, $explain, $img, get_real_ip());
        if ($data['code'] == 1) {
            return json(msg(0, '', lang('成功')));
        } else {
            return json(msg(-1, '', lang($data['msg'])));
        }
    }
}
