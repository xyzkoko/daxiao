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

    /*获取当局游戏信息*/
    public function getGameInfo()
    {
        $response = new ResponseData();
        $gameKey = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($gameKey), true);
        if ($gameInfo == null) {
            $response->result = false;
            $response->message = "牌局错误";
            return json_encode($response);
        }
        $gameInfo["dice"] = json_decode($gameInfo["cards"]);
        $gameInfo["nowTime"] = $this->getMillisecond();
        $response->data = $gameInfo;
        return json_encode($response);
    }

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
