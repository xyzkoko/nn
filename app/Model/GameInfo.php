<?php

namespace App\Model;

class GameInfo
{
    public $gameId;
    public $position = [["nick"=>"庄家","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0],
        ["nick"=>"","icon"=>"","pot"=>0,"cards"=>"","point"=>0]];
    public $status = 0;
    public $startTime;
    public $nowTime;
}
