<?php

namespace app\api\listener;
use app\common\model\LangModel;
use app\common\model\ConfigModel;
class CheckLang {

    public function handle() {
        setTimezone();
        $lang = LangModel::where('is_default_lang', 1)->find();
        if ($lang) {
            config('default_lang', $lang->lang);
            $model = new ConfigModel();
            $configInfo = $model->getConfig();
            if (!$configInfo['checkout_language']) {
                cookie('think_var', $lang->lang);
            }
        }
        if (!cookie('?think_var')) {
            if ($lang) {
                cookie('think_var', $lang->lang);
            }else{
                cookie('think_var', null);
            }
        }
    }

}
