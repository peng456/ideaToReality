<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

use \GatewayWorker\Lib\Gateway;

require_once(__DIR__.'/config.php');
require_once(__DIR__.'/base.func.php');

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 *
 * GatewayWorker开发参见手册：
 * @link http://gatewayworker-doc.workerman.net/
 */

class wsAuctionUser {
	public $uid = 0;	//
	public $key = '';
	public $websocket_secret = '';
	public $button_secret = '';
	public $city = '';

	public $bid_count = 0;	//状态 0 默认

	//////////////////
	public static $mc_user_cas = '';
	public static $mc_user_key = 'auction_websocket_user_';

	public static function do_user($action, $uid, $obj_user=null)
	{
		if(!$action || !$uid)
		{
			return null;
		}

		$obj = null;
		$mcobj = Event::getMC();
		do {
			$obj = $mcobj->get(wsAuctionUser::$mc_user_key.$uid, null, wsAuctionUser::$mc_user_cas);
			if ($mcobj->getResultCode() == Memcached::RES_NOTFOUND) {
				if($obj_user)
				{
					$obj = $obj_user;
				}
				else 
				{
					$obj = new wsAuctionUser();
					$obj->uid = $uid;
				}
				$mcobj->add(wsAuctionUser::$mc_user_key.$uid, $obj);
			}
			else
			{
				switch ($action)
				{
					case 'update_bid_count':
						$obj->bid_count = $obj_user->bid_count;
						break;
					case 'update_all':
						if(!$obj_user)
						{
							return null;
						}
						$obj->key = $obj_user->key;
						$obj->websocket_secret = $obj_user->websocket_secret;
						$obj->button_secret = $obj_user->button_secret;
						$obj->city = $obj_user->city;
						$obj->bid_count = $obj_user->bid_count;
						break;
					case 'get':
						break;
					default:
						break;
				}

				$mcobj->cas(wsAuctionUser::$mc_user_cas, wsAuctionUser::$mc_user_key.$obj->uid, $obj);
			}
		} while ($mcobj->getResultCode() != Memcached::RES_SUCCESS);

		unset($action);
		unset($uid);

		return $obj;
	}
}

class Event
{
	public static $add_price = array(200,500,1000,2000);
	public static $city = array('南昌', '南宁', '西安');
	public static $auction_arr = array();	//拍卖会静态数组
	public static $mc_cas = '';

	public static $mc_key = 'auction_websocket_city_';
	public static $mc_servers = array(array('127.0.0.1',11211));
	public static $gCache = array();

	public static $init_auction = array(
	'auction_name' => ''	//拍卖场名字
	,'auction_id' => 0	//拍卖会id
	,'auction_status' =>	0	//拍卖场（0未开始/1拍卖中/2完成）
	,'auction_countdown' => 0	//拍卖会开始倒计时秒数
	,'auction_start_time' => 0	//拍卖会开始
	,'auction_car_all' => 0	//本场拍卖会车数

	,'car_id' => 0	//当前拍卖车id(根据 car_id 取得车辆信息)
	,'car_status' => 1	//当前车状态 1 正在拍卖 2 成交 3 流拍
	,'car_price' => 0	//当前车拍卖价
	,'car_reserve_price' => 0	//当前车保留价
	,'reach_reserve_price' => 0	//到达当前车保留价 0 未到达 1 到达
	,'car_countdown' => 30	//当前车倒计时秒数
	,'car_price_count' => array()	//当前车出价过程
	,'car_number' => 1	//当前车序号
	,'car_button200' => 0	//200按钮状态 0 不可用 1 可用
	,'service_charge' => 0	//当前车服务费
	,'transfer_charge' => 0	//当前车过户费
	,'timer_mark'=>0	//计时器操作时间
	,'old_car_save'=>0	// 0 老车拍卖结束没存储 1 已存储
	,'new_car_load'=>0	// 0 新车未载入 1 已经载入
	);

	public static function getMC()
	{
		//单例 memcached
		if( !isset(Event::$gCache['mcobj']) || !method_exists(Event::$gCache['mcobj'], 'set') || !(Event::$gCache['mcobj']->set('key_is_memcached_ok', 1)) )
		{
			$mcobj = new Memcached();
			$mcobj->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
			$mcobj->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, TRUE);
			if($mcobj->addServers(Event::$mc_servers))
			{
				Event::$gCache['mcobj'] = $mcobj;
			}
			else
			{
				unset($mcobj);
				Event::getMC();
			}
		}
		return  Event::$gCache['mcobj'];
	}

	//定时器 call
	public static function do_timer()
	{
		foreach (Event::$city as $city_item)
		{
			if(Event::do_memcached_city($city_item, array('act'=>'timer')))
			{
				Gateway::sendToGroup($city_item, json_encode(Event::$auction_arr[$city_item]));
			}
		}
		unset($city_item);
	}

	//
	public static function do_memcached_city($city, $params=array())
	{
		$mcobj = Event::getMC();
		do {
			unset($return);
			$return = false;
			$itime = time();

			Event::$auction_arr[$city] = $mcobj->get(Event::$mc_key.$city, null, Event::$mc_cas);
			if ($mcobj->getResultCode() == Memcached::RES_NOTFOUND) {
				Event::$auction_arr[$city] = Event::$init_auction;
				$mcobj->add(Event::$mc_key.$city, Event::$auction_arr[$city]);
			}
			else
			{
				if(isset($params['act']) && $params['act'] == 'bid'
				&& $params['car_id'] == Event::$auction_arr[$city]['car_id']
				&& (($params['add_price'] == 200 && Event::$auction_arr[$city]['car_button200']) || $params['add_price'] != 200)
				&& Event::$auction_arr[$city]['car_status'] == 1
				&& Event::$auction_arr[$city]['car_countdown'] > 0
				)//竞价
				{
					Event::$auction_arr[$city]['car_price'] += $params['add_price'];
					if(Event::$auction_arr[$city]['car_price'] >= Event::$auction_arr[$city]['car_reserve_price'])
					{
						Event::$auction_arr[$city]['reach_reserve_price'] = 1;
					}
					else
					{
						Event::$auction_arr[$city]['reach_reserve_price'] = 0;
					}
					Event::$auction_arr[$city]['car_price_count'][] = array($params['uid'], Event::$auction_arr[$city]['car_price']);
					if(Event::$auction_arr[$city]['car_countdown'] < 10)
					{
						Event::$auction_arr[$city]['car_countdown'] = 10;
					}
					$return = true;
				}
				else if(isset($params['act']) && $params['act'] == 'button200'
				&& isset(Event::$auction_arr[$city])
				&& $params['car_id'] == Event::$auction_arr[$city]['car_id']
				&& Event::$auction_arr[$city]['car_status'] == 1
				)//开启200按钮
				{
					Event::$auction_arr[$city]['car_button200'] = 1;
					$return = true;
				}
				elseif (isset($params['act']) && $params['act'] == 'login')
				{
					//登录拍卖大厅
					$return = true;
				}
				elseif (isset($params['act']) && $params['act'] == 'timer')
				{
					//定时器执行的
					if(Event::$auction_arr[$city]['timer_mark'] < $itime)
					{
						Event::$auction_arr[$city]['timer_mark'] = $itime;
						Event::$auction_arr[$city]['car_countdown'] -= 1;
						if(Event::$auction_arr[$city]['car_countdown'] < 0 && Event::$auction_arr[$city]['car_status'] == 1 && Event::$auction_arr[$city]['auction_id'] && Event::$auction_arr[$city]['car_id'])
						{
							if(true)
							{
								Event::$auction_arr[$city]['car_countdown'] = 5;
								if(Event::$auction_arr[$city]['reach_reserve_price'])
								{
									Event::$auction_arr[$city]['car_status'] = 2;
								}
								else
								{
									Event::$auction_arr[$city]['car_status'] = 3;
								}
							}
							$return = true;
						}
						elseif ((Event::$auction_arr[$city]['car_status'] == 2 || Event::$auction_arr[$city]['car_status'] == 3) || !Event::$auction_arr[$city]['auction_id'])
						{
							if(!Event::$auction_arr[$city]['old_car_save'] && Event::$auction_arr[$city]['auction_id'] && Event::$auction_arr[$city]['car_id']
							&& (Event::$auction_arr[$city]['car_status'] == 2 || Event::$auction_arr[$city]['car_status'] == 3))
							{
								//成交/流拍/用户数据 处理
								$tmp_uid = '';
								$tmp_deal_price = '';
								if(Event::$auction_arr[$city]['car_status'] == 2 && Event::$auction_arr[$city]['car_price_count'])
								{
									$end_bid = end(Event::$auction_arr[$city]['car_price_count']);
									$tmp_uid = $end_bid[0];
									$tmp_deal_price = $end_bid[1];
									unset($end_bid);
								}

								$data_request = array(
								'mod' => 'Business'
								, 'act' => 'set_user_car'
								, 'platform' => 'tocar'
								, 'uid' => $tmp_uid
								, 'auction_id' => Event::$auction_arr[$city]['auction_id']
								, 'car_id' => Event::$auction_arr[$city]['car_id']
								, 'deal_price' => $tmp_deal_price
								, 'auction_detail' => json_encode(Event::$auction_arr[$city]['car_price_count'])
								);
								$biaoji;
								//log

								$randkey = encryptMD5($data_request);
								$url = tocar_config::$BASE_PATH . "?randkey=" . $randkey . "&c_version=0.0.1";
								$result = json_decode(https_request($url, array('parameter' => json_encode($data_request))), true);
								if (!$result || !isset($result['code']) || $result['code'] != 0 || (isset($result['sub_code']) && $result['sub_code'] != 0) || !isset($result['data']['uid'])) {
									$biaoji;
									//log
								}
								else
								{
									if($result['data']['uid'])
									{
										$obj_user = new wsAuctionUser();
										$obj_user->uid = $result['data']['uid'];
										$obj_user->bid_count = $result['data']['bid_count'];
										wsAuctionUser::do_user('update_bid_count', $result['data']['uid'], $obj_user);
										unset($obj_user);
									}
								}
								Event::$auction_arr[$city]['old_car_save'] = 1;

								unset($tmp_uid);
								unset($tmp_deal_price);
								unset($data_request);
								unset($result);
							}

							if((Event::$auction_arr[$city]['old_car_save'] == 1 && !Event::$auction_arr[$city]['new_car_load']) || !Event::$auction_arr[$city]['auction_id'])
							{
								//新车信息
								$data_request = array(
								'mod' => 'Business'
								, 'act' => 'get_city_current_auction'
								, 'platform' => 'tocar'
								, 'city' => $city
								);
								$randkey = encryptMD5($data_request);
								$url = tocar_config::$BASE_PATH . "?randkey=" . $randkey . "&c_version=0.0.1";
								$result = json_decode(https_request($url, array('parameter' => json_encode($data_request))), true);
								if (!$result || !isset($result['code']) || $result['code'] != 0
								|| (isset($result['sub_code']) && $result['sub_code'] != 0)
								|| !isset($result['data']['auction'])
								|| !$result['data']['auction']) {
									;
								}
								else
								{
									Event::$auction_arr[$city]['auction_name'] = $result['data']['auction']['name'];
									Event::$auction_arr[$city]['auction_id'] = $result['data']['auction']['auction_id'];
									Event::$auction_arr[$city]['auction_status'] = $result['data']['auction']['status'];
									Event::$auction_arr[$city]['auction_start_time'] = $result['data']['auction']['start_time'];

									if(isset($result['data']['current_car']) && isset($result['data']['current_car']['car_id']) && $result['data']['current_car']['car_id'])
									{
										Event::$auction_arr[$city]['car_id'] = $result['data']['current_car']['car_id'];
										Event::$auction_arr[$city]['car_price'] = $result['data']['current_car']['dumb_bid'];
										Event::$auction_arr[$city]['car_reserve_price'] = $result['data']['current_car']['reserve_price'];
										Event::$auction_arr[$city]['reach_reserve_price'] = 0;
										Event::$auction_arr[$city]['car_number'] += 1;
										Event::$auction_arr[$city]['car_button200'] = 0;
										Event::$auction_arr[$city]['service_charge'] = $result['data']['current_car']['service_charge'];
										Event::$auction_arr[$city]['transfer_charge'] = $result['data']['current_car']['transfer_charge'];
										if(isset($result['data']['auction_car_all']))
										{
											Event::$auction_arr[$city]['auction_car_all'] = $result['data']['auction_car_all'];
										}
										else
										{
											Event::$auction_arr[$city]['auction_car_all'] -= 1;
										}
										Event::$auction_arr[$city]['car_price_count'] = array();

										Event::$auction_arr[$city]['new_car_load'] = 1;
									}

									$return = true;
								}
								unset($result);

							}

							if(Event::$auction_arr[$city]['car_countdown'] < 0 && Event::$auction_arr[$city]['new_car_load'] == 1 && Event::$auction_arr[$city]['old_car_save'] == 1)
							{
								Event::$auction_arr[$city]['old_car_save'] = 0;
								Event::$auction_arr[$city]['new_car_load'] = 0;
								Event::$auction_arr[$city]['car_status'] = 1;
								Event::$auction_arr[$city]['car_countdown'] = 30;

								$return = true;
							}
							elseif(Event::$auction_arr[$city]['auction_id'] && Event::$auction_arr[$city]['car_countdown'] < 0
							&& !Event::$auction_arr[$city]['new_car_load'] && Event::$auction_arr[$city]['old_car_save'] == 1)
							{
								//没车了，结束拍卖会
								$data_request = array(
								'mod' => 'Business'
								, 'act' => 'set_auction'
								, 'platform' => 'tocar'
								, 'auction_id' => Event::$auction_arr[$city]['auction_id']
								, 'status' => 2
								);
								$randkey = encryptMD5($data_request);
								$url = tocar_config::$BASE_PATH . "?randkey=" . $randkey . "&c_version=0.0.1";
								$result = json_decode(https_request($url, array('parameter' => json_encode($data_request))), true);
								if (!$result || !isset($result['code']) || $result['code'] != 0 || (isset($result['sub_code']) && $result['sub_code'] != 0) ) {
									$biaoji;
									//log
								}
								else
								{
									Event::$auction_arr[$city] = Event::$init_auction;
								}
								$return = true;
							}
						}

						Event::$auction_arr[$city]['auction_countdown'] = Event::$auction_arr[$city]['auction_start_time'] - $itime;
						if(Event::$auction_arr[$city]['auction_countdown'] < 0 && Event::$auction_arr[$city]['auction_status'] == 0)
						{
							Event::$auction_arr[$city]['auction_countdown'] = 0;
							Event::$auction_arr[$city]['auction_status'] = 1;

							//开始
							$data_request = array(
							'mod' => 'Business'
							, 'act' => 'set_auction'
							, 'platform' => 'tocar'
							, 'auction_id' => Event::$auction_arr[$city]['auction_id']
							, 'status' => 1
							);
							$randkey = encryptMD5($data_request);
							$url = tocar_config::$BASE_PATH . "?randkey=" . $randkey . "&c_version=0.0.1";
							$result = json_decode(https_request($url, array('parameter' => json_encode($data_request))), true);
							if (!$result || !isset($result['code']) || $result['code'] != 0 || (isset($result['sub_code']) && $result['sub_code'] != 0) ) {
								$biaoji;
								//log
							}
						}
						else
						{
							Event::$auction_arr[$city]['auction_status'] = 0;
						}
					}
				}

				$mcobj->cas(Event::$mc_cas, Event::$mc_key.$city, Event::$auction_arr[$city]);
			}
		} while ($mcobj->getResultCode() != Memcached::RES_SUCCESS);

		unset($itime);
		unset($city);

		if(isset($return) && $return)
		{
			return $return;
		}
		else
		{
			return false;
		}
	}

	/**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     * @link http://gatewayworker-doc.workerman.net/gateway-worker-development/onconnect.html
     */
	public static function onConnect($client_id)
	{
		// 向当前client_id发送数据 @see http://gatewayworker-doc.workerman.net/gateway-worker-development/send-to-client.html
		//Gateway::sendToClient($client_id, "Hello $client_id");
		// 向所有人发送 @see http://gatewayworker-doc.workerman.net/gateway-worker-development/send-to-all.html
		//Gateway::sendToAll("$client_id login");
	}

	/**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param string $message 具体消息
    * @link http://gatewayworker-doc.workerman.net/gateway-worker-development/onmessage.html
    */
	public static function onMessage($client_id, $message)
	{
		echo $message;
		$params = json_decode($message, true);
		if(isset($params['act']))
		{
			switch ($params['act'])
			{
				case 'login':
					if(isset($params['city']) && in_array($params['city'], Event::$city)
					&& isset($params['uid']) && $params['uid']
					&& isset($params['key']) && $params['key']
					&& isset($params['websocket_secret'])
					&& isset($params['button_secret'])
					&& isset($params['bid_count'])
					&& isset($params['time_stamp']) && $params['time_stamp'] && ((time() - $params['time_stamp']) < 3600)
					)
					{
						$check = (!$params['websocket_secret'] || ($params['websocket_secret'] == encryptMD5(array('uid'=>$params['uid'], 'key'=>$params['key'], 'city'=>$params['city'], 'bid_count'=>$params['bid_count'], 'time_stamp'=>$params['time_stamp']))))
						&& (!$params['button_secret'] || ($params['button_secret'] == encryptMD5(array('uid'=>$params['uid'], 'key'=>$params['key'], 'city'=>$params['city'], 'button200'=>1))))
						;
						if($check)
						{
							Event::onClose($client_id);

							$obj_user = new wsAuctionUser();
							$obj_user->uid = $params['uid'];
							$obj_user->key = $params['key'];
							$obj_user->websocket_secret = $params['websocket_secret'];
							$obj_user->button_secret = $params['button_secret'];
							$obj_user->city = $params['city'];
							$obj_user->bid_count = $params['bid_count'];

							if(wsAuctionUser::do_user('update_all', $params['uid'], $obj_user))
							{
								Gateway::joinGroup($client_id, $params['city']);
								if(Event::do_memcached_city($params['city'],$params))
								{
									Gateway::sendToCurrentClient(json_encode(Event::$auction_arr[$params['city']]));
								}
							}
							else
							{
								Gateway::sendToCurrentClient(json_encode(array('code'=>1, 'desc'=>'登录失败' , 'line'=>__LINE__)));
							}
							
							unset($obj_user);
						}
						else
						{
							Gateway::sendToCurrentClient(json_encode(array('code'=>1, 'desc'=>'登录失败' , 'line'=>__LINE__)));
						}
						unset($check);
					}
					else
					{
						Gateway::sendToCurrentClient(json_encode(array('code'=>1, 'desc'=>'登录失败' , 'line'=>__LINE__)));
					}
					break;
				case 'bid':
					if(!isset($params['uid']))
					{
						break;
					}
					$obj_user = wsAuctionUser::do_user('get', $params['uid']);
					if(isset($obj_user->city) && in_array($obj_user->city, Event::$city)
					&& isset($params['uid']) && $params['uid'] && $params['uid'] == $obj_user->uid
					&& isset($params['key']) && $params['key'] && $params['key'] == $obj_user->key
					&& isset($params['websocket_secret']) && $params['websocket_secret'] && $params['websocket_secret'] == $obj_user->websocket_secret
					&& isset($params['car_id']) && $params['car_id']
					&& isset($params['add_price']) && $params['add_price'] && in_array($params['add_price'], Event::$add_price)
					&& $obj_user->bid_count > 0
					)
					{
						if(Event::do_memcached_city($obj_user->city, $params))
						{
							Gateway::sendToGroup($obj_user->city, json_encode(Event::$auction_arr[$obj_user->city]));
						}
						else
						{
							Gateway::sendToCurrentClient(json_encode(array('code'=>3, 'desc'=>'出价失败' , 'line'=>__LINE__)));
						}
					}
					elseif (!$obj_user->websocket_secret || !in_array($params['add_price'], Event::$add_price))
					{
						Gateway::sendToCurrentClient(json_encode(array('code'=>6, 'desc'=>'没有功能权限' , 'line'=>__LINE__)));
					}
					elseif ($obj_user->bid_count <= 0)
					{
						Gateway::sendToCurrentClient(json_encode(array('code'=>5, 'desc'=>'保证金不足' , 'line'=>__LINE__)));
					}
					else
					{
						Gateway::sendToCurrentClient(json_encode(array('code'=>2, 'desc'=>'参数错误', 'line'=>__LINE__)));
					}
					break;
				case 'button200':
					if(!isset($params['uid']))
					{
						Gateway::sendToCurrentClient(json_encode(array('code'=>2, 'desc'=>'参数错误', 'line'=>__LINE__)));
						break;
					}
					$obj_user = wsAuctionUser::do_user('get', $params['uid']);
					if(isset($obj_user->city) && in_array($obj_user->city, Event::$city)
					&& isset($params['uid']) && $params['uid'] && $params['uid'] == $obj_user->uid
					&& isset($params['key']) && $params['key'] && $params['key'] == $obj_user->key
					&& isset($params['button_secret']) && $params['button_secret'] && $params['button_secret'] == $obj_user->button_secret
					&& isset($params['car_id']) && $params['car_id']
					)
					{
						if(Event::do_memcached_city($obj_user->city, $params))
						{
							Gateway::sendToGroup($obj_user->city, json_encode(Event::$auction_arr[$obj_user->city]));
						}
						else
						{
							Gateway::sendToCurrentClient(json_encode(array('code'=>4, 'desc'=>'操作失败' , 'line'=>__LINE__)));
						}
					}
					else
					{
						Gateway::sendToCurrentClient(json_encode(array('code'=>2, 'desc'=>'参数错误' , 'line'=>__LINE__)));
					}
					break;
				default:
					break;
			}
		}
		else
		{
			Gateway::sendToCurrentClient(json_encode(array('code'=>2, 'desc'=>'参数错误' , 'line'=>__LINE__)));
		}
		unset($params);

	}

	/**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
	public static function onClose($client_id)
	{
		$_SESSION = array();
		foreach (Event::$city as $val)
		{
			Gateway::leaveGroup($client_id, $val);
		}
		unset($val);
		// 向所有人发送 @see http://gatewayworker-doc.workerman.net/gateway-worker-development/send-to-all.html
		//GateWay::sendToAll("$client_id logout");
	}
}
