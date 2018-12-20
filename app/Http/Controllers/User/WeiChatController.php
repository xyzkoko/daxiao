<?php

namespace App\Http\Controllers\user;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WeiChatController extends Controller
{
    public function oauth2(Request $request)
    {
        $code = $request->input('code');
        $appId = config('weichat.appId');
        $appSecret = config('weichat.appSecret');
        $redirect_uri = config('weichat.redirect_uri');
        if ($code == null) {
            $scope = 'snsapi_userinfo';
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $appId . '&redirect_uri=' . urlencode($redirect_uri) . '&response_type=code&scope=' . $scope . '&state=STATE#wechat_redirect';
            header("Location:" . $url);
            exit;
        }
        //根据code获取access_token和openid
        $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appId . '&secret=' . $appSecret . '&code=' . $code . '&grant_type=authorization_code';
        $res = file_get_contents($get_token_url);
        $json_decode = json_decode($res, true);
        return $json_decode;
    }

    public function getUserInfo($openid, $access_token)
    {
        $get_user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $access_token . '&openid=' . $openid . '&lang=zh_CN';
        $res = file_get_contents($get_user_info_url);
        $json_decode = json_decode($res, true);
        return $json_decode;
    }
}
