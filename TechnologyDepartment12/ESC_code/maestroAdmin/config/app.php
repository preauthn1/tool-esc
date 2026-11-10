<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

use think\facade\Env;

return [
    // 应用的命名空间
    'app_namespace' => '',
    // 是否启用路由
    'with_route' => true,
    // 开启应用快速访问
    'app_express' => true,
    // 默认应用
    'default_app' => 'index',
    // 默认时区
    'default_timezone' => Env::get("TIMEZONE", 'PRC'),

    //活动开始时间
    'activity_start_time' => '2025-10-22 03:00:00',

    // 应用映射（自动多应用模式有效）
    'app_map' => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind' => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list' => ['admin', 'agent'],

    // 异常页面的模板文件
    'exception_tmpl' => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message' => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg' => false,

    'upload' => [
        'mimes' => '', // 允许上传的文件MiMe类型
        'maxSize' => 100 * 1024 * 1024, // 上传的文件大小限制 (0-不做限制，默认为2M，后台配置会覆盖此值)
        'autoSub' => true, // 自动子目录保存文件
        'subName' => array('date', 'Y-m-d'), // 子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
        'rootPath' => app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'upload', // 保存根路径
        'savePath' => '', // 保存路径
        'saveName' => 'date', // 上传文件命名规则
        'saveExt' => '', // 文件保存后缀，空则使用原后缀
        'replace' => false, // 存在同名是否覆盖
        'hash' => true, // 是否生成hash编码
        'callback' => false, // 检测文件是否存在回调函数，如果存在返回文件信息数组
    ],
    //加密串
    'salt' => 'wZPb~yxvA!ir38&Z',
    // 状态
    'status' => [
        '1' => '正常',
        '2' => '禁用'
    ],
    // 会员状态
    'user_status' => [
        '1' => '正常',
        '2' => '禁用'
    ],
    // 充值状态
    'recharge_status' => [
        '0' => '待审核',
        '1' => '待支付',
        '2' => '已支付',
        '3' => '已驳回'
    ],
    // 申请状态
    'withdrawals_status' => [
        '0'  => '待处理',
        '1'  => '待审核',
        '2'  => '审核通过',
        '3'  => '审核失败',
        '4'  => '已出款',
        '5'  => '三方出款失败',
        '6'  => '线下出款失败',
        '7'  => '卡审核'
    ],

    'aws_s3' => [
        'credentials_key' => Env::get('S3.CREDENTIALS_KEY'),
        'credentials_secret' => Env::get('S3.CREDENTIALS_SECRET'),
        'region' => Env::get('S3.REGION'),
        'bucket' => Env::get('S3.BUCKET'),
        'proxy' => Env::get('S3.PROXY'),
        'url' => Env::get('S3.URL')
    ],

    'new_aws_s3' => [
        'credentials_key' => Env::get('S3.CREDENTIALS_KEY'),
        'credentials_secret' => Env::get('S3.CREDENTIALS_SECRET'),
        'region' => Env::get('NEW_S3.REGION'),
        'bucket' => Env::get('NEW_S3.BUCKET'),
        'proxy' => Env::get('NEW_S3.PROXY'),
        'url' => Env::get('NEW_S3.URL')
    ],

    'api_secret' => 'akz8VEU!kru6kgk9dhp', // API密钥
    'jwt_key' => 'REDACTED',
    'access_expires' => 24 * 3600 * 7, // token过期时间
    'refresh_expires' => 24 * 3600 * 8, // token 刷新时间
    'queue_number' => Env::get("queue.number",6),//队列数量
    'queue_name'=>Env::get("queue.queue_name","queue_name"),//队列名称 只有root 需要执行队列
];
