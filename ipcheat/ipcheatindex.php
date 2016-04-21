<?php
/**
 * Created by TOCAR.
 * User: a
 * Date: 2016/4/21
 * Time: 11:37
 */
function getClientIp() {
    if (!empty($_SERVER["HTTP_CLIENT_IP"]))
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    else if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
        $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    else if (!empty($_SERVER["REMOTE_ADDR"]))
        $ip = $_SERVER["REMOTE_ADDR"];
    else
        $ip = "err";
    return $ip;
}
echo  getClientIp() ;
//echo "referer: " . $_SERVER;
die();