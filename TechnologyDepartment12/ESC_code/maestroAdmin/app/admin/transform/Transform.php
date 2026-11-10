<?php

namespace app\admin\transform;
use think\Collection;

abstract class Transform
{
    /**
     * @param $items
     * @return array
     */
    public function transformPages($items): array
    {
        return [
            'total'         => $items['total'],
            'perPage'       => $items['per_page'],
            'currentPage'   => $items['current_page'],
            'lastPage'      => $items['last_page'],
            'data'          => array_map([$this, 'transform'], $items['data'])
        ];
    }

    /**
     * @param $items
     * @return array
     */
    public function transformCollection($items): array
    {
        if (empty($items)) {
            return [];
        }
        if ($items instanceof Collection) {
            $items = $items->toArray();
        }
        return array_map([$this, 'transform'], $items);
    }

    /**
     * @param $items
     * @return mixed
     */
    public abstract function transform($items);
}
