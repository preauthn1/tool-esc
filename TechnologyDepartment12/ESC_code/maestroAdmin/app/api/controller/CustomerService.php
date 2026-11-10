<?php

namespace app\api\controller;

use app\api\transform\CustomerServiceTransform;
use app\common\repository\CustomerServiceRepository;
use app\BaseController;
use think\response\Json;

class CustomerService extends BaseController
{
    public function index(CustomerServiceRepository $repository, CustomerServiceTransform $transform): Json
    {
        $customer_services = $repository->order(['sort' => 'asc'])
            ->selectWhere([['status', '=', 1]], ['image', 'link', 'name']);
        $data = $transform->transformCollection($customer_services->toArray());
        return json(msg(0, $data, ''));
    }

}
