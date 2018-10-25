<?php

namespace App\Http\Controllers\user;

use App\Model\UserInfo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Model\Constant;

class UserController extends Controller
{
    /*登录*/
    public function login(Request $request){
        // 微信登录
        $key = "USER_INFO";
        $userId = $request->session()->get('userId');
        if(blank($userId)){     // 新用户
            $userId = rand(0,99999);
            $request->session()->put('userId', $userId);
            $userInfo = new UserInfo();
            $userInfo->id = $userId;
            $userInfo->nick = "nick";
            $userInfo->icon = "icon";
            $userInfo->chips = 10000;
            Redis::set($key."|".$userId, json_encode($userInfo));
        }else{
            $userInfo = json_decode(Redis::get($key."|".$userId),true);
            if($userInfo == null){
                $request->session()->flush();
                return $this->login($request);
            }
        }
        return response(json_encode($userInfo));
    }

    /*获取当局游戏信息*/
    public function getGameInfo(Request $request){
        $key = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key."|".$userId),true);
        if($userInfo == null){
            return "请先登录!";
        }
        $key2 = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key2),true);
        for($i=0;$i<count($gameInfo["position"]);$i++){
            if($gameInfo["position"][$i]["nick"] == $userInfo["nick"]){
                break;
            }
            if(blank($gameInfo["position"][$i]["nick"])){
                $gameInfo["position"][$i]["nick"] = $userInfo["nick"];
                $gameInfo["position"][$i]["icon"] = $userInfo["icon"];
                break;
            }
        }
        $gameInfo["nowTime"] = time();
        Redis::set($key2, json_encode($gameInfo));
        return response($gameInfo);
    }

    /*获取用户信息*/
    public function getUserInfo(Request $request){
        $key = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = Redis::get($key."|".$userId);
        return response($userInfo);
    }

    /*获取下注信息*/
    public function getBets(Request $request){
        $key = "BETS_INFO";       // 下注信息
        $userId = $request->session()->get('userId');
        $bets = json_decode(Redis::hget($key,$userId),true);
        if(blank($bets)){
            $constant = new Constant();
            $bets = $constant::BETS;
        }
        return response(json_encode($bets));
    }

    /*下注*/
    public function addBets(Request $request){
        $betNo = $request->input('betNo');
        $betVal = $request->input('betVal');
        if(blank($betNo) || blank($betVal) || $betNo < 1 || $betNo > 9 || $betVal < 1){
            return "参数错误!";
        }
        $key = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key),true);
        if(blank($gameInfo) || $gameInfo['status'] == 1){
            return "该局已结算!";
        }
        $key2 = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key2."|".$userId),true);
        if($userInfo == null){
            return "请先登录!";
        }
        if($betVal > $userInfo['chips']){
            return "筹码不足!";
        }
        // 保存下注信息
        $key3 = "BETS_INFO";       // 玩家信息
        $bets = json_decode(Redis::hget($key3,$userId),true);
        if(blank($bets)){
            $constant = new Constant();
            $bets = $constant::BETS;
        }
        $bets[$betNo] += $betVal;
        Redis::hset($key3,$userId,json_encode($bets));
        $userInfo["chips"] -= $betVal;
        Redis::set($key2."|".$userId,json_encode($userInfo));
        return $this->getBets($request);
    }

    /*翻倍*/
    public function putDouble(Request $request){
        $double = $request->input('double');
        if(blank($double) || $double < 0 || $double > 1){
            return "参数错误!";
        }
        $key = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key),true);
        if(blank($gameInfo) || $gameInfo['status'] == 1){
            return "该局已结算!";
        }
        $key2 = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key2."|".$userId),true);
        if($userInfo == null){
            return "请先登录!";
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
    }
}
