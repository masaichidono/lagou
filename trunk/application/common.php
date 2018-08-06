<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
/**
 * 根据省份名称和城市名称获取更新需要的城市array和区县array
 * @param array $data
 * @param $province
 * @param $city
 * @return array
 */
function getArrByProvinceAndCity ( $data = [], $province, $city ) {
    $re_data = array(
        'city_arr'     => [],
        'district_arr' => [],
    );
    //如果没有data，则从文件中获取
    if (empty($data)) {
        $tmp_data = json_decode(file_get_contents('./static/file/province.json'), true);
        foreach ($tmp_data as $key => $val) {
            $data[$val['name']] = $val;
        }
    }
    if (isset($data[$province])) {
        $city_arr            = $data[$province]['city'];
        $re_data['city_arr'] = $city_arr;
        foreach ($city_arr as $key => $val) {
            if ($val['name'] == $city) {
                $re_data['district_arr'] = $val['area'];
                break;
            }
        }
    }
    return $re_data;
}

//获取中国省份城市区县json数据
function getProvinceData () {
    //输出json文件内容
    $province_json = json_decode(file_get_contents('./static/js/province.json'), true);
    $data          = [];
    //处理数据
    foreach ($province_json as $key => $val) {
        $data[$val['name']] = $val;
    }
    return $data;
}