<p align="center"><img src="https://laravel.com/assets/img/components/logo-laravel.svg"></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License"></a>
</p>

## 部署
项目采用php 7.2.10\laravel 5.6
- clone项目到本地
- 给/storage /bootstrap/cache /public/photo 777权限
- 执行SQL文件 niuniu.sql 执行计划中会自动删除7日前的牌组 请自行取舍
- 复制.env.example 重命名为.env 并更改数据库和Reidis配置
- composer install\php artisan key:generate
- 修改/config/headimgurl.php 头像地址 并在项目根目录执行php artisan config:cache生成laravel缓存文件
- 开启定时任务 crontab -e 添加如下指令(php目录和项目目录根据部署情况修改)
指令 * * * * * /usr/local/php/bin/php /data/wwwroot/nn/artisan schedule:run >> /dev/null 2>&1
- 第一次部署可手动执行生成当日和次日的牌组 系统每日0点会自动生成次日牌组
Route::get('/game/add', 'GameController@addTodayGameList');
Route::get('/game/gameInfo', 'GameController@getGameInfo');
