<?php

namespace App\Http\Controllers\game;

use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use App\Http\Controllers\user\UserController;
use App\Model\GameCards;
use App\Model\GameInfo;
use App\Model\Constant;
use App\Model\UserBet;

class GameController extends Controller
{
    /*每三分钟运行一次进行游戏*/
    public function startGame(){
        set_time_limit(180);
        $key = "GAME_ID";       // 当局ID
        $gameId = Redis::get($key);
        if(blank($gameId)){
            $num = 1;
        }else{
            $pieces = explode("|", $gameId);
            if($pieces[1] == 480 || $pieces[0] != date('Ymd')){
                $num = 1;
            }else{
                $num = $pieces[1] + 1;
            }
        }
        $num = sprintf("%03d", $num);       // 补齐3位
        $gameId = date('Ymd').'|'.$num;
        Redis::set($key, $gameId);      // 更新当局ID
        // 准别阶段
        $key2 = "GAME_INFO";       // 当局信息
        $gameInfo = new GameInfo;
        $gameInfo->gameId = $gameId;
        $gameInfo->startTime = UserController::getMillisecond();
        Redis::set($key2, json_encode($gameInfo));            // 更新Redis
        $key3 = "BETS_INFO";       // 下注信息
        Redis::del($key3);
        sleep(105);      // 等待
        // 下注阶段
        $gameInfo->status = 1;
        Redis::set($key2, json_encode($gameInfo));            // 更新Redis
        sleep(5);      // 等待
        // 结算阶段
        $gameCards = GameCards::find($gameId);
        $cards = json_decode($gameCards["cards"],true);
        for($i=0;$i<count($gameInfo->position);$i++){
            $gameInfo->position[$i]["cards"] = json_encode($cards[$i]);
            $gameInfo->position[$i]["point"] = $this->getPoint($cards[$i]);
        }
        $gameInfo->status = 2;
        Redis::set($key2, json_encode($gameInfo));      // 更新Redis
        $result = $this->result($gameInfo);     // 总收入
        $gameCards->status = 2;
        $gameCards->pot = $result['pot'];      // 总下注数
        $gameCards->result = $result['bankerResult'];        // 庄家输赢
        $gameCards->save();
        return "success";
    }

    /*每天早上生成次日的牌组*/
    public function addGameList()
    {
        $constant = new Constant();
        $data = date("Ymd",strtotime("+1 day"));
        for($i = 1;$i <= 480;$i++){
            $gameCards = new GameCards;
            $gameCards->id = $data.'|'.sprintf("%03d", $i);       // 补齐3位;
            $cardIndexs = $constant::CARDINDEXS;      // 获取总牌组
            shuffle($cardIndexs);     // 随机
            $cardIndexs = array_chunk($cardIndexs,5);       // 分割
            $cardIndexs = array_slice($cardIndexs,0,10);        // 取前十个
            $gameCards->cards = json_encode($cardIndexs);
            $gameCards->save();
        }
        echo 'success';
    }

    /*生成今天的牌组*/
    public function addTodayGameList()
    {
        $constant = new Constant();
        $data = date("Ymd");
        for($i = 1;$i <= 480;$i++){
            $gameCards = new GameCards;
            $gameCards->id = $data.'|'.sprintf("%03d", $i);       // 补齐3位;
            $cardIndexs = $constant::CARDINDEXS;      // 获取总牌组
            shuffle($cardIndexs);     // 随机
            $cardIndexs = array_chunk($cardIndexs,5);       // 分割
            $cardIndexs = array_slice($cardIndexs,0,10);        // 取前十个
            $gameCards->cards = json_encode($cardIndexs);
            $gameCards->save();
        }
        echo 'success';
    }

    /*算点*/
    private function getPoint($cards){
        for($i=0;$i<count($cards);$i++) {
            $cards[$i] = $cards[$i]%10 > 10?10:$cards[$i]%10;
        }
        for($i=0;$i<count($cards);$i++){
            for($j=$i+1;$j<count($cards);$j++){
                for($k=$j+1;$k<count($cards);$k++){
                    if(($cards[$i]+$cards[$j]+$cards[$k])%10 == 0){
                        $cards = array_except($cards, [$i,$j,$k]);
                        return array_sum($cards)%10==0?10:array_sum($cards)%10;
                    }
                }
            }
        }
        return 0;
    }

    /*结算*/
    private function result($gameInfo){
        $key = "BETS_INFO";       // 玩家下注信息
        $allBets = Redis::hgetall($key);
        $bankerPoint = $gameInfo->position[0]["point"];     // 庄家点数
        $bankerResult = 0;      // 庄家输赢
        $pot = 0;       // 总下注数
        foreach($allBets as $userId=>$value){
            $result = 0;        // 玩家输赢
            $value = json_decode($value,true);
            $double = $value["double"];
            $betnum = 0;      // 玩家下注数
            for($i=1;$i<=9;$i++){
                $playerPoint = $gameInfo->position[$i]["point"];
                if($value[$i] == 0){
                    continue;
                }
                $betnum += $value[$i];
                // 比大小
                if($playerPoint > $bankerPoint){      // win
                    $result += $this->getResult($playerPoint,$double,$value[$i]);
                }elseif ($playerPoint < $bankerPoint){        // lose
                    $result -= $this->getResult($bankerPoint,$double,$value[$i]);
                }
            }
            $pot += $betnum;
            if($result > 0){        // 玩家赢了
                $bankerResult -= $result;
            }elseif ($result < 0){      // 玩家输了
                $bankerResult += abs($result);
            }
            $value["result"] = $result;
            Redis::hset($key,$userId,json_encode($value));
            // 更新用户信息
            $key2 = "USER_INFO";       // 玩家信息
            $userInfo = json_decode(Redis::get($key2."|".$userId),true);
            if($result<0){      // 多扣输的筹码
                $result = $betnum - abs($result);
            }elseif($result>0){      // 赢了返还筹码
                $result = $betnum + $result;
            }
            $userInfo["chips"] += $result;
            Redis::set($key2."|".$userId, json_encode($userInfo));
            // 保存玩家下注数据
            $userbet = new UserBet();
            $userbet->user_id = $userId;
            $userbet->game_id = $gameInfo->gameId;
            $userbet->bets = json_encode($value);
            $userbet->betnum = $betnum;
            $userbet->result = $result;
            $userbet->save();
        }
        $response['bankerResult'] =$bankerResult;
        $response['pot'] = $pot;
        return $response;
    }

    /*算分*/
    private function getResult($bigPoint, $double, $bets){
        if($double == 1){
            if($bigPoint == 8){
                return $bets * 2;
            }
            if($bigPoint == 9){
                return $bets * 3;
            }
            if($bigPoint == 10){
                return $bets * 4;
            }
            return $bets;
        }
        return $bets;
    }
}
