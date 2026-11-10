<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://baiyf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\model;

use think\Model;

class ArticleModel extends Model
{
    // 确定链接表名
    protected $name = 'articles';
    protected $autoWriteTimestamp = true;

    //1、公司简介  2、帮助中心  3、提现提示  4、AI智能托管规则  5、注册协议  6、推广规则  7、广告位规则  8、平台教程 9签到规则 10直推规则 11 充值规则 12提现规则 13积分规则 14佣金池规则 15推广签到规则 16团队活跃奖励规则 17团队推广奖励规则 18平台指南规则
    const TYPE_INTRODUCE = 1;
    const TYPE_HELP = 2;
    const TYPE_RECHARGE_TIPS = 3;
    const TYPE_BALANCE_RULE = 4;
    const TYPE_REGISTER = 5;
    const TYPE_PROMOTION = 6;
    const TYPE_BET_ON = 7;
    const TYPE_TUTORIAL = 8;
    const TYPE_SIGN_RULE = 9;
    const TYPE_DIRECT_RULE = 10;
    const TYPE_RECHARGE_RULE = 11;
    const TYPE_WITHDRAW_RULE = 12;
    const TYPE_PDD_RULE = 13;
    const TYPE_CREDIT_SCORE_RULE = 14;
    const TYPE_COMMISSION_POOL_RULE = 15;
    const TYPE_PROMOTION_SIGN_RULE = 16;
    const TYPE_TEAM_ACTIVE_REWARD = 17;
    const TYPE_MARKET_TRENDS = 18;

    public static array $typeMap = [
        self::TYPE_INTRODUCE => '公司简介',
        self::TYPE_HELP => '帮助中心',
        self::TYPE_RECHARGE_TIPS => '提现提示',
        self::TYPE_BALANCE_RULE => 'AI智能托管规则',
        self::TYPE_REGISTER => '注册协议',
        self::TYPE_PROMOTION => '推广规则',
        self::TYPE_BET_ON => '广告位规则',
        self::TYPE_TUTORIAL => '平台教程',
        self::TYPE_SIGN_RULE => '签到规则',
        self::TYPE_DIRECT_RULE => '直推规则',
        self::TYPE_RECHARGE_RULE => '充值规则',
        self::TYPE_WITHDRAW_RULE => '提现规则',
        self::TYPE_PDD_RULE => '拼多多转盘规则说明',
        self::TYPE_CREDIT_SCORE_RULE => '信用分说明',
        self::TYPE_COMMISSION_POOL_RULE => '个人佣金池的规则',
        self::TYPE_PROMOTION_SIGN_RULE => '连续推广奖励规则',
        self::TYPE_TEAM_ACTIVE_REWARD => '团队活跃奖励规则',
        self::TYPE_MARKET_TRENDS => '平台指南规则',
    ];

    /**
     * 查询文章
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getArticlesByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order(['sort' => 'ASC', 'id' => 'DESC'])->select();
    }

    /**
     * 根据搜索条件获取所有的文章数量
     * @param $where
     */
    public function getAllArticles($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 添加文章
     * @param $param
     */
    public function addArticle($param)
    {
        try {
            $result = $this->validate('ArticleValidate')->save($param);
            if (false === $result) {
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            } else {
                insert_operation_log('文章:添加', "{$param['title']}");
                return msg(1, url('articles/index')->build(), '添加文章成功');
            }
        } catch (\Exception $e) {
            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 编辑文章信息
     * @param $param
     */
    public function editArticle($param)
    {
        try {

            $result = $this->validate('ArticleValidate')->save($param, ['id' => $param['id']]);

            if (false === $result) {
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            } else {

                insert_operation_log('文章:修改', "{$param['title']}");
                return msg(1, url('articles/index')->build(), '编辑文章成功');
            }
        } catch (\Exception $e) {
            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 根据文章的id 获取文章的信息
     * @param $id
     */
    public function getOneArticle($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 删除文章
     * @param $id
     */
    public function delArticle($id)
    {
        try {
            $param = self::get($id);
            $this->where('id', $id)->delete();
            if ($param) {
                insert_operation_log('文章:删除', "{$param['title']}");
            }
            return msg(1, '', '删除文章成功');

        } catch (\Exception $e) {
            return msg(-1, '', $e->getMessage());
        }
    }
}
