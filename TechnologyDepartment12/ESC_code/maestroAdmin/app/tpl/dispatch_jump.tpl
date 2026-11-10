{__NOLAYOUT__}<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,user-scalable=no,initial-scale=1,maximum-scale=1,minimum-scale=1">
    <title>{:lang('跳转提示')}</title>
    <link href="/static/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="/static/libs/bootstrap/css/font-awesome.min.css" rel="stylesheet"/>
    <style type="text/css">
        *{ padding: 0; margin: 0; }
        body{ background: #fff; font-family: "Microsoft Yahei","Helvetica Neue",Helvetica,Arial,sans-serif; color: #333; font-size: 16px; }
        .system-message{ padding: 48px 0; text-align: center;}
        .system-message-icon i{
            color: #07c160;
            font-size: 64px;
        }
        .system-message-icon.error i{
            color: #fa5151;
        }
        .system-message-text-area {
            margin-bottom: 32px;
            padding: 0 32px;
            -webkit-box-flex: 1;
            -webkit-flex: 1;
            flex: 1;
            line-height: 1.6;
        }
        .title {
            margin-bottom: 16px;
            font-weight: 700;
            font-size: 22px;
            color: rgba(0,0,0,0.9);
            word-wrap: break-word;
            word-break: break-all;
        }
        .desc {
            font-size: 17px;
            color: rgba(0,0,0,0.9);
            word-wrap: break-word;
            word-break: break-all;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="container system-message">
        <?php switch ($code) {?>
            <?php case 1:?>
            <div class="system-message-icon">
                <i class="fa fa-check-circle" aria-hidden="true"></i>
            </div>
            <div class="system-message-text-area">
                <h2 class="title">{:lang('操作成功')}</h2>
                <p class="desc"><?php echo(strip_tags($msg));?></p>
            </div>
            <?php break;?>
            <?php case 0:?>
            <div class="system-message-icon error">
                <i class='fa fa-exclamation-circle' aria-hidden="true"></i>
            </div>
            <div class="system-message-text-area">
                <h2 class="title">{:lang('操作失败')}</h2>
                <p class="desc"><?php echo(strip_tags($msg));?></p>
            </div>
            <?php break;?>
        <?php } ?>

        <div class="system-message-opr-area">
            <p class="weui-btn-area">
                <a href="<?php echo($url);?>" id='href' class="btn btn-default">{:lang('跳转')} <span id="wait"><?php echo($wait);?></span></a>
            </p>
        </div>
    </div>
    <script type="text/javascript">
        (function(){
            var wait = document.getElementById('wait'),
                href = document.getElementById('href').href;
            var interval = setInterval(function(){
                var time = --wait.innerHTML;
                if(time <= 0) {
                    location.href = href;
                    clearInterval(interval);
                };
            }, 1000);
        })();
    </script>
</body>
</html>
