<?php

namespace app\command;

use app\common\model\ConfigModel;
use app\common\model\LangItemDetailModel;
use app\common\model\LangItemModel;
use app\common\model\LangModel;
use app\services\GoogleTranslate;
use think\console\Command;
use think\console\Input;
use think\console\Output;

//语言翻译
// php think lang_task
class LangTask extends Command
{
    protected function configure()
    {
        $this->setName('lang_task')->setDescription('语言翻译');
    }

    protected function execute(Input $input, Output $output)
    {
        $trans = new GoogleTranslate();
        $lang_model = new LangModel();
        $lang_list = $lang_model->where('status', 1)->column('id', 'lang');
        $lang_item_model = new LangItemModel();
        $list = $lang_item_model->order('update_time asc')->limit(5000)->select();
        $lang_detail_model = new LangItemDetailModel();
        $system_default_lang = ConfigModel::getConfigValue('system_default_lang');
        $system_default_lang_id = $lang_model->where('lang', $system_default_lang)->value('id');
        foreach ($list as $item) {
            print_r($item['title'] . "\r\n");
            $source = 'zh-CN';//Source language, 源语言
            $text = $item['title'];
            if (!$text) {
                $lang_item_model->where('id', $item['id'])->update([
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }
            $map = [
                'item_id' => $item['id'],
                'lang_id' => $system_default_lang_id,
            ];
            $item_list_detail = $lang_detail_model->where($map)->find();
            if ($item_list_detail) {
                $lang_item_model->where('id', $item['id'])->update([
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }
            foreach ($lang_list as $lang => $id) {
                if ($lang == 'zh-cn') {
                    $lang = "zh-CN";
                } else if ($lang == 'zh-tw') {
                    $lang = "zh-TW";
                }
                $result = $trans->translate($source, $lang, $text);
                $map = [
                    'item_id' => $item['id'],
                    'lang_id' => $id,
                ];
                $item_list_detail = $lang_detail_model->where($map)->find();
                if ($item_list_detail) {
                    $item_list_detail->text = $result;
                    $item_list_detail->save();
                } else {
                    LangItemDetailModel::create([
                        'item_id' => $item['id'],
                        'lang_id' => $id,
                        'text' => $result,
                    ]);
                }
                print_r($item['title'] . " to " . $lang . "\r\n");
            }
            $lang_item_model->where('id', $item['id'])->update([
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
