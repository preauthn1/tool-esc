<?php

namespace app\admin\controller;

use think\facade\Db;
use app\common\model\MessageModel;
use OSS\OssClient;
use app\admin\model\RechargeModel;
use app\common\model\UserModel;
use app\admin\model\WithdrawModel;
use app\admin\model\UserModel AS AdminModel;
use think\facade\View;

/**
 * 在线客服 控制器
 */
class Message extends Base {

    public function index() {
        View::assign("title", "在线客服");
        if ($this->request->isAjax()) {
            $param  = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            if (!isset($param['pageNumber'])) {
                $param['pageNumber'] = 1;
            }
            if (!isset($param['pageSize'])) {
                $param['pageSize'] = 10;
            }
            $map = $map2 = [];
            if (!empty($param['searchText'])) {
                $uid = UserModel::where('mobile', $param['searchText'])->value('id');
                $map['user_id'] = $uid;
            }
            $limit  = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $list = [];
            $list['rows'] = db('message')->alias('m')
                ->field("DISTINCT m.user_id, MAX(m.id) AS message_id, u.mobile")
                ->where('m.master_id', 0)
                ->where($map)
                ->join('home_user u', 'u.id = m.user_id')
                ->order(["message_id" => "DESC"])
                ->limit($offset, $limit)
                ->group('m.user_id')->select();
            foreach ($list['rows'] as &$value) {
                $value['chat_url'] = url("chat", ['userid' => $value['user_id']])->build();
                $value['message'] = Db::name('message')->where("user_id", $value['user_id'])->where("master_id", 0)
                    ->order(["read" => "ASC",'id' => "DESC"])->find();
                if ($value['message']) {
                    $value['message']['create_time'] = removeMilliseconds($value['message']['create_time']);
                    $value['message']['IpLoca'] = ip2location($value['message']['ip']);
                    if (stripos($value['message']['content'], "img[") !== false) {
                        $value['message']['content'] = $this->getimg($value['message']['content']);
                    } elseif (stripos($value['message']['content'], "face[") !== false) {
                        $value['message']['content'] = $this->getfaces($value['message']['content']);
                    } else {
                        $value['message']['content'] = removeXSS($value['message']['content']);
                    }
                    $value['message']['model'] = $this->getClientMobileBrand($value['message']['browser']);
                }

                $value['reply'] = Db::name('message')->where("user_id", $value['user_id'])->where("master_id", ">", 0)->order("read ASC,id DESC")->find();
                if ($value['reply']) {
                    $value['reply']['create_time'] = removeMilliseconds($value['reply']['create_time']);
                    $value['reply']['IpLoca'] = ip2location($value['reply']['ip']);
                    if (stripos($value['reply']['content'], "img[") !== false) {
                        $value['reply']['content'] = $this->getimg($value['message']['content']);
                    } elseif (stripos($value['reply']['content'], "face[") !== false) {
                        $value['reply']['content'] = $this->getfaces($value['reply']['content']);
                    } else {
                        $value['reply']['content'] = removeXSS($value['reply']['content']);
                    }
                }
                if ($value['message']['read'] == 0) {
                    $value['style'] = "background:yellow";
                } else {
                    $value['style'] = "";
                }
            }
            unset($value);
            $list['total'] = db('message')->where('master_id', 0)->where($map)->where($map2)->count("DISTINCT user_id");
            return $list;
        }
        return View::fetch();
    }

    /**
     * 获取客户端手机型号
     * @param $agent    //$_SERVER['HTTP_USER_AGENT']
     * @return array[mobile_brand]      手机品牌
     * @return array[mobile_ver]        手机型号
     */
    public function getClientMobileBrand($agent = '') {
        if (preg_match('/iPhone\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '苹果';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/SAMSUNG|Galaxy|GT-|SCH-|SM-\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '三星';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/Huawei|Honor|H60-|H30-\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '华为';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/Mi note|mi one\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '小米';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/HM NOTE|HM201\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '红米';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/Coolpad|8190Q|5910\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '酷派';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/ZTE|X9180|N9180|U9180\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '中兴';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/OPPO|X9007|X907|X909|R831S|R827T|R821T|R811|R2017\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = 'OPPO';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/HTC|Desire\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = 'HTC';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/Nubia|NX50|NX40\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '努比亚';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/M045|M032|M355\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '魅族';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/Gionee|GN\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '金立';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/HS-U|HS-E\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '海信';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/Lenove\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '联想';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/ONEPLUS\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '一加';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/vivo\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = 'vivo';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/K-Touch\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '天语';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/DOOV\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '朵唯';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/GFIVE\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '基伍';
            $mobile_ver = $regs[0];
        } elseif (preg_match('/Nokia\s([^\s|;]+)/i', $agent, $regs)) {
            $mobile_brand = '诺基亚';
            $mobile_ver = $regs[0];
        } else {
            $mobile_brand = '其他';
            $mobile_ver = '无';
        }
        return $mobile_brand . "(" . $mobile_ver . ")";
    }

    public function chat() {
        $userid = $this->request->param("userid/d");
        $result['info'] = UserModel::getOneUser($userid);
        if (empty($result['info'])) {
            $this->error('用户信息不存在');
        }
        $result['info']['tree_id'] = explode("/", trim($result['info']['tree_id'], "/"));
        $result['info']['tree_id_mobile'] = UserModel::whereIn('id', $result['info']['tree_id'])->where('id', "<>", $userid)->column('mobile', 'id');
        $result['info']['ip_local'] = ip2location($result['info']['ip']);
        $result['info']['last_login_time'] = $result['info']['last_login_time'] > 0 ? timeToStr($result['info']['last_login_time']) : '-';
        $result['info']['last_login_ip_local'] = ip2location($result['info']['last_login_ip']);
        $result['info']['Recharge'] = RechargeModel::getRecharge($userid);
        $result['info']['TodayRecharge'] = RechargeModel::getTodayRecharge($userid);
        $result['info']['Withdraw'] = WithdrawModel::getWithdraw($userid);
        $result['info']['TodayWithdraw'] = WithdrawModel::getTodayWithdraw($userid);
//        $tipsList = db("messagefast")->where(["nullity" => 1])->order("id DESC")->field("content,title")->select();
//        View::assign("tipsList", $tipsList);
        View::assign("player", $result);
        return View::fetch();
    }

    public function mute() {
        $this->checkAuth(config('AUTH.REPLY'));
        $userid = $this->request->param("userid/d", 0);
        if (empty($userid)) {
            $this->error("用户ID不能为空");
        }

        $redis = \think\facade\Cache::store("redis3");
        $isMute = $redis->has(md5("mute{$userid}"));
        if ($isMute) {
            $redis->delete(md5("mute{$userid}"));
            insertSystemSecurity($this->user['user_name'], "客服消息提醒", "开启客服消息提醒「{$userid}」");
        } else {
            $redis->set(md5("mute{$userid}"), 1, 86400);
            insertSystemSecurity($this->user['user_name'], "客服消息提醒", "关闭客服消息提醒「{$userid}」");
        }
        $this->success("操作成功", "", !$isMute);
    }

    public function get_next() {
        $map = ["read" => 0, "master_id" => 0];
        $UserID = Db::name('message')->where($map)->value("user_id");
        if (empty($UserID)) {
            return $this->error("no");
        } else {
            return $this->success("ok", url('chat', ['userid' => $UserID])->build());
        }
    }

    public function get_tips() {
        $keyword = $this->request->param("keyword/s", "", "trim");
        $list = [];
        if (trim($keyword) !== "") {
            $list = db("messagefast")
                    ->where("title", "LIKE", "%$keyword%")
                    ->where(["status" => 1])->field("title,content")->limit(10)->select();
            foreach ($list as &$value) {
                $value['title'] = str_replace($keyword, "<font color='red'>{$keyword}</font>", $value['title']);
            }
            unset($value);
        }else{
            $list = db("messagefast")->where(["status" => 1])->order("id DESC")->field("content,title")->select();
        }

        return $this->success("ok", "", ['tips' => $list]);
    }

    public function get_list() {
        $user_id = $this->request->param("UserID/d", 0);
        $lastID = $this->request->param("lastID/d", 0);
        if (empty($user_id)) {
            $this->error("请选择用户");
        }
        $map = ["user_id" => $user_id];
        $data = Db::name('message')->where($map)->order("id ASC")->where("id", ">", $lastID)->select();
        foreach ($data as &$value) {
            if (stripos($value['content'], "img[") !== false) {
                $value['content'] = $this->getimg($value['content']);
            } elseif (stripos($value['content'], "face[") !== false) {
                $value['content'] = $this->getfaces($value['content']);
            } else {
                $value['content'] = removeXSS($value['content']);
            }
            $value['create_time'] = removeMilliseconds($value['create_time']);
            $value['reply_time'] = removeMilliseconds($value['reply_time']);
            if ($value['master_id'] == "999999") {
                $value['MasterName'] = lang("系统");
            } else {
                $value['MasterName'] = \app\admin\model\UserModel::where('id', $value['master_id'])->value('user_name');
            }
        }
        unset($value);
        $this->success("ok", "", $data);
    }

    public function reply() {
        $UserID = $this->request->param("UserID/d", 0);
        if (empty($UserID)) {
            $this->error(lang("请选择用户"));
        }
        $content = $this->request->param("content/s", "", "trim");
        if ($content === "") {
            $this->error(lang("回复内容不能为空"));
        }
        $dbObj = new MessageModel();
        $row = [
            "user_id" => $UserID,
            "content" => removeXSS($content),
            "master_id" => session('id'),
            "reply_time" => timeToStr()
        ];
        $result = $dbObj->save($row);

        if (false === $result) {
            $this->error($dbObj->getError());
        } else {
            $map = ["user_id" => $UserID, "master_id" => 0, "read" => 0];
            Db::name('message')->where($map)->where("id", "<", $dbObj->id)->update(['read' => 1]);
        }

        if (stripos($row['content'], "img[") !== false) {
            $row['content'] = $this->getimg($row['content']);
        }

        $data = [];
        $data['message_id'] = $dbObj->id;
        $data['Content'] = $row['content'];
        $data['CreateTime'] = timeToStr();
        $data['ReplyTime'] = $row['reply_time'];
        $data['MasterName'] = session('username');
        $this->success("ok", "", $data);
    }

    public function withdraw(){
        $id = $this->request->param("message_id/d", 0);
        if (empty($id)) {
            $this->error(lang("请选择撤回信息"));
        }
        Db::name('message')->where('id',$id)->delete();
        $this->success('ok',"",$id);
    }

    /**
     * 保存消息为快捷消息
     */
    public function save_fast() {
        $message = $this->request->param("message/s", "");
        $chat_id = $this->request->param("chat_id/s", "");
        if (empty($message) || empty($chat_id)) {
            $this->error("消息内容不能为空");
        }

        $FastMessage = \app\model\MessageFast::get(['chat_id' => $chat_id]);
        if (!empty($FastMessage)) {
            $this->error("该消息已添加");
        }
        \app\model\MessageFast::create(['content' => $message, 'chat_id' => $chat_id]);
        $this->success("添加成功");
    }

    public function setread() {
        $message_id = $this->request->param("message_id/d", 0);
        if (empty($message_id)) {
            $this->error("message_id 不能为空");
        }

        $Message = modelObj::get(['id' => $message_id, 'master_id' => 0]);
        if (empty($Message)) {
            $this->error("消息不存在");
        }

        db("message")->where(['user_id' => $Message['user_id'], 'master_id' => 0])->update(['read' => 1]);
        $this->success("操作成功");
    }

    public function getfaces($content) {
        $alt = ["[微笑]", "[嘻嘻]", "[哈哈]", "[可爱]", "[可怜]", "[挖鼻]", "[吃惊]", "[害羞]", "[挤眼]", "[闭嘴]", "[鄙视]", "[爱你]", "[泪]", "[偷笑]", "[亲亲]", "[生病]", "[太开心]", "[白眼]", "[右哼哼]", "[左哼哼]", "[嘘]", "[衰]", "[委屈]", "[吐]", "[哈欠]", "[抱抱]", "[怒]", "[疑问]", "[馋嘴]", "[拜拜]", "[思考]", "[汗]", "[困]", "[睡]", "[钱]", "[失望]", "[酷]", "[色]", "[哼]", "[鼓掌]", "[晕]", "[悲伤]", "[抓狂]", "[黑线]", "[阴险]", "[怒骂]", "[互粉]", "[心]", "[伤心]", "[猪头]", "[熊猫]", "[兔子]", "[ok]", "[耶]", "[good]", "[NO]", "[赞]", "[来]", "[弱]", "[草泥马]", "[神马]", "[囧]", "[浮云]", "[给力]", "[围观]", "[威武]", "[奥特曼]", "[礼物]", "[钟]", "[话筒]", "[蜡烛]", "[蛋糕]"];
        preg_match_all("/face(.*)]/U", $content, $pat_array);
        foreach ($pat_array[0] as $k => $v) {
            $key = array_search($pat_array[1][$k] . "]", $alt);
            $url = "/static/service/images/face/" . $key . ".gif";
            $pic = "<img src='{$url}'></img>";
            $content = str_replace($v, $pic, $content);
        }
        return $content;
    }

    public function getimg($content) {
        preg_match_all("/img(.*)]/U", $content, $pat_array);
        foreach ($pat_array[0] as $k => $v) {
            $url = str_replace("img[", "", "img" . $pat_array[1][$k]);
            $url = "<a target='_blank' href='{$url}'><i class='fa fa-picture'></i> 图片信息，点击查看</a>";
            $content = str_replace($v, $url, $content);
        }
        return $content;
    }

    public function upload() {
        $UploadModel = new \app\common\model\UploadModel();
        $ret = $UploadModel->Upload('file','kfimage');
        if($ret){
            $ret['data']['src'] = toMedia($ret['data']['path'], 0);
            $ret['data']['url'] = toMedia($ret['data']['path'], 0);
            return json(msg($ret['code'], $ret['data'], $ret['msg']));
        } else {
            return json(msg(-1, '', '上传失败'));
        }
    }

}
