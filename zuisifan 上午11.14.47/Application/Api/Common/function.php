<?php

function jsonReturn($status=0,$msg='',$data=''){
    if(empty($data))
        $data = '';
    $info['status'] = $status ? 1 : $status;
    $info['msg'] = $msg;
    $info['result'] = $data;
    exit(json_encode($info));
}
/**
 * 获取具体时间
 */
function friendlyDate($time = null) {
    if (!$time) {
        return '';
    }
    // 获取当前时间戳
    $cTime = time();
    $date = date('Y.m.d', $time);
    // 获取年份的差值，如果大于等于1，表示不是今年
    $xyh = intval(($cTime - $time) / 24 / 3600);
    // 获取当前时间戳与$time的差
    $dTime = $cTime - $time;
    // date("z", $cTime)---: z 一年中的第几天（从 0 到 365）
    //$dDay = abs(intval(date("z", $cTime)) - intval(date("z", $time)));
    if ($dTime < 60) {
        return "今天   " . $dTime . "秒前";
    } elseif ($dTime < 3600) {
        return "今天   " . intval($dTime / 60) . "分钟前";
    } elseif ($dTime >= 3600 && $xyh < 1) {
        return intval($dTime / 3600) . "小时前";
    } elseif ($xyh > 365) {
        // 如果时间大于1年，返回具体日期
        return $date;
    } elseif ($xyh == 1) {
        return "昨天";
    } elseif ($xyh >= 2 && $xyh <= 13) {
        return intval($xyh) . "天前";
    } elseif ($xyh > 13 && $xyh <= 60) {
        return intval($xyh / 7) . "周前";
    } elseif ($xyh > 60) {
        return intval($xyh / 30) . "月前";
    }
}