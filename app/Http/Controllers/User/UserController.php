<?php

namespace App\Http\Controllers\user;

use App\Model\UserInfo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Model\Constant;
use App\Model\ResponseData;

class UserController extends Controller
{
    /*登录*/
    public function login(Request $request){
        $response = new ResponseData();
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
        $response->data = $userInfo;
        return json_encode($response);
    }

    /*获取当局游戏信息*/
    public function getGameInfo(Request $request){
        $response = new ResponseData();
        $key = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key."|".$userId),true);
        if($userInfo == null){
            $response->resutt = false;
            $response->message = "请先登录";
            return json_encode($response);
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
        $response->data = $gameInfo;
        return json_encode($response);
    }

    /*获取用户信息*/
    public function getUserInfo(Request $request){
        $response = new ResponseData();
        $key = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = Redis::get($key."|".$userId);
        $response->data = $userInfo;
        return json_encode($response);
    }

    /*获取下注信息*/
    public function getBets(Request $request){
        $response = new ResponseData();
        $key = "BETS_INFO";       // 下注信息
        $userId = $request->session()->get('userId');
        $bets = json_decode(Redis::hget($key,$userId),true);
        if(blank($bets)){
            $constant = new Constant();
            $bets = $constant::BETS;
        }
        $response->data = $bets;
        return json_encode($response);
    }

    /*下注*/
    public function addBets(Request $request){
        $response = new ResponseData();
        $betNo = $request->input('betNo');
        $betVal = $request->input('betVal');
        if(blank($betNo) || blank($betVal) || $betNo < 1 || $betNo > 9 || $betVal < 1){
            $response->resutt = false;
            $response->message = "参数错误";
            return json_encode($response);
        }
        $key = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key),true);
        if(blank($gameInfo) || $gameInfo['status'] == 1){
            $response->resutt = false;
            $response->message = "该局已结算";
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
        if($betVal > $userInfo['chips']){
            $response->resutt = false;
            $response->message = "筹码不足";
            return json_encode($response);
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
        $response = new ResponseData();
        $double = $request->input('double');
        if(blank($double) || $double < 0 || $double > 1){
            $response->resutt = false;
            $response->message = "参数错误";
            return json_encode($response);
        }
        $key = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key),true);
        if(blank($gameInfo) || $gameInfo['status'] == 1){
            $response->resutt = false;
            $response->message = "该局已结算";
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
    }
}
