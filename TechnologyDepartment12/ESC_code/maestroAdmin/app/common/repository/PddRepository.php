<?php

namespace app\common\repository;

use app\common\model\PddTurntableModel;


class PddRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        
        //绑定模型
        $this->model = new PddTurntableModel();
    }

}
