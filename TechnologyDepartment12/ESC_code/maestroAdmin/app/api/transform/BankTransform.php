<?php

namespace app\api\transform;

class BankTransform extends Transform
{

    public function transform($items): array
    {
        return [
            'id'               => $items['id'],
            'name'             => $items['name'],
            'logo'             => $items['logo'],
            'bank_code'        => $items['bank_code'],
            'bank_number_rule' => $items['bank_number_rule'],
        ];
    }
}
