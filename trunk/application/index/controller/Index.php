<?php
namespace app\index\controller;

use app\common\model\Log;
use app\index\service\Lagou;
use app\index\service\Mail;
use think\Config;
use think\Controller;
use think\Exception;
use think\Session;

class Index extends Controller
{
    public function index()
    {
        $lagou    = new Lagou();
        $data     = $lagou->steal();
        if ($data === false) {
            echo $lagou->getError();
            exit;
        }
        //保存数据
        foreach ($data as $type => $item) {
            $re = $this->saveData( $type, $item );
            if (!$re) {
                echo '$type保存失败';
            }
        }
        echo '保存成功';
    }

    /**
     * 保存数据
     * @param $type
     * @param $data
     * @return bool
     * @throws \Exception
     */
    protected function saveData ( $type , $data ) {
        if ($type == 'offcn') {
            $model = new Log();
            $repeat_key = 'title';
        } else {
            $model = new \app\common\model\Lagou();
            $repeat_key = 'company_name';
        }
        //获取最新的几条数据
        $last_arr  = $model->order('id DESC')->field($repeat_key)->column($repeat_key);
        $save_data = [];
        //排除重复的数据
        foreach ($data as $key => $val) {
            if (in_array($val[$repeat_key], $last_arr)) {
                break;
            }
            $save_data[] = $val;
        }
        if ($save_data) {
            //反转，将最新的数据放置在最后
            $save_data = array_reverse($save_data);
            //保存
            $re = $model->saveAll($save_data);
            if (!$re) {
                $this->error = '保存失败';
                return false;
            }
        }
        return true;
    }

    /**
     * 发送邮件函数
     */
    public function send_email () {
        $host         = config('email.host');
        $username     = config('email.username');
        $pwd          = config('email.pwd');
        $steal_items  = config('steal_url');
        $to_users     = config('email.to_user');
        foreach ($steal_items as $item => $val) {
            $temp = 'send_' . $item;
            $this->$temp($host, $username, $pwd, $to_users[$item]);
        }
        echo '发送成功';
    }

    /**
     * 发送offcn邮件
     * @param $host
     * @param $username
     * @param $pwd
     * @param $to_user
     * @return bool
     */
    protected function send_offcn ( $host, $username, $pwd, $to_user ) {
        $log_model = new Log();
        $list      = $log_model->where('status', 0)->order('id Desc')->limit(10)->select();
        if ($list) {
            $sended_ids = [];
            $list = $list->toArray();
            foreach ($list as $key => $info) {
                $title   = '【offcn】' . $info['title'];
                $content = '<a href="' .$info['href'] .'">原文链接</a><br />' . $info['content'];
                $mail    = new Mail( $host, $username, $pwd, $to_user , $title, $content);
                if ($mail) {
                    $sended_ids[] = $info['id'];
                }
            }
            if ($sended_ids) {
                //修改状态
                $log_model->where('id', 'IN', $sended_ids)->update(['status' => 1]);
            }
        }
        return true;
    }

    /**
     * 发送拉钩邮件
     * @param $host
     * @param $username
     * @param $pwd
     * @param $to_user
     * @return bool
     */
    protected function send_lagou ( $host, $username, $pwd, $to_user ) {
        $lagou_model = new \app\common\model\Lagou();
        $list      = $lagou_model->where('status', 0)->order('id Desc')->limit(10)->select();
        if ($list) {
            $sended_ids = [];
            $list = $list->toArray();
            $detail_url = Config::get('lagou_detail');
            foreach ($list as $key => $info) {
                $title   = '【拉钩' . $info['salary'] . '】' . $info['company_name'];
                $content = '<a href="' .$detail_url . $info['position_id'] .'.html">原文链接</a><br />' . $info['detail'];
                $mail    = new Mail( $host, $username, $pwd, $to_user , $title, $content);
                if ($mail) {
                    $sended_ids[] = $info['id'];
                }
            }
            if ($sended_ids) {
                //修改状态
                $lagou_model->where('id', 'IN', $sended_ids)->update(['status' => 1]);
            }
        }
        return true;
    }
}