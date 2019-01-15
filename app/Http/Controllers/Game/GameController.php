<?php

namespace App\Http\Controllers\game;

use App\model\UserInfo;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Http\Controllers\user\UserController;
use App\Model\GameCards;
use App\Model\GameInfo;
use App\Model\Constant;
use App\Model\UserBet;
use App\Model\ResponseData;

class GameController extends Controller
{
    /*每三分钟运行一次进行游戏*/
    public function startGame()
    {
        set_time_limit(180);
        $idKey = "GAME_ID";       // 当局ID
        $hour = date('H');
        $minute = date('i');
        $num = intval(($hour * 60 + $minute) / 3) + 1;
        $num = sprintf("%03d", $num);       // 补齐3位
        $gameId = date('Ymd') . '|' . $num;
        Redis::set($idKey, $gameId);      // 更新当局ID
        // 准别阶段
        $gameKey = "GAME_INFO";       // 当局信息
        Redis::set($gameKey, json_encode(new GameInfo()));
        $gameInfo = json_decode(Redis::get($gameKey), true);
        $gameInfo['gameId'] = $gameId;
        $gameInfo['startTime'] = UserController::getMillisecond();
        $gameInfo['status'] = 0;
        Redis::set($gameKey, json_encode($gameInfo));            // 更新Redis
        $betsKey = "BETS_INFO";       // 下注信息
        Redis::del($betsKey);
        sleep(105);      // 等待
        // 下注阶段
        $gameInfo['status'] = 1;
        Redis::set($gameKey, json_encode($gameInfo));            // 更新Redis
        sleep(40);      // 等待
        // 结算阶段
        $gameCards = GameCards::find($gameId);
        $gameCards->status = 2;
        $gameCards->save();
        $gameInfo['dice'] = $gameCards["cards"];
        $gameInfo['status'] = 2;
        Redis::set($gameKey, json_encode($gameInfo));      // 更新Redis
        return "success";
    }

    /*每天早上生成次日的牌组*/
    public function addGameList()
    {
        $data = date("Ymd", strtotime("+1 day"));
        $closeTime = strtotime($data) + 105;
        for ($i = 1; $i <= 480; $i++) {
            $gameCards = new GameCards;
            $gameCards->id = $data . '|' . sprintf("%03d", $i);       // 补齐3位;
            $gameCards->cards = json_encode(array(rand(1, 6), rand(1, 6), rand(1, 6)));
            $gameCards->close_time = $closeTime * 1000;
            $gameCards->save();
            $closeTime += 180;
        }
        echo 'success';
    }

    /*生成今天的牌组*/
    public function addTodayGameList()
    {
        $data = date("Ymd");
        $closeTime = strtotime($data) + 105;
        for ($i = 1; $i <= 480; $i++) {
            $gameCards = new GameCards;
            $gameCards->id = $data . '|' . sprintf("%03d", $i);       // 补齐3位;
            $gameCards->cards = json_encode(array(rand(1, 6), rand(1, 6), rand(1, 6)));
            $gameCards->close_time = $closeTime * 1000;
            $gameCards->save();
            $closeTime += 180;
        }
        echo 'success';
    }

    /*获取当前牌局信息*/
    public function getGameInfo()
    {
        $response = new ResponseData();
        $idKey = "GAME_ID";       // 当局ID
        $nextGameId = Redis::get($idKey);
        $pieces = explode("|", $nextGameId);
        if ($pieces[1] == "001") {
            $date = date('Ymd', strtotime("-1 day"));
            $num = 480;
        } else {
            $date = $pieces[0];
            $num = $pieces[1] - 1;
        }
        $num = sprintf("%03d", $num);       // 补齐3位
        $gameId = $date . '|' . $num;
        $gameCards = GameCards::find($gameId)->toArray();
        $dice = json_decode($gameCards['cards'],true);
        $dice[] = array_sum($dice);
        $data['dice'] = implode(",",$dice);
        $data['gameId'] = $gameId;
        $data['nextGameId'] = $nextGameId;
        $response->data = $data;
        return json_encode($response);
    }

    /*更改座位玩家头像*/
    public function changeIcon()
    {
        set_time_limit(60);
        $userInfo = UserInfo::inRandomOrder()->take(50)->get()->toArray();
        $userInfCcolumn = array_column($userInfo, 'nickname', 'headimgurl');        // 头像集合
        $userInfCcolumn = $this->changeIconImpl($userInfCcolumn);
        sleep(10);
        $userInfCcolumn = $this->changeIconImpl($userInfCcolumn);
        sleep(10);
        $userInfCcolumn = $this->changeIconImpl($userInfCcolumn);
        sleep(10);
        $userInfCcolumn = $this->changeIconImpl($userInfCcolumn);
        sleep(10);
        $userInfCcolumn = $this->changeIconImpl($userInfCcolumn);
        sleep(10);
        $this->changeIconImpl($userInfCcolumn);
    }

    private function changeIconImpl($userInfCcolumn)
    {
        $iconKey = "ICON_INFO";       // 在线用户信息
        $iconInfo = json_decode(Redis::get($iconKey), true);
        if ($iconInfo == null) {
            $iconInfo = [null, null, null, null, null, null, null, null, null, null];
        }
        $GameKey = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($GameKey), true);
        if ($gameInfo == null) {      // 游戏未开始
            return $userInfCcolumn;
        }
        for ($i = 1; $i < 10; $i++) {
            if ($gameInfo['status'] == 2) {       // 已结算
                $p = 1;     // 概率
            } else {      // 未结算
                $p = 3;
            }
            $rand = rand(1, 10);
            if (blank($iconInfo[$i]) || $rand <= $p) {      // 换座位
                $iconInfoCcolumn = array_column($iconInfo, 'nickname', 'headimgurl');        // 在线头像集合
                $userInfCcolumn = array_diff_key($userInfCcolumn, $iconInfoCcolumn);     // 取差集
                if (count($userInfCcolumn) == 0) {
                    return $userInfCcolumn;
                }
                reset($userInfCcolumn);
                $key = key($userInfCcolumn);
                $iconInfo[$i]['nickname'] = $userInfCcolumn[$key];
                $iconInfo[$i]['headimgurl'] = $key;
                unset($userInfCcolumn[$key]);
            }
        }
        Redis::set($iconKey, json_encode($iconInfo));
        return $userInfCcolumn;
    }


}

