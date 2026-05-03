<?php

return [
    /*
    | 搜索引擎站点验证码
    | 在 .env 设置对应 KEY 即可启用，无需修改模板。
    | layout.blade.php 在 <head> 中按本配置自动渲染 <meta> 标签。
    */
    "verifications" => [
        "bing" => env("BING_SITE_VERIFICATION"),
        "baidu" => env("BAIDU_SITE_VERIFICATION"),
        "google" => env("GOOGLE_SITE_VERIFICATION"),
        "360" => env("SO360_SITE_VERIFICATION"),
        "sogou" => env("SOGOU_SITE_VERIFICATION"),
        "shenma" => env("SHENMA_SITE_VERIFICATION"),
    ],
];
