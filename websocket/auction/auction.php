<?php
use Workerman\Worker;

require_once './workerman/Autoloader.php';
// 初始化一个worker容器，监听321端口
$worker = new Worker('websocket://0.0.0.0:321');
// 进程数设置为1
$worker->count = 2;
// 新增加一个属性，用来保存uid到connection的映射
$worker->uidConnections = array();
// 当有客户端发来消息时执行的回调函数

$auction_arr = array(
	'auction_name' => '西安现场拍 第005场'	//拍卖场名字
	,'auction_status' =>	1	//拍卖场（0未开始/1拍卖中/2完成）
	,'auction_countdown' => 0	//拍卖会开始倒计时秒数
	,'auction_car_all' => 107	//本场拍卖会从车数
	,'car_id' => 123321123321	//当前拍卖车id(根据 car_id 取得车辆信息)
	,'car_status' => 1	//当前车状态 1 正在拍卖 2 成交 3 流拍
	,'car_price' => 43000	//当前车拍卖价
	,'car_reserve_price' => 65000	//当前车保留价
	,'car_countdown' => 30	//当前车倒计时秒数
	,'car_price_count' => 3	//当前车出价次数
	,'car_number' => 12	//当前车序号
	,'car_button200' => 0	//200按钮状态 0 不可用 1 可用
	,'service_charge' => 1000	//当前车服务费
	,'transfer_charge' => 1000	//当前车过户费
);


$worker->onMessage = function($connection, $data)use($worker)
{
	global $auction_arr;
	echo $data;
    // 判断当前客户端是否已经验证,既是否设置了uid
    if(!isset($connection->uid))
    {
       // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
       $connection->uid = time();
       /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
        * 实现针对特定uid推送数据
        */
       $worker->uidConnections[$connection->uid] = $connection;
       //return $connection->send('login success, your uid is ' . $connection->uid);
    }
	
    broadcast(json_encode($auction_arr));
    
    // 其它罗辑，针对某个uid发送 或者 全局广播
    // 假设消息格式为 uid:message 时是对 uid 发送 message
    // uid 为 all 时是全局广播
    //broadcast($data);
};

// 当有客户端连接断开时
$worker->onClose = function($connection)use($worker)
{
    global $worker;
    if(isset($connection->uid))
    {
        // 连接断开时删除映射
        unset($worker->uidConnections[$connection->uid]);
    }
};

// 向所有验证的用户推送数据
function broadcast($message)
{
   global $worker;
   foreach($worker->uidConnections as $connection)
   {
        $connection->send($message);
   }
}

// 针对uid推送数据
function sendMessageByUid($uid, $message)
{
    global $worker;
    if(isset($worker->uidConnections[$uid]))
    {
        $connection = $worker->uidConnections[$uid];
        $connection->send($message);
    }
}

// 运行所有的worker（其实当前只定义了一个）
Worker::runAll();