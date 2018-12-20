<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
class TestController extends Controller
{
    public function redis(Request $request)
    {
        $data = $request->input();
        $result = Redis::set('redis', json_encode($data));
        echo $result;
        $redis = Redis::get('redis');
        return response($redis);
    }

    public function session(Request $request)
    {
        //$request->session()->flush();
        $request->session()->put('key', 'value');
        $value = $request->session()->all();
        return response($value);
    }

    public function collect(Request $request)
    {
        $list = collect([['foo' => 10], ['foo' => 10], ['foo' => 20], ['foo' => 40]]);
        $map = collect(['name' => 'Desk', 'price' => 100]);
        $value = $list[0];
        $value2 = $map['name'];
        return response([$value,$value2]);
    }

    public function cookie(Request $request)
    {
        return $request->cookie();
    }
}
