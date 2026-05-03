<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 搜索引擎主动推送配置
    |--------------------------------------------------------------------------
    | 文章发布或重大编辑时自动调用，让搜索引擎秒级感知。
    | 由 ArticleSeoPushObserver → PushSeoUrlsJob → SearchEnginePushService 链路使用。
    | 配置缺省时该引擎跳过，不影响其它引擎。
    */

    'push' => [
        'baidu' => [
            // 百度搜索资源平台「普通收录 → API 提交」给的 site 与 token
            // 例：site = https://geo.rezzy.cn  token = xxxxxxxxxxx
            'site' => env('SEO_BAIDU_PUSH_SITE'),
            'token' => env('SEO_BAIDU_PUSH_TOKEN'),
        ],

        'indexnow' => [
            // 自行生成的 32 位 hex 密钥；同名文件需在 https://{host}/{key}.txt 暴露内容相同的字符串
            // 已通过 docker/nginx/default.conf 的 alias 让 storage/app/public/seo/{key}.txt 在根路径可访问
            'key' => env('SEO_INDEXNOW_KEY'),
            'host' => env('SEO_INDEXNOW_HOST', 'geo.rezzy.cn'),
        ],
    ],
];
