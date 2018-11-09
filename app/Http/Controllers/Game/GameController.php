<?php

namespace App\Http\Controllers\game;

use App\model\UserInfo;
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
        $gameInfo->status = 1;      // TODO 方便下注测试原来是0
        $gameInfo->position = $this->getPosition($gameInfo->position);     // 随机获取玩家头像
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
        $closeTime = strtotime($data) + 105;
        for($i = 1;$i <= 480;$i++){
            $gameCards = new GameCards;
            $gameCards->id = $data.'|'.sprintf("%03d", $i);       // 补齐3位;
            $carda = $constant::CARDINDEXS;      // 获取总牌组
            shuffle($carda);     // 随机
            $carda = array_chunk($carda,5);       // 分割
            $carda = array_slice($carda,0,10);        // 取前十个
            $gameCards->cards = $this->sortCards($carda);
            $gameCards->close_time = $closeTime * 1000;
            $gameCards->save();
            $closeTime += 180;
        }
        echo 'success';
    }

    /*生成今天的牌组*/
    public function addTodayGameList()
    {
        $constant = new Constant();
        $data = date("Ymd");
        $closeTime = strtotime($data) + 105;
        for($i = 1;$i <= 480;$i++){
            $gameCards = new GameCards;
            $gameCards->id = $data.'|'.sprintf("%03d", $i);       // 补齐3位;
            $cards = $constant::CARDINDEXS;      // 获取总牌组
            shuffle($cards);     // 随机
            $cards = array_chunk($cards,5);       // 分割
            $cards = array_slice($cards,0,10);        // 取前十个
            $gameCards->cards = $this->sortCards($cards);
            $gameCards->close_time = $closeTime * 1000;
            $gameCards->save();
            $closeTime += 180;
        }
        echo 'success';
    }

    /*把牌按照3|2排序*/
    public static function sortCards($cards){
        $sortCards = array();
        for($x=0;$x<count($cards);$x++){
            $card = $cards[$x];
            $count = count($card);
            for($i=0;$i<$count;$i++){
                $point1 = $card[$i]%100>10?10:$card[$i];
                for($j=$i+1;$j<$count;$j++){
                    $point2 = $card[$j]%100>10?10:$card[$j];
                    for($k=$j+1;$k<$count;$k++){
                        $point3 = $card[$k]%100>10?10:$card[$k];
                        if(($point1 + $point2 + $point3)%10 == 0){
                            $sortCard = array($card[$i],$card[$j],$card[$k]);
                            $sortCards[] = array_merge($sortCard, array_except($card, [$i,$j,$k]));
                            break 3;
                        }
                    }
                }
                if($i == $count - 1){
                    $sortCards[] = $card;
                }
            }
        }
        return json_encode($sortCards);
    }

    /*算点*/
    private function getPoint($card){
        $count = count($card);
        for($i=0;$i<$count;$i++){
            $point1 = $card[$i]%100>10?10:$card[$i];
            for($j=$i+1;$j<$count;$j++){
                $point2 = $card[$j]%100>10?10:$card[$j];
                for($k=$j+1;$k<$count;$k++){
                    $point3 = $card[$k]%100>10?10:$card[$k];
                    if(($point1 + $point2 + $point3)%10 == 0){
                        $card = array_except($card, [$i,$j,$k]);
                        return array_sum($card)%10==0?10:array_sum($card)%10;
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
            // 统计游戏信息
            $bankerResult += $result;
            $pot += $betnum;
            // 保存玩家下注信息
            $value["result"] = $result;
            Redis::hset($key,$userId,json_encode($value));
            $userbet = new UserBet();
            $userbet->user_id = $userId;
            $userbet->game_id = $gameInfo->gameId;
            $userbet->bets = json_encode($value);
            $userbet->betnum = $betnum;
            $userbet->result = $result;
            $userbet->save();
            // 更新玩家信息
            $key2 = "USER_INFO";       // 玩家信息
            $userInfo = json_decode(Redis::get($key2."|".$userId),true);
            $userInfo["chips"] += $betnum + $result;        // 返还筹码
            Redis::set($key2."|".$userId, json_encode($userInfo));
        }
        $response['bankerResult'] = $bankerResult;
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

    /*随机获取玩家头像信息*/
    private function getPosition($position){
        $userInfo =  UserInfo::where('headimgurl','<>', 'headimgurl')->inRandomOrder()->take(9) ->get()->toArray();
        for($i = 1;$i<count($position);$i++){
            $position[$i]['nickname'] = $userInfo[$i-1]["nickname"];
            $position[$i]['headimgurl'] = $userInfo[$i-1]["headimgurl"];
        }
        return $position;
    }


}
