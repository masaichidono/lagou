<?php
/**
 * Created by PhpStorm.
 * User: 剑正泣一
 * Date: 2018/3/31
 * Time: 14:54
 */

namespace app\index\service;


use app\index\service\Curl;
use think\Config;
use think\Cookie;
use think\image\Exception;
use think\Session;

class Lagou
{
    protected $error = null;
    protected $error_message = null;
    protected $error_code = null;


    public function getError()
    {
        return $this->error;
    }

    /**
     * 爬取数据
     * @return array
     */
    public function steal()
    {
        $steal_web = Config::get('steal_url');
        $data = [];
        foreach ($steal_web as $key => $url) {
            if (!isset($data[$key])) {
                $data[$key] = [];
            }
            //测试，跳过offcn数据
            $tmp_data = $this->$key($url);
            if ($tmp_data) {
                $data[$key] = array_merge($data[$key], $tmp_data);
            } else {
                echo $this->error;
            }
        }
        return $data;
    }

    /**
     * 获取中公教育医疗卫生招聘
     * @param $url
     * @return array|bool
     * @throws \ErrorException
     */
    public function offcn( $url )
    {
        $curl = new Curl();
        $curl->setUserAgent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        $curl->get($url);
        if ($curl->error) {
            $this->error = $curl->error_message . '[' . $curl->error_code . ']';
            return false;
        }
        $data = $curl->response;
        //原网页为gb2312格式，需要转换为utf8
        $data = mb_convert_encoding($data, 'utf8', 'gb2312');
        preg_match_all('#<ul class=\"list\">(.+)</ul>#Uis', $data, $match_data);
        if (!isset($match_data[1])) {
            $this->error = '没有匹配的数据';
            return false;
        }
        $data = trim($match_data[1][0], '\t\n\r ');
        //去掉空格等字符
        str_replace([' ', '\n', '\t', '\r'], '', $data);
        str_replace(' ', '', $data);
        try {
            preg_match_all('#<li>(.+)</li>#Uis', $data, $match);
        } catch (\think\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
        if (!isset($match[1])) {
            $this->error = '没有匹配的数据2';
            return false;
        }
        $data = $match[1];
        $final_data = [];
        foreach ($data as $key => $val) {
            $temp_data = array(
                'date' => '',
                'href' => '',
                'title' => '',
                'key' => 'offcn',
                'status' => 0,
            );
            preg_match_all('#<span>(.+)</span>#Uis', $val, $tmp_match);
            if (isset($tmp_match[1])) {
                $temp_data['date'] = strip_tags($tmp_match[1][0]);
            }
            //获取链接
            preg_match_all('#</em>　<a href=\"(.+)\" title\=\"(.+)\" target#Uis', $val, $tmp_match);
            if (isset($tmp_match[1]) && isset($tmp_match[2])) {
                $temp_data['href'] = $tmp_match[1][0];
                $temp_data['title'] = $tmp_match[2][0];
            }
            $final_data[] = $temp_data;
        }
        $final_data = array_slice($final_data, 0, 5);
        foreach ($final_data as $key => $val) {
            $val['content'] = $this->getContent($val['href']);
            $final_data[$key] = $val;
            break;
        };
        return $final_data;
    }

    /**
     * 获取拉钩网的php招聘信息数据
     */
    public function lagou ( $url ) {
        $curl = new Curl();
        $curl->setUserAgent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        $data = array(
            'first' => true,
            'pn'    => 1,
            'kd'    => 'PHP',
        );
        $curl->setHeader('Origin', 'https://www.lagou.com');
        $curl->setHeader('Host', 'www.lagou.com');
        $curl->setHeader('Referer', 'https://www.lagou.com/jobs/list_PHP?gj=3%E5%B9%B4%E5%8F%8A%E4%BB%A5%E4%B8%8B&px=new&city=%E5%B9%BF%E5%B7%9E');
        $curl->post($url, $data);
        if ($curl->error) {
            $this->error = $curl->error_message . '[' . $curl->error_code . ']';
            return false;
        }
        $data = json_decode($curl->response, true);
        if (!$data || !isset($data['success']) ) {
            echo $curl->response;
            $this->error = '获取拉钩数据有误';
            return false;
        }
        if (!isset($data['content'])) {
            $this->error = '获取拉钩数据频繁';
            return false;
        }
        //只获取最新的5个
        $new_data   = array_slice($data['content']['positionResult']['result'], 0, 5);
        $re_data    = [];
        $lagou_keys = Config::get('lagou_keys');
        foreach ($new_data as $key => $val) {
            $temp = array();
            foreach ($lagou_keys as $lagou_key => $lagou_val) {
                if ($lagou_key == 'label_list') {
                    $temp[$lagou_key] = json_encode($val[$lagou_val], JSON_UNESCAPED_UNICODE);
                } else {
                    $temp[$lagou_key] = $val[$lagou_val];
                }
            }
            $temp['detail'] = $this->getLagouContent($val['positionId']);
            $temp['status'] = 0;
            $re_data[]      = $temp;
        }
        return $re_data;
    }

    /**
     * 获取详情页
     * @param $position_id
     * @return bool|string
     * @throws \ErrorException
     */
    public function getLagouContent ( $position_id ) {
        $url  = Config::get('lagou_detail') . $position_id . '.html';
        $curl = new Curl();
        $curl->setUserAgent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        $curl->get($url);
        if ($curl->error) {
            $this->error = '获取详情失败,id:' . $position_id;
            return false;
        }
        $data = $curl->response;
        str_replace([' ', '\r','\t', '\n','  '], ' ',$data);
        //匹配
        preg_match_all('#<dl class=\"job_detail\" id=\"job_detail\">(.+)</dl>#Uis', $data, $match_data);
        $content = '';
        if ($match_data[1]) {
            $content = '<dl>' . $match_data[1][0]. '</dl>';
        }
        return $content;
    }

    /**
     * 生成uuid
     * @return string
     */
    public function uuid () {
        $str = md5(uniqid(mt_rand(), true));
        $new_str = substr($str, 0, 8);
        $new_str .= '-' . substr($str, 8, 4);
        $new_str .= '-' . substr($str, 12, 4);
        $new_str .= '-' . substr($str, 16, 4);
        $new_str .= '-' . substr($str, 20, 12);
        return $new_str;
    }

    /**
     * 获取内容
     * @param $url
     * @return bool|string
     * @throws \ErrorException
     */
    protected function getContent($url)
    {
        $curl = new Curl();
        $curl->setUserAgent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
        $curl->get($url);
        if ($curl->error) {
            $this->error = $curl->error_message . '[' . $curl->error_code . ']';
            return false;
        }
        $data = $curl->response;
        $data = mb_convert_encoding($data, 'utf-8', 'gb2312');
        str_replace([' ', '\n', '\r', '\t'], '', $data);
        $content = '';
        preg_match_all('#<div class=\"zg_articlecon\">(.+)</div>#Uis', $data, $match_data);
        if (isset($match_data[1])) {
            $content = '<div class="zg_articlecon">' . $match_data[1][0] . '</div>';
        }
        return $content;
    }
}