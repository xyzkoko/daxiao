<?php

namespace App\Http\Controllers\user;

use App\Model\UserInfo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Model\Constant;
use App\Model\ResponseData;
use App\Model\UserBet;

class UserController extends Controller
{
    /*登录*/
    public function login(Request $request)
    {
        $response = new ResponseData();
        // 测试登录
        $userKey = "USER_INFO";
        $userId = $request->session()->get('userId');
        if (blank($userId)) {     // 新用户
            $userInfo = new UserInfo();
            $userInfo->openid = mt_rand(100000, 999999);
            $userInfo->nickname = "nickname";
            $userInfo->headimgurl = "headimgurl";
            $userInfo->sex = "0";
            $userInfo->province = "province";
            $userInfo->city = "city";
            $userInfo->save();
            $request->session()->put('userId', $userInfo->id);
        } else {
            $userInfo = json_decode(Redis::get($userKey . "|" . $userId), true);
            if (blank($userInfo)) {
                $userInfo = UserInfo::where('id', $userId)->first();
                if (blank($userInfo)) {
                    $request->session()->flush();
                    return $this->login($request);
                }
            }
        }
        $userInfo['chips'] = 10000;
        //Redis::setex($userKey."|".$userInfo['id'], 7200,json_encode($userInfo));
        Redis::set($userKey . "|" . $userInfo['id'], json_encode($userInfo));
        $response->data = $userInfo;
        return json_encode($response);
        exit;
        // 微信登录
        $userKey = "USER_INFO";
        $userId = $request->session()->get('userId');
        if (blank($userId)) {     // 新用户
            $weiChatController = new WeiChatController();
            $res = $weiChatController->oauth2($request);
            if (!blank($res['errmsg'])) {
                $response->result = false;
                $response->message = $res['errmsg'];
                return json_encode($response);
            }
            $openid = $res['openid'];
            $userInfo = UserInfo::where('openid', $openid)->first();
            if (blank($userInfo)) {
                $res = $weiChatController->getUserInfo($openid, $res['access_token']);
                if (!blank($res['errmsg'])) {
                    $response->result = false;
                    $response->message = $res['errmsg'];
                    return json_encode($response);
                }
                $userInfo = new UserInfo();
                $userInfo->openid = $openid;
                $userInfo->nickname = $res['nickname'];
                $userInfo->headimgurl = $res['headimgurl'];
                $userInfo->sex = $res['sex'];
                $userInfo->province = $res['province'];
                $userInfo->city = $res['city'];
                $userInfo->save();
            }
            $request->session()->put('userId', $userInfo->id);
        } else {
            $userInfo = json_decode(Redis::get($userKey . "|" . $userId), true);
            if (blank($userInfo)) {
                $userInfo = UserInfo::where('id', $userId)->first();
                if (blank($userInfo)) {
                    $request->session()->flush();
                    return $this->login($request);
                }
            }
        }
        $userInfo['chips'] = 10000;       // TODO 筹码跟服务器要
        //Redis::setex($userKey."|".$userInfo['id'],7200, json_encode($userInfo));
        Redis::set($userKey . "|" . $userInfo['id'], json_encode($userInfo));
        $response->data = $userInfo;
        return json_encode($response);
    }

    /*获取当局游戏信息*/
    public function getGameInfo(Request $request)
    {
        $response = new ResponseData();
        $gameKey = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($gameKey), true);
        if ($gameInfo == null) {
            $response->result = false;
            $response->message = "牌局错误";
            return json_encode($response);
        }
        $gameInfo["nowTime"] = $this->getMillisecond();
        $iconKey = "ICON_INFO";       // 在线用户信息
        $iconInfo = json_decode(Redis::get($iconKey), true);
        for ($i = 1; $i < count($gameInfo["position"]); $i++) {
            $gameInfo["position"][$i]["headimgurl"] = $iconInfo[$i]["headimgurl"];
            $gameInfo["position"][$i]["nickname"] = $iconInfo[$i]["nickname"];
        }
        $response->data = $gameInfo;
        return json_encode($response);
    }

    /*获取用户信息*/
    public function getUserInfo(Request $request)
    {
        $response = new ResponseData();
        $userKey = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($userKey . "|" . $userId), true);
        if ($userInfo == null) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $response->data = $userInfo;
        return json_encode($response);
    }

    /*获取下注信息*/
    public function getBets(Request $request)
    {
        $response = new ResponseData();
        $betsKey = "BETS_INFO";       // 下注信息
        $userId = $request->session()->get('userId');
        $bets = json_decode(Redis::hget($betsKey, $userId), true);
        if (blank($bets)) {
            $constant = new Constant();
            $bets = $constant::BETS;
        }
        $response->data = $bets;
        return json_encode($response);
    }

    /*下注*/
    public function addBets(Request $request)
    {
        $response = new ResponseData();
        $betNo = $request->input('betNo');
        $betVal = $request->input('betVal');
        $double = $request->input('double');
        if (!is_array($betNo) || !is_array($betVal) || blank($double) || count($betNo) != count($betVal) || 0 > $double || 1 < $double) {
            $response->result = false;
            $response->message = "参数错误";
            return json_encode($response);
        }
        $gameKey = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($gameKey), true);
        if (blank($gameInfo) || $gameInfo['status'] != 1) {
            $response->result = false;
            $response->message = "非下注时间";
            return json_encode($response);
        }
        $userKey = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($userKey . "|" . $userId), true);
        if ($userInfo == null) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        // 保存下注信息
        $betsKey = "BETS_INFO";       // 玩家信息
        $bets = json_decode(Redis::hget($betsKey, $userId), true);
        if (blank($bets)) {
            $constant = new Constant();
            $bets = $constant::BETS;
        } else {      // 只能下一次注
            return $this->getBets($request);
        }
        $allBetVal = 0;
        for ($i = 0; $i < count($betNo); $i++) {
            if (1 > $betNo[$i] || 9 < $betNo[$i] || !is_numeric($betVal[$i])) {
                $response->result = false;
                $response->message = "参数错误";
                return json_encode($response);
            }
            $allBetVal += $betVal[$i];
            $bets[$betNo[$i]] += $betVal[$i];
        }
        $bets["double"] = $double;
        if ($double == 1 && ($allBetVal * 4 > $userInfo['chips'])) {
            $response->result = false;
            $response->message = "筹码不足";
            return json_encode($response);
        } elseif ($allBetVal > $userInfo['chips']) {
            $response->result = false;
            $response->message = "筹码不足";
            return json_encode($response);
        }
        Redis::hset($betsKey, $userId, json_encode($bets));
        $userInfo["chips"] -= $allBetVal;
        Redis::set($userKey . "|" . $userId, json_encode($userInfo));
        return $this->getBets($request);
    }

    /*翻倍*/
    /*public function putDouble(Request $request){
        $response = new ResponseData();
        $double = $request->input('double');
        if(blank($double) || $double < 0 || $double > 1){
            $response->resutt = false;
            $response->message = "参数错误";
            return json_encode($response);
        }
        $key = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key),true);
        if(blank($gameInfo) || $gameInfo['status'] != 1){
            $response->resutt = false;
            $response->message = "非下注时间";
            return json_encode($response);
        }
        $key2 = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key2."|".$userId),true);
        if($userInfo == null){
            $response->resutt = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        // 保存下注信息
        $key3 = "BETS_INFO";       // 玩家信息
        $bets = json_decode(Redis::hget($key3,$userId),true);
        if(blank($bets)){
            $constant = new Constant();
            $bets = $constant::BETS;
        }
        $bets["double"] = $double;
        Redis::hset($key3,$userId,json_encode($bets));
        return $this->getBets($request);
    }*/

    /*获取系统时间戳（毫秒）*/
    public function getTime()
    {
        $response = new ResponseData();
        $data["time"] = $this->getMillisecond();
        $response->data = $data;
        return json_encode($response);
    }

    // 毫秒级时间戳
    public static function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    /*获取历史下注信息*/
    public static function getHistoryBets(Request $request)
    {
        $response = new ResponseData();
        $startDate = $request->input('startDate');       // 查询开始日期|001
        $endDate = $request->input('endDate');       // 查询结束日期|460
        $userKey = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($userKey . "|" . $userId), true);
        if ($userInfo == null) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $userBets = UserBet::whereBetween('game_id', [$startDate, $endDate])->where('user_id', $userId)->get();
        $response->data = $userBets;
        return json_encode($response);
    }

    /*获取某一天的投注信息*/
    public static function getDateBets(Request $request)
    {
        $response = new ResponseData();
        $date = $request->input('date');       // 查询开始日期
        $userKey = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($userKey . "|" . $userId), true);
        if ($userInfo == null) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $userBets = UserBet::where('user_id', $userId)->where('game_id', 'like', $date . '%')->get();
        $data['date'] = $date;
        $data['betnum'] = $userBets->sum('betnum');
        $data['result'] = $userBets->sum('result');
        $response->data = $data;
        return json_encode($response);
    }

    /*保存玩家头像*/
    public static function saveUserIcon(Request $request)
    {
        $response = new ResponseData();
        $savaUri = config('headimgurl.sava_uri');
        $visitUri = config('headimgurl.visit_uri');
        $uid = $request->input('uid');
        $url = $request->input('headimgurl');
        $header = array(
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:45.0) Gecko/20100101 Firefox/45.0',
            'Accept-Language: zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3',
            'Accept-Encoding: gzip, deflate',);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $data = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $imgBase64Code = null;
        if ($code == 200) {     // 把URL格式的图片转成base64_encode格式的！
            $imgBase64Code = "data:image/jpeg;base64," . base64_encode($data);
        } else {
            $response->data = "error1";
            return json_encode($response);
        }
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $imgBase64Code, $result)) {
            $new_file = "{$savaUri}/daxiao_{$uid}.png";
            file_put_contents($new_file, base64_decode(str_replace($result[1], '', $imgBase64Code)));
        } else {
            $response->data = "error2";
            return json_encode($response);
        }
        // 保存数据库
        $userInfo = UserInfo::where('openid', $uid)->first();
        if (blank($userInfo)) {     // 新用户
            $userInfo = new UserInfo();
            $userInfo->openid = $uid;
            $userInfo->nickname = "nickname";
            $userInfo->headimgurl = "{$visitUri}/daxiao_{$uid}.png";
            $userInfo->sex = "0";
            $userInfo->province = "province";
            $userInfo->city = "city";
            $userInfo->save();
        }
        $response->data = $userInfo;
        return json_encode($response);
    }
}
