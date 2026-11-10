ThinkPHP 8
===============

## 特性

* 基于PHP`8.0+`重构
* 升级`PSR`依赖
* 依赖`think-orm`3.0+版本

#宝塔环境安装
     1.安装 php 版本 8.2 拓展 ionCube,fileinfo,redis,exif,igbinary
     2.安装 MySQL 8.0+
     3.安装 Redis 7.0+
     4.安装 Nginx 1.20+
#常驻进程  使用 Supervisor 工具 启动
    ##广告位服务
    php think worker_timer start
    ##队列服务
    php think queue:listen

#文件说明
    db/  文件夹下面是最新数据库  
    接口文档/  ApiFox 软件导出的接口文档

# 定时任务使用 宝塔计划任务执行
    #投资流量包结算 5分钟
        cd /www/wwwroot/maestro/api/ && php think userfinancesettle
    
    #每日用户推广统计 5分钟
        cd /www/wwwroot/maestro/api/ && php think usertasksummary
    
    #语言翻译
        cd /www/wwwroot/maestro/api/ && php think lang_task
    
    #更新广告位编码 1分钟
        cd /www/wwwroot/maestro/api/ &&  php think refresh_level_password
    
    #pubmatic-邀请活动结算 5分钟
        cd /www/wwwroot/maestro/api/ && php think inviteactivity


    #maestro-信誉分活跃统计 1分钟
    cd /www/wwwroot/maestro/api/ && php think usercreditscoretasksummary

    #pubmatic-信用分加减
        cd /www/wwwroot/maestro/api && php think creditscorecount
    
    #团队活跃奖励统计 2分钟
        cd /www/wwwroot/maestro/api/&& php think newteamactivereward
    
    #针对活动-每日用户推广统计 1分钟
        cd /www/wwwroot/maestro/api/ && php think newusertasksummary

    #maestro-昨日下级活跃给上级增加抽奖次数 1分钟
    cd /www/wwwroot/maestro/api/ && php think useractiveaddturntablecount

#url 重写

    # 通用规则：所有请求转发给 ThinkPHP 入口 index.php
    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=$1 last;
            break;
        }
    }
    
    # # 新增规则：匹配指定 API 路径，代理到 Workerman
    location = /api.php/new_finance_product/match {
        proxy_pass http://127.0.0.1:9909;
        # CORS 支持
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, uuid' always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;
    
        # 处理 OPTIONS 请求
        if ($request_method = OPTIONS) {
            return 204;
        }
    }
    # # 新增规则：匹配指定 API 路径，代理到 Workerman
    location = /api.php/new_finance_product/overdue_match {
        proxy_pass http://127.0.0.1:9909;
        # CORS 支持
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, uuid' always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;
    
        # 处理 OPTIONS 请求
        if ($request_method = OPTIONS) {
            return 204;
        }
    }

    # 匹配 /api.php/new_finance_product/get_finance_product_order 接口
    location = /api.php/new_finance_product/get_finance_product_order {
        proxy_pass http://127.0.0.1:9909;
        # CORS 支持
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, uuid' always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;
    
        # 处理 OPTIONS 请求
        if ($request_method = OPTIONS) {
            return 204;
        }
    }

#短信接口对接
    在  extend/sms/ 目录下面  如果存在  xxx.php 文件存在自己调整使用即可,,如果文件不存在自行根据接口文档进行开发
    使用地方  app/api/controller/Login.php  sendSms方法下面

#邮箱接口对接
    在  extend/email/ 目录下面  如果存在  xxx.php 文件存在自己调整使用即可,如果文件不存在自行根据接口文档进行开发
    使用地方  app/api/controller/Login.php  sendEmail方法下面

#支付接口对接
收款  extend/pay/ 目录下面  如果存在  xxx.php 文件存在自己调整使用即可,如果文件不存在自行根据接口文档进行开发
使用地方  app/api/controller/Recharge.php  getRechargeAddress方法下面

    # 实例代码
    $class_name = "pay\\".$config['class_name'];
    if (!class_exists($class_name)) {
        return json(msg(-1, '', lang('支付方式不存在,请联系客服')));
    }
    $url = (new $class_name($config))->pay($order);

打款  extend/transfer/ 目录下面  如果存在  xxx.php 文件存在自己调整使用即可,如果文件不存在自行根据接口文档进行开发

使用地方  app/admin/controller/Withdraw.php  checkStatus方法下面

    # 实例代码
    $class_name = "transfer\\" . $config['class_name'];
    if (!class_exists($class_name)) {
    return json(msg(-1, '', lang('支付方式不存在,请联系管理员')));
    }
    $result = (new $class_name($config))->pay($info);
    $info->result = json_encode($result);

#env 文件
    ##; AWS S3存储 自己配置Key 和 Secret  并且配置 Bucket 允许跨域上传
    [S3]
    CREDENTIALS_KEY = S3_KEY
    CREDENTIALS_SECRET = S3_SECRET
    BUCKET = xxx
    REGION = sa-east-1
    URL = https://xxx.s3.dualstack.sa-east-1.amazonaws.com