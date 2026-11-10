<?php

namespace app\api\transform;

class CustomerServiceTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'image' => toMedia($items['image'], '', 1),
            'link'  => $items['link'],
            'name'  => $items['name'],
        ];
    }
}
