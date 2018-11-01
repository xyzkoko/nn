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
    public function login(Request $request){
        $response = new ResponseData();
        // 测试登录
        $key = "USER_INFO";
        $userId = $request->session()->get('userId');
        if(blank($userId)) {     // 新用户
            $userInfo = new UserInfo();
            $userInfo->openid = mt_rand(100000,999999);
            $userInfo->nickname = "nickname";
            $userInfo->headimgurl = "headimgurl";
            $userInfo->sex = "0";
            $userInfo->province = "province";
            $userInfo->city = "city";
            $userInfo->chips = 10000;
            $userInfo->save();
            $request->session()->put('userId', $userInfo->id);
            Redis::set($key."|".$userInfo->id, json_encode($userInfo));
        }else{
            $userInfo = json_decode(Redis::get($key."|".$userId),true);
            if(blank($userInfo)){
                $userInfo = UserInfo::where('id', $userId)->first();
                if(blank($userInfo)){
                    $request->session()->flush();
                    return $this->login($request);
                }
                Redis::set($key."|".$userInfo->id, json_encode($userInfo));
            }
        }
        $response->data = $userInfo;
        return json_encode($response);exit;
        // 微信登录
        $key = "USER_INFO";
        $userId = $request->session()->get('userId');
        if(blank($userId)){     // 新用户
            $weiChatController = new WeiChatController();
            $res = $weiChatController->oauth2($request);
            if(!blank($res['errmsg'])){
                $response->result = false;
                $response->message = $res['errmsg'];
                return json_encode($response);
            }
            $openid = $res['openid'];
            $userInfo = UserInfo::where('openid', $openid)->first();
            if(blank($userInfo)) {
                $res = $weiChatController->getUserInfo($openid,$res['access_token']);
                if(!blank($res['errmsg'])){
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
                $userInfo->chips = 10000;
                $userInfo->save();
            }
            $request->session()->put('userId', $userInfo->id);
            Redis::set($key."|".$userInfo->id, json_encode($userInfo));
        }else{
            $userInfo = json_decode(Redis::get($key."|".$userId),true);
            if(blank($userInfo)){
                $userInfo = UserInfo::where('id', $userId)->first();
                if(blank($userInfo)){
                    $request->session()->flush();
                    return $this->login($request);
                }
                Redis::set($key."|".$userInfo->id, json_encode($userInfo));
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
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $key2 = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key2),true);
        if($gameInfo == null){
            $response->result = false;
            $response->message = "牌局错误";
            return json_encode($response);
        }
        for($i=0;$i<count($gameInfo["position"]);$i++){
            if($gameInfo["position"][$i]["nickname"] == $userInfo["nickname"]){
                break;
            }
            if(blank($gameInfo["position"][$i]["nickname"])){
                $gameInfo["position"][$i]["nickname"] = $userInfo["nickname"];
                $gameInfo["position"][$i]["headimgurl"] = $userInfo["headimgurl"];
                break;
            }
        }
        $gameInfo["nowTime"] = $this->getMillisecond();
        Redis::set($key2, json_encode($gameInfo));
        $response->data = $gameInfo;
        return json_encode($response);
    }

    /*获取用户信息*/
    public function getUserInfo(Request $request){
        $response = new ResponseData();
        $key = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key."|".$userId),true);
        if($userInfo == null){
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
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
        $double = $request->input('double');
        if(!is_array($betNo) || !is_array($betVal) || blank($double) || count($betNo) != count($betVal) || 0>$double || 1<$double){
            $response->result = false;
            $response->message = "参数错误";
            return json_encode($response);
        }
        $key = "GAME_INFO";       // 当局信息
        $gameInfo = json_decode(Redis::get($key),true);
        if(blank($gameInfo) || $gameInfo['status'] != 1){
            $response->result = false;
            $response->message = "非下注时间";
            return json_encode($response);
        }
        $key2 = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key2."|".$userId),true);
        if($userInfo == null){
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        // 保存下注信息
        $key3 = "BETS_INFO";       // 玩家信息
        $bets = json_decode(Redis::hget($key3,$userId),true);
        if(blank($bets)){
            $constant = new Constant();
            $bets = $constant::BETS;
        }else{      // 只能下一次注
            return $this->getBets($request);
        }
        $allBetVal = 0;
        for($i=0;$i<count($betNo);$i++){
            if(1>$betNo[$i] || 9<$betNo[$i] || !is_numeric($betVal[$i])){
                $response->result = false;
                $response->message = "参数错误";
                return json_encode($response);
            }
            $allBetVal += $betVal[$i];
            $bets[$betNo[$i]] += $betVal[$i];
        }
        $bets["double"] = $double;
        if($double == 1 && ($allBetVal*4 > $userInfo['chips'])){
            $response->result = false;
            $response->message = "筹码不足";
            return json_encode($response);
        }elseif($allBetVal > $userInfo['chips']){
            $response->result = false;
            $response->message = "筹码不足";
            return json_encode($response);
        }
        Redis::hset($key3,$userId,json_encode($bets));
        $userInfo["chips"] -= $allBetVal;
        Redis::set($key2."|".$userId,json_encode($userInfo));
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
    public function getTime(){
        $response = new ResponseData();
        $data["time"] =  $this->getMillisecond();
        $response->data = $data;
        return json_encode($response);
    }

    // 毫秒级时间戳
    public static function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }

    /*获取历史下注信息*/
    public static function getHistoryBets(Request $request) {
        $response = new ResponseData();
        $startDate  = $request->input('startDate');       // 查询开始日期|001
        $endDate  = $request->input('endDate');       // 查询结束日期|460
        $key = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key."|".$userId),true);
        if($userInfo == null){
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $userBets = UserBet::whereBetween('game_id', [$startDate, $endDate])->where('user_id', $userId)->get();
        $response->data = $userBets;
        return json_encode($response);
    }

    /*获取某一天的投注信息*/
    public static function getDateBets(Request $request) {
        $response = new ResponseData();
        $date  = $request->input('date');       // 查询开始日期
        $key = "USER_INFO";       // 玩家信息
        $userId = $request->session()->get('userId');
        $userInfo = json_decode(Redis::get($key."|".$userId),true);
        if($userInfo == null){
            $response->result = false;
            $response->message = "请先登录";
            return json_encode($response);
        }
        $userBets = UserBet::where('user_id',$userId)->where('game_id','like',$date.'%')->get();
        $data['date'] = $date;
        $data['betnum'] = $userBets->sum('betnum');
        $data['result'] = $userBets->sum('result');
        $response->data = $data;
        return json_encode($response);
    }
}
