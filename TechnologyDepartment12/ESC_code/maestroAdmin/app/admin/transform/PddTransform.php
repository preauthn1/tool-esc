<?php

namespace app\admin\transform;

class PddTransform extends Transform
{
    public function transform($items): array
    {
        return [
            'id'          => $items['id'],
            'title'       => $items['title'],
            'start_time'  => date('Y-m-d H:i:s', $items['start_time']),
            'end_time'    => date('Y-m-d H:i:s', $items['end_time']),
            'create_time' => $items['create_time'],
            'update_time' => $items['update_time'],
            'status'      => $items['status'],
            'fixed_times' => $items['fixed_times'],
            'operate'     => showOperate($this->makeButton($items['id']))
        ];
    }


    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '编辑'     => [
                'auth'     => 'pdd/add',
                'href'     => url('pdd/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon'     => 'fa fa-paste'
            ],
            '奖品设置' => [
                'auth'     => 'pdd/prize',
                'href'     => url('pdd/prize', ['id' => $id]),
                'btnStyle' => 'success',
                'icon'     => 'fa fa-gift'
            ],
            '删除'     => [
                'auth'     => 'pdd/del',
                'href'     => "javascript:del(".$id.")",
                'btnStyle' => 'danger',
                'icon'     => 'fa fa-trash-o'
            ]
        ];
    }
}
