<?php

namespace App\Http\Controllers\admin;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\GameCards;

class AdminController extends Controller
{
    /*查询日期所有牌组列表*/
    public function getCardsInfo(Request $request){
        $date  = $request->input('date');       // 查询日期
        $gameCards = GameCards::where('id','like',$date.'%')->get();
        return $gameCards;
    }

    /*修改牌组*/
    public function putCardsInfo(Request $request){
        $gameId  = $request->input('gameId');
        $gameCards = GameCards::where('id',$gameId)->first();
        if($gameCards->status == 1){        // 已结算
            return "no time!";
        }
        $cards = $request->input('cards');
        $cards = json_decode($cards,true);
        for($i=0;$i<10;$i++){       // 判断数量
            if(count($cards[$i]) != 5){
                return "parameter error!";
            }
        }
        $allCards = array_collapse($cards);     // 判断重复
        if (count($allCards) != count(array_unique($allCards))) {
            return "parameter error!";
        }
        // 保存数据库
        $gameCards->cards = json_encode($cards);
        $gameCards->save();
        return $gameCards;
    }
}
