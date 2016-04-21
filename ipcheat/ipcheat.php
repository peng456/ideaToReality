<?php
/**
 * Created by TOCAR.
 * User: a
 * Date: 2016/4/21
 * Time: 11:35
 */

function curl_string ($url,$user_agent,$proxy){
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_PROXY, $proxy);
    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt ($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt ($ch, CURLOPT_COOKIEJAR, "d:\cookies.txt");
    curl_setopt ($ch, CURLOPT_HEADER, 1);
    curl_setopt ($ch, CURLOPT_HTTPHEADER, array('CLIENT-IP:125.210.188.36', 'X-FORWARDED-FOR:125.210.188.36'));  //此处可以改为任意假IP
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt ($ch, CURLOPT_TIMEOUT, 120);



    $result = curl_exec ($ch);
    curl_close($ch);
    return $result;
}

function doget($url){
    if(function_exists('file_get_contents')) {
        $optionget = array('http' => array('method' => "GET", 'header' => "User-Agent:Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; Media Center PC 5.0; .NET CLR 3.5.21022; .NET CLR 3.0.04506; CIBA)\r\nAccept:*/*\r\nReferer:https://kyfw.12306.cn/otn/lcxxcx/init"));
        $file_contents = @file_get_contents($url, false , stream_context_create($optionget));
    } else {
        $ch = curl_init();
        $timeout = 5;
        $header = array(
            'Accept:*/*',
            'Accept-Charset:GBK,utf-8;q=0.7,*;q=0.3',
            'Accept-Encoding:gzip,deflate,sdch',
            'Accept-Language:zh-CN,zh;q=0.8,ja;q=0.6,en;q=0.4',
            'Connection:keep-alive',
            'Host:kyfw.12306.cn',
            'Referer:https://kyfw.12306.cn/otn/lcxxcx/init',
        );
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $file_contents = curl_exec($ch);
        curl_close($ch);
    }
    $file_contents = json_decode($file_contents,true);
    return $file_contents;
}


function curl_cheat2($url)
{
//    $ip = rand(1, 255) . "." . rand(1, 255) . "." . rand(1, 255) . "." . rand(1, 255) . "";
    $ip = "202" . "." . rand(1, 255) . "." . rand(1, 255) . "." . rand(1, 255) . "";
//    $ip = '8.8.8.8';
//    echo $ip."    =======";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Client_Ip: " . $ip . "", "Real_ip: " . $ip . "", "X_FORWARD_FOR:" . $ip . "", "X-forwarded-for: " . $ip . "", "PROXY_USER: " . $ip . ""));
    curl_setopt($ch, CURLOPT_URL, $url);
//    curl_setopt($ch, CURLOPT_URL, "http://192.168.1.99/11.php");
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}


$url_page1 = "http://localhost/ipcheatindex.php";
$url_page2 = "http://cys.lechome.com/match/vote?tid=263";
$user_agent = "Mozilla/4.0";
$proxy = "http://125.210.188.36:80";    //此处为代理服务器IP和PORT

$count = 50;
$arr = array(
    '本次投票次数'=>$count,
    'ok'=>0,
    'fail'=>0
);



for($i = 0;$i < $count ;$i++)
{
    $result = curl_cheat2($url_page2);

    $result_array = json_decode($result,true);

    if($result_array['code'] == 200){
        $arr['ok']++;
    }else{
        $arr['fail']++;
    }

}

echo json_encode($arr);
