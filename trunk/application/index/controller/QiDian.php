<?php
/**
 * Created by PhpStorm.
 * User: 剑正泣一
 * Date: 2018/6/27
 * Time: 22:45
 */

namespace app\index\controller;


use app\common\model\Chapter;
use app\index\service\Curl;
use QL\QueryList;
use think\Controller;

class QiDian extends Controller
{
    //起点首页
    private $url           = 'https://www.qidian.com';
    private $free_url      = '';
    private $day_free_url  = '';
    private $error         = '';

    public function _initialize()
    {
        $this->free_url     = $this->url . '/free/all';
        $this->day_free_url = $this->url . '/free';
    }

    /**
     * 测试phplist
     */
    public function test(){
        $rules = array(
            'link' => array('.book-img-box a', 'href'),
            'qidian_id' => array('.book-img-box a', 'data-bid'),
            'img' => array('.book-img-box a img', 'src'),
            'score' => array('.book-img-box .score', 'html'),
            'title' => array('.book-mid-info h4 a', 'html'),
            'author' => array('.book-mid-info .author .name', 'html'),
            'type' => array('.book-mid-info .author a:nth-child(2)', 'html'),
            'status' => array('.book-mid-info .author span', 'html'),
//            'intro' => array('.intro', 'html'),
        );
        $item = QueryList::getInstance();
        $data = $item->get('https://www.qidian.com/free')
            ->rules($rules)
            ->range('#limit-list>.book-img-text>ul>li')
            ->query()
            ->getData();

        dump($data->all());
//        dump($item->getHtml());
//        $data = QueryList::html($html)
//            ->rules($rules)
//            ->query()
//            ->getData();
//        print_r($data->all());
    }

    /**
     * 获取书本详情
     */
    public function info(){
        $curl = new Curl();
        $curl->get('https://book.qidian.com/info/3621897');
        $response_headers = implode('', $curl->response_headers);
        //获取token
        preg_match_all('#_csrfToken=(.*)expire#Uis', $response_headers, $match);
        if (!isset($match[1])){
            exit('fail to get _csrfToken');
        }
        $_csrfToken = trim($match[1][0], '; ');
        $html = QueryList::get('https://book.qidian.com/info/3621897')->getHtml();
        $book_data = $this->getBookData($html);
        //获取作品详情
        $intro_data = $this->getIntro($html);
        $book_id = $book_data['book_id'];
        //获取目录
        $cf_data = $this->getCatagory($book_id, $_csrfToken);
        if (!$cf_data){
            exit($this->error);
        }
        dump($book_data);
        dump($intro_data);
        dump($cf_data);
    }
}