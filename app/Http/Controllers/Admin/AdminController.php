<?php

namespace App\Http\Controllers\admin;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\GameCards;
use App\Model\ResponseData;
use App\Model\AdminInfo;
use Webpatser\Uuid\Uuid;

class AdminController extends Controller
{

    /*查询日期所有牌组列表*/
    public function getCardsInfo(Request $request){
        $response = new ResponseData();
        $adminId = $request->session()->get('adminId');
        if(blank($adminId)) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $uuid = $request->input('uuid');
        $adminId2 = Redis::get($uuid);
        if($adminId != $adminId2){
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $startDate  = $request->input('startDate');       // 查询开始日期|001
        $endDate  = $request->input('endDate');       // 查询结束日期|460
        $gameCards = GameCards::whereBetween('id', [$startDate, $endDate])->get();
        $response->data = $gameCards;
        return json_encode($response);
    }

    /*修改牌组*/
    public function putCardsInfo(Request $request){
        $response = new ResponseData();
        $adminId = $request->session()->get('adminId');
        if(blank($adminId)) {
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $uuid = $request->input('uuid');
        $adminId2 = Redis::get($uuid);
        if($adminId != $adminId2){
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $gameId  = $request->input('gameId');
        $gameCards = GameCards::where('id',$gameId)->first();
        if($gameCards->status == 2){        // 已结算
            $response->result = false;
            $response->message = "该局已结算!";
            return json_encode($response);;
        }
        $cards = $request->input('cards');
        $cards = json_decode($cards,true);
        for($i=0;$i<10;$i++){       // 判断数量
            if(count($cards[$i]) != 5){
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
        $gameCards->cards = json_encode($cards);
        $gameCards->save();
        $response->data = $gameCards;
        return json_encode($response);
    }

    /*管理员登录*/
    public function login(Request $request){
        $response = new ResponseData();
        $username  = $request->input('username');
        $password  = $request->input('password');
        $adminInfo = AdminInfo::where('username', $username)->where('password', md5($password))->first();
        if(blank($adminInfo)){
            $response->result = false;
            $response->message = "账号密码错误!";
            return json_encode($response);
        }
        $request->session()->put('adminId', $adminInfo->id);
        $uuid = UUID::generate()->string;
        Redis::set($uuid, $adminInfo->id);
        $data['uuid'] = $uuid;
        $response->data = $data;
        return json_encode($response);
    }
}
