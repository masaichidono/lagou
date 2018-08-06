<?php
/**
 * Created by PhpStorm.
 * User: 33515
 * Date: 2018/8/6
 * Time: 22:35
 */

namespace app\index\controller;


use app\common\model\Chapter;
use think\Controller;

class Test extends Controller
{
    public function test (){
        $chapter_model = new Chapter();
        $chapter_model->save_to_qiniu('static/chapter/3f8e942e00d0861f46143801d3e06cae.txt', '3f8e942e00d0861f46143801d3e06cae.txt');
    }
}