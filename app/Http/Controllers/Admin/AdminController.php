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
        for ($i = 0; $i < count($gameCards); $i++) {
            $gameCards[$i]['cards'] = json_decode($gameCards[$i]['cards'], true);
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
        $dice = $request->input('cards');
        $dice = json_decode($dice, true);
        for ($i = 0; $i < count($dice); $i++) {       // 判断数量
            if (count($dice) != 3 || $dice[$i] < 1 || $dice[$i] > 6) {
                $response->result = false;
                $response->message = "参数错误!";
                return json_encode($response);
            }
        }
        // 保存数据库
        $gameCards->cards = json_encode($dice);
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
