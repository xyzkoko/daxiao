<?php

namespace App\Http\Controllers\admin;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\GameCards;
use App\Model\ResponseData;
use App\Model\AdminInfo;
use Webpatser\Uuid\Uuid;
use App\Http\Controllers\game\GameController;

class AdminController extends Controller
{

    /*查询日期所有牌组列表*/
    public function getCardsInfo(Request $request)
    {
        $response = new ResponseData();
        $uuid = $request->input('uuid');
        $adminId = Redis::get($uuid);
        if (blank($adminId)) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        Redis::setex($uuid, 1800, $adminId);     // 刷新uuid有效期
        $startDate = $request->input('startDate');       // 查询开始日期|001
        $endDate = $request->input('endDate');       // 查询结束日期|480
        $gameCards = GameCards::whereBetween('id', [$startDate, $endDate])->get()->toArray();
        if (count($gameCards) != 0) {
            for ($i = 0; $i < count($gameCards); $i++) {
                $cards = $gameCards[$i]['cards'] = json_decode($gameCards[$i]['cards'], true);
                for ($j = 0; $j < count($cards); $j++) {
                    $gameCards[$i]['points'][] = GameController::getPoint($cards[$j]);
                }
            }
        }
        $response->data = $gameCards;
        return json_encode($response);
    }

    /*修改牌组*/
    public function putCardsInfo(Request $request)
    {
        $response = new ResponseData();
        $uuid = $request->input('uuid');
        $adminId = Redis::get($uuid);
        if (blank($adminId)) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        Redis::setex($uuid, 1800, $adminId);     // 刷新uuid有效期
        $gameId = $request->input('gameId');
        $gameCards = GameCards::where('id', $gameId)->first();
        if ($gameCards->status == 2) {        // 已结算
            $response->result = false;
            $response->message = "该局已结算!";
            return json_encode($response);;
        }
        $cards = $request->input('cards');
        $cards = json_decode($cards, true);
        for ($i = 0; $i < 10; $i++) {       // 判断数量
            if (count($cards[$i]) != 5) {
                $response->result = false;
                $response->message = "参数错误!";
                return json_encode($response);
            }
        }
        $allCards = array_collapse($cards);     // 判断重复
        if (count($allCards) != count(array_unique($allCards))) {
            $response->result = false;
            $response->message = "参数错误!";
            return json_encode($response);
        }
        // 保存数据库
        $gameCards->cards = GameController::sortCards($cards);
        $gameCards->save();
        $response->data = $gameCards;
        return json_encode($response);
    }

    /*管理员登录*/
    public function login(Request $request)
    {
        $response = new ResponseData();
        $username = $request->input('username');
        $password = $request->input('password');
        $adminInfo = AdminInfo::where('username', $username)->where('password', md5($password))->first();
        if (blank($adminInfo)) {
            $response->result = false;
            $response->message = "账号密码错误!";
            return json_encode($response);
        }
        $request->session()->put('adminId', $adminInfo->id);
        $uuid = UUID::generate()->string;
        Redis::setex($uuid, 1800, $adminInfo->id);
        $data['uuid'] = $uuid;
        $response->data = $data;
        return json_encode($response);
    }
}
