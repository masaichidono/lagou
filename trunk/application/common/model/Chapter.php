<?php
/**
 * Created by PhpStorm.
 * User: 剑正泣一
 * Date: 2018/7/5
 * Time: 0:16
 */

namespace app\common\model;


use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use QL\QueryList;
use think\Model;

class Chapter extends Model
{
    public  $error;
    private $url_prefix = "https://read.qidian.com/chapter/";

    /**
     * 爬取章节内容
     * @param $chapter_id
     * @return mixed
     */
    public function getChapter($chapter_id, $chapter_url){
        $url = $this->url_prefix . $chapter_url;
        $rule = array(
            'content' => array('.read-content', 'html')
        );
        $data = QueryList::get($url)
            ->rules($rule)
            ->range('.main-text-wrap')
            ->query()
            ->getData()
            ->all();
        $content  = $data[0];
        $file_name = $this->getName($chapter_id);
        //1.保存到到七牛云
        $temp_path = "static/chapter/{$file_name}.txt";
        $re = file_put_contents($temp_path, $content);
        if (!$re){
            $this->error = '保存章节失败';
            return false;
        }
        //保存到七牛云
        $path = $this->save_to_qiniu($temp_path, $file_name);
        if (!$path){
            return false;
        }
        //2.更新保存路径
        $re = $this->update(['save_path' => "{$path}.txt", 'update_time' => time(), 'status' => 1], ['id' => $chapter_id]);
        if (!$re){
            $this->error = '更新章节数据失败';
            return false;
        }
        return true;
    }

    public function save_to_qiniu($file_path, $file_name){
        $qiniu_config = config('qiniu_config');
        $auth = new Auth($qiniu_config['AccessKey'], $qiniu_config['SecretKey']);
        //生成token
        $token = $auth->uploadToken($qiniu_config['bucket']);
        //构建uploadManager对象
        $uploadMgr = new UploadManager();
        //保存到七牛云上的名称
        $save_path = 'lagou' . DS .'qidian' . DS . $file_name;
        $save_path = str_replace('\\', '/', $save_path);
        //开始保存
        list($ret, $err) = $uploadMgr->putFile($token, $save_path, $file_path);
        if ($err !== null) {
            $this->error = $err;
            return false;
        } else {
            return $ret['key'];
        }
    }

    /**
     * 获取章节保存的文件名
     * @param $chapter_id
     * @return string
     */
    protected function getName($chapter_id){
        return md5($chapter_id . 'masaichi' . rand(0, 9));
    }
}