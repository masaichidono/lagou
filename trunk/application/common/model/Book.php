<?php
/**
 * Created by PhpStorm.
 * User: 33515
 * Date: 2018/8/5
 * Time: 0:23
 */

namespace app\common\model;


use app\index\service\Curl;
use QL\QueryList;
use think\Model;

class Book extends Model
{
    protected $error;

    /**
     * 爬取一本书的信息
     * @param $book_id
     * @return bool
     * @throws \ErrorException
     * @throws \think\exception\PDOException
     */
    public function getOneBook($book_id, $book_status){
        $url = "https://book.qidian.com/info/{$book_id}";
        $curl = new Curl();
        $curl->get($url);
        $response_headers = implode('', $curl->response_headers);
        //获取token
        preg_match_all('#_csrfToken=(.*)expire#Uis', $response_headers, $match);
        if (!isset($match[1])){
            $this->error = 'fail to get _csrToken';
            return false;
        }
        $_csrfToken = trim($match[1][0], '; ');
        $html       = QueryList::get($url)->getHtml();
        $book_data  = $this->getBookData($html);
        //获取作品详情
        $intro_data = $this->getIntro($html);
        //获取目录
        $cf_data = $this->getCatagory($book_id, $_csrfToken);
        if (!$cf_data){
            return false;
        }
        //保存
        //1.保存书本基础信息
        $book_data = array(
            'qidian_id'         => $book_id,
            'book_status'       => $book_status,
            'book_name'         => $book_data['name'],
            'book_intro'        => trim($intro_data),
            'book_img'          => $book_data['img'],
            'book_qidian_score' => $book_data['score1'] . '.' . $book_data['score2'],
            'book_author'       => $book_data['author'],
            'create_time'       => time(),
        );
        $id = $this->insertGetId($book_data);
        if (!$id){
            $this->error = '保存文章信息失败';
            return false;
        }
        //2.添加章节
        $chapter_data = [];
        foreach ($cf_data as $key => $val){
            $chapter_data[] = array(
                'book_id'      => $id,
                'url'          => $val['cU'],
                'sort'         => $val['uuid'],
                'title'        => $val['cN'],
                'publish_time' => $val['uT'],
                'create_time'  => time(),
                'update_time'  => time()
            );
        }
        $chapter_model = new Chapter();
        $re = $chapter_model->saveAll($chapter_data);
        if (!$re){
            $this->error = '保存章节失败';
            return false;
        }
        //保存成功
        return true;
    }

    /**
     * 获取文章信息
     * @param $html
     * @return mixed
     */
    protected function  getBookData($html){
        //作品信息规则
        $book_rule = array(
            'book_id'     => array('#readBtn', 'data-bid'),
            'img'         => array('.book-img img', 'src'),
            'name'        => array('.book-info h1 em', 'html'),
            'short_intro' => array('.book-info .intro', 'html'),
            'type'        => array('.tag a', 'html'),
            'score1'      => array('#score1', 'html'),
            'score2'      => array('#score2', 'html'),
            'author'      => array('.writer', 'html')
        );
        $book_data = QueryList::html($html)
            ->rules($book_rule)
            ->range('.book-information')
            ->query()
            ->getData();
        return $book_data->all()[0];
    }

    /**
     * 获取作品详情
     * @param $html
     * @return mixed
     */
    protected function getIntro($html){
        //作品详情
        $intro_rule = array(
            'intro' => array('.book-intro p', 'html')
        );
        $intro_data = QueryList::html($html)
            ->rules($intro_rule)
            ->range('.book-info-detail')
            ->query()
            ->getData()
            ->all();
        if (!isset($intro_data[0]) || !isset($intro_data[0]['intro'])){
            return '';
        }
        return trim($intro_data[0]['intro'], '　　 ');
    }

    /**
     * 获取章节目录
     * @param $book_id
     * @param $token
     * @return bool|mixed
     * @throws \ErrorException
     */
    protected function getCatagory($book_id, $token){
        $url = "https://book.qidian.com/ajax/book/category?_csrfToken={$token}&bookId={$book_id}";
        $curl = new Curl();
        $curl->get($url);
        if ($curl->error){
            $this->error = '获取失败';
            return false;
        }
        $data = json_decode($curl->response, true);
        if (!$data || !isset($data['data']) || !isset($data['data']['vs'])){
            $this->error = '数据返回格式有误';
            return false;
        }
        $vs = $data['data']['vs'];
        $new_data = [];
        foreach ($vs as $key => $val){
            $new_data = array_merge($new_data, $val['cs']);
        }
        return $new_data;
    }

    /**
     * 获取所有免费书本
     */
    public function getAllFreeBook($page){
        $url = "https://www.qidian.com/free/all?orderId=&vip=hidden&style=1&pageSize=20&siteid=1&pubflag=0&hiddenField=1&page={$page}";
        $rule = array(
            'qidian_id'   => array('.book-img-box>a','data-bid'),
            'book_name'   => array('.book-mid-info>h4>a', 'html'),
            'book_status' => array('.book-mid-info .author span:last-child', 'html')
        );
        $data = QueryList::get($url)
            ->rules($rule)
            ->range('.all-book-list ul>li')
            ->query()
            ->getData()
            ->all();
        if (!empty($data)){
            $task_model = new QidianTaskBook();
            //去重
            $haved_ids = $task_model->where('qidian_id', 'IN', array_column($data, 'qidian_id'))
                ->column('qidian_id');
            foreach ($data as $key => $val){
                if (in_array($val['qidian_id'], $haved_ids)){
                    unset($data[$key]);
                }
            }
            if ($data){
                $re = $task_model->saveAll($data);
                if(!$re){
                    $this->error = '保存失败';
                    return false;
                }
            }
            return true;
        }else{
            $this->error = '没有获取到数据';
            return false;
        }
    }
}