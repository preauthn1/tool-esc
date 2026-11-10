<?php
namespace app\admin\transform;

class FinanceProductTransform extends Transform
{
    public function transform($items): array
    {
        $items['operate'] = showOperate($this->makeButton($items['id']));
        $items['image'] = show_img(toMedia($items['image'], '/upload/', 1), true);

        return $items;
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id): array
    {
        return [
            '编辑' => [
                'auth'     => 'financeProduct/add',
                'href'     => url('finance_product/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon'     => 'fa fa-paste'
            ],
            '删除' => [
                'auth'     => 'financeProduct/financeProductDel',
                'href'     => "javascript:financeProductDel(".$id.")",
                'btnStyle' => 'danger',
                'icon'     => 'fa fa-trash-o'
            ]
        ];
    }
}
