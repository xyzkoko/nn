<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['namespace' => 'Game'], function(){
    Route::post('/game/start', 'GameController@startGame');
    Route::post('/game/add', 'GameController@addGameList');
    Route::get('/game/add', 'GameController@addTodayGameList');
});

Route::group(['namespace' => 'User'], function(){
    Route::post('/user/login', 'UserController@login');
    Route::get('/user/gameInfo', 'UserController@getGameInfo');
    Route::get('/user/userInfo', 'UserController@getUserInfo');
    Route::post('/user/bets', 'UserController@addBets');
    Route::post('/user/double', 'UserController@putDouble');
    Route::get('/user/bets', 'UserController@getBets');
});

Route::group(['namespace' => 'Admin'], function(){
    Route::post('/admin/getCards', 'AdminController@getCardsInfo');
    Route::post('/admin/putCards', 'AdminController@putCardsInfo');
});
