<?php

/*
base.func.php 提供公用函数
*/

function time2str($itime)
{
	if ($itime) {
		return date('Y-m-d H:i:s', $itime);
	}
	return false;
}

function encryptMD5($data)
{
	$content = '';
	if (!$data || !is_array($data)) {
		return $content;
	}
	ksort($data);
	foreach ($data as $key => $value)
	{
		$content = $content . $key . $value;
	}
	if (!$content) {
		return $content;
	}

	return sub_encryptMD5($content);

}

function sub_encryptMD5($content)
{
	//global $RPC_KEY;
	$content = $content . tocar_config::$RPC_KEY;
	$content = md5($content);
	if (strlen($content) > 10) {
		$content = substr($content, 0, 10);
	}
	return $content;
}

function https_request($url, $data = null)
{
	$output = '';
	try{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		if (!empty($data)) {
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);
	}catch (Exception $e)
	{
		$e->getMessage();
	}
	return $output;
}

function logger($file, $word)
{
	$fp = fopen($file, "a");
	flock($fp, LOCK_EX);
	fwrite($fp, "执行日期：" . strftime("%Y-%m-%d %H:%M:%S", time()) . "\n" . $word . "\n\n");
	flock($fp, LOCK_UN);
	fclose($fp);
}

