<?php

namespace app\api\controller;

use app\admin\model\UserMessageModel;
use app\api\transform\MessageTransform;
use think\response\Json;

class Message extends Base
{
    public function index(): Json
    {
        $page = input('page/d') ? input('page/d') : 1;   // 页数
        $pageSize = input('pageSize/d') ? input('pageSize/d') : 10;  // 每页条数
        $user_messages = UserMessageModel::where('user_id', $this->userid)
            ->order(['id' => 'desc'])
            ->paginate(['page' => $page, 'list_rows' => $pageSize]);
        $data = (new MessageTransform())->transformPages($user_messages->toArray());
        return json(msg(0, $data, ''));
    }

    public function detail($id)
    {
        $message = UserMessageModel::where('user_id', $this->userid)
            ->where('id', $id)
            ->find();
        if (!$message) {
            return json(msg(-1, [], ''));
        }
        $message->is_read = 1;
        $message->read_time = time();
        $message->save();
        $data = (new MessageTransform())->transform($message);
        return json(msg(0, $data, ''));
    }

    public function pop_list(): Json
    {
        $user_messages = UserMessageModel::where('user_id', $this->userid)
            ->order('id desc')
            ->where('is_pop', 1)
            ->where('is_read', 0)
            ->select();
        // 把状态设置为已读
        UserMessageModel::where('user_id', $this->userid)
            ->where('is_pop', 1)
            ->where('is_read', 0)
            ->update(['is_read' => 1,'read_time' => time()]);
        $data = (new MessageTransform())->transformCollection($user_messages->toArray());
        return json(msg(0, $data, ''));
    }
}
