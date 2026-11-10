<?php

namespace app\api\listener;

use app\common\model\DomainModel;
use util\Domain;
use think\facade\Request;

class CheckDomain {

    public function handle() {
        $request = Request::instance();
        $domain = (new Domain())->getDomain($request->domain());
        $info = DomainModel::where('domain', $domain)->find();
        if (!empty($info) && $info->status == 0) {
            abort(404);
        }
        Request::instance()->domainInfo = empty($info) ? null : $info;
    }

}
