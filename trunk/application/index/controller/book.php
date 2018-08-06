<?php
/**
 * Created by PhpStorm.
 * User: 剑正泣一
 * Date: 2018/4/27
 * Time: 20:57
 */

namespace app\index\controller;


use app\common\model\Chapter;
use app\common\model\QidianAllFree;
use app\common\model\QidianTaskBook;
use app\common\model\QidianTaskChapter;
use app\index\service\Curl;
use QL\QueryList;
use think\Controller;
use think\Cookie;

class Book extends Controller
{
    /**
     * 获取限时免费书本列表
     */
    public function get_book_list(){
        $rules = array(
            'qidian_id'    => array('.book-img-box a', 'data-bid'),
            'book_name'    => array('.book-mid-info h4 a', 'html'),
            'book_status'  => array('.book-mid-info .author span', 'html'),
        );
        $item = QueryList::getInstance();
        $data = $item->get('https://www.qidian.com/free')
            ->rules($rules)
            ->range('#limit-list>.book-img-text>ul>li')
            ->query()
            ->getData()
            ->all();
        //根据qidian_id去重
        $task_model = new QidianTaskBook();
        $haved_ids  = $task_model->where('qidian_id','IN', array_column($data, 'qidian_id'))
            ->column('qidian_id');
        foreach ($data as $key => $val){
            if (in_array($val['qidian_id'], $haved_ids)){
                unset($data[$key]);
            }
        }
        if (!empty($data)){
            $re = $task_model->saveAll($data);
            if (!$re){
                exit('fail to save');
            }
        }
        exit('success num:' . count($data));
    }

    /**
     * 获取全部免费作品每一页的链接
     */
    public function get_all_free(){
        //{"page":1,"all":47397}
        $file_path  = 'static/file/page.txt';
        $page_data  = json_decode(file_get_contents($file_path), true);
        $page       = $page_data['page'];
        $all_page   = $page_data['all_page'];
        //判断是否已经爬取完毕
        if ($all_page == $page){
            exit('all task done');
        }
        $end_page   = $page + 10;
        if ($end_page > $all_page){
            $end_page == $all_page;
        }
        $book_model = new \app\common\model\Book();
        //获取10页
        for ($i = $page; $i < $end_page; $i++){
            $book_model->getAllFreeBook($i);
        }
        $update = array('page' => $end_page, 'all_page' => $all_page);
        file_put_contents($file_path, json_encode($update, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 执行爬取书本任务
     */
    public function index(){
        //设置最大时间
        set_time_limit(180);
        $book_task_model = new QidianTaskBook();
        //1.获取未爬取的前20条数据
        $book_list = $book_task_model->where('status', 0)
            ->order('id ASC')
            ->limit(5)
            ->select();
        if ($book_list->isEmpty()){
            exit('there is nothing to do');
        }

        $book_list    = $book_list->toArray();
        $count = 0;
        //开始爬取
        $book_model = new \app\common\model\Book();
        foreach ($book_list as $key => $val){
            $book_model->startTrans();
            $re = $book_model->getOneBook($val['qidian_id'], $val['book_status']);
            if (!$re){
                //更新
                $re = $book_task_model->update(['task_info' => $book_model->getError(), 'status' => 2], ['id' => $val['id']]);
            }else{
                //成功
                $re = $book_task_model->update(['status' => 1], ['id' => $val['id']]);
                $count++;
            }
            if (!$re){
                $book_model->rollback();
                echo '更新失败';
                continue;
            }
            $book_model->commit();
        }
        echo "SUCCESS num:{$count}";
    }

    /**
     * 执行爬取书本章节任务
     */
    public function get_chapter(){
        set_time_limit(180);
        //1.每次爬取10个章节
        $chapter_model = new Chapter();
        $chapter_list = $chapter_model->where('status', 0)
            ->order('id ASC')
            ->limit(20)
            ->select();
        if ($chapter_list->isEmpty()){
            exit('there is nothing to do');
        }
        $chapter_list  = $chapter_list->toArray();
        $error_count   = 0;
        $success_count = 0;
        //2.将每个章节保存在本地
        foreach ($chapter_list as $key => $val){
            $re = $chapter_model->getChapter($val['id'], $val['url']);
            if(!$re){
                $error_count ++;
            }else{
                $success_count ++;
            }
        }
        echo "ERROR ({$error_count})  SUCCESS ({$success_count})";
    }

    //检查连载中的书籍，是否免费，是否需要更新章节
    public function update_chapter(){

    }
}