<?php


//configuration:
$buf_size=8*1024;
$address_trans = '127.0.0.1';
$port_trans = 0;
$proxy_response="HTTP/1.1 200 Connection Established\r\n\r\n";
$script_time_limit=0.2*60; //seconds
$trans_time_limit=0.1*60; //seconds  less than $script_time_limit


$start_time=date_timestamp_get(date_create());

//global
$socket_client=null;
$socket_msg=null;
$socket_server=null;



function proxy_die($msg)
{
    global $socket_client,$socket_msg,$socket_server;
    if ($socket_msg) {
        socket_close($socket_msg);
    }
    if ($socket_client) {
        socket_close($socket_client);
    }
    if ($socket_server) {
        socket_close($socket_server);
    }
    die($msg);
}

function sendto_socket($data, $port)
{
    $address='127.0.0.1';
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        proxy_die("Failed: ". socket_strerror(socket_last_error($socket))."\n");
    }
    $result = socket_connect($socket, $address, $port);

    if ($result === false) {
        proxy_die("Failed: ". socket_strerror(socket_last_error($socket))."\n");
    }

    socket_write($socket, $data, strlen($data));

    socket_close($socket);
}
$sleep_time=0;
function time_manager($status, $increase=0.1)
{
    global $sleep_time,$trans_time_limit,$start_time;
    if ($trans_time_limit<date_timestamp_get(date_create())-$start_time) {
        proxy_die('trans time out!');
    }
    if ($status) {
        $sleep_time=0;
    } else {
        if (1>$sleep_time) {
            $sleep_time+=$increase;
        }
    }
    usleep($sleep_time*pow(10, 6));
}

function parse_request($request_stream)
{
    $result=array(
        'method'=>'',
        'url'=>'',
        'HTTP_version'=>'',
        'headers'=>array()
    );
    list($head)=preg_split("/\r\n\r\n/", $request_stream, 2);
    $head_splited=preg_split("/\r\n/", $head);
    list($result['method'], $result['url'], $result['HTTP_version'])=preg_split('/ /', $head_splited[0]);
    $length=count($head_splited);
    for ($i=1;$i<$length;++$i) {
        $header=preg_split('/: /', $head_splited[$i], 2);
        $result['headers'][$header[0]]=$header[1];
        //$result['headers'][]=$head_splited[$i];
    }
    return $result;
}

function parse_request_info($buf_temp, &$request_info)
{
    $location0=strpos($buf_temp, "\r\n\r\n");
    if (false===$location0) {
        return false;
    }
    $request_parsed=parse_request($buf_temp);
    $request_info['Host']=$request_parsed['headers']['Host'];
    $request_info['method']=$request_parsed['method'];

    $Host=$request_info['Host'];
    $Port='80';
    $location0=strpos($request_info['Host'], ':');
    if (false!==$location0) {
        list($Host, $Port)=preg_split("/:/", $request_info['Host']);
    }
    $request_info['Host']=$Host;
    $request_info['PORT']=$Port;
    
    if ('localhost'===substr($request_info['Host'], 0, 9)) {
        $request_info['IP']='127.0.0.1';
    }
    if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $request_info['Host'])) {
        $request_info['IP']= $request_info['Host'];
    } else {
        $dns=dns_get_record($request_info['Host'], DNS_A);
        if (!$dns) {
            proxy_die('dns_get_record() failed!');
        }
        $request_info['IP']=$dns[0]['ip'];
    }
    return true;
}

// if ('CONNECT'===$_SERVER['REQUEST_METHOD']) {
//     $request_stream=file_get_contents('php://input');
// } else {
//     proxy_die('must use CONNECT method!');
// }

// $request=parse_request($request_stream);


error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit($script_time_limit);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();


ob_end_clean();
header('Connection: close');
ignore_user_abort(true); // just to be safe
ob_start();

if (!function_exists('socket_create')) {
    proxy_die('the PHP server not support socket!');
}

if (($socket_client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    proxy_die("socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
}

if (socket_bind($socket_client, $address_trans, $port_trans) === false) {
    proxy_die("socket_bind() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n");
}
socket_getsockname($socket_client, $address_bind, $port_bind);
if (socket_listen($socket_client, 1) === false) {
    proxy_die("socket_listen() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n");
}

$configuration=array(
    'address_bind'=>$address_bind,
    'port_bind'=>$port_bind
    );

echo json_encode($configuration);

$size = ob_get_length();
header('Connection: close');
header("Content-Length: $size");
header('Content-Type: application/json');
ob_end_flush(); // Strange behaviour, will not work
flush(); // Unless both are called !
// Do processing here


if (!socket_set_nonblock($socket_client)) {
    proxy_die("socket_set_nonblock() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n");
}
while (!($socket_msg = socket_accept($socket_client))) {
    $socket_error_code=socket_last_error($socket_client);
    if (0!==$socket_error_code) {
        proxy_die("socket_read() failed: reason: " . socket_strerror($socket_error_code) . "\n");
    }
    time_manager(false);
}
if (!socket_set_nonblock($socket_msg)) {
    proxy_die("socket_set_nonblock() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n");
}


$buf_recv=null;
$socket_server=null;
$buf_temp='';
$request_info=array(
    'isInit'=>false,
    'method'=>'',
    'Host'=>'',
    'IP'=>'',
    'PORT'=>80
);

do {
    if (false===($buf_recv=socket_read($socket_msg, $buf_size))) {
        $socket_error_code=socket_last_error($socket_msg);
        if (10054===$socket_error_code||10053===$socket_error_code) {
            socket_close($socket_msg);
            socket_close($socket_server);
            break;
        } elseif (10035!==$socket_error_code) {
            proxy_die("socket_read() failed: reason: " . socket_strerror($socket_error_code) . "\n");
        }
        time_manager(false);
    } else {
        time_manager(true);
    }
    if (''===$buf_recv) {
        if ($socket_server) {
            socket_close($socket_server);
        }
        socket_close($socket_msg);
        break;
    }
    if (!$request_info['isInit']) {
        $buf_temp.=$buf_recv;
        if (!parse_request_info($buf_temp, $request_info)) {
            continue;
        }
        $request_info['isInit']=true;
    }
    if (!$socket_server) {
        //create $socket_server
        $socket_server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false===$socket_server) {
            proxy_die("socket_create() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
        }
        if (!socket_connect($socket_server, $request_info['IP'], $request_info['PORT'])) {
            proxy_die("socket_connect() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
        }
        if (!socket_set_nonblock($socket_server)) {
            proxy_die("socket_set_nonblock() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n");
        }
        if ('CONNECT'===$request_info['method']) {
            if (!socket_write($socket_msg, $proxy_response)) {
                proxy_die("socket_write() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
            }
        } else {
            if (!socket_write($socket_server, $buf_temp, strlen($buf_temp))) {
                proxy_die("socket_write() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
            }
        }
    } else {
        if ($buf_recv) {
            $len=socket_write($socket_server, $buf_recv);
            if (!$len) {
                proxy_die("socket_write() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
            }
        }
        if (false===($buf_server_recv=socket_read($socket_server, $buf_size))) {
            $socket_error_code=socket_last_error($socket_server);
            if (10054===$socket_error_code||10053===$socket_error_code) {
                socket_close($socket_msg);
                socket_close($socket_server);
                break;
            } elseif (10035!==$socket_error_code) {
                proxy_die("socket_read() failed: reason: " . socket_strerror($socket_error_code) . "\n");
            }
            time_manager(false);
        } else {
            if (''===$buf_server_recv) {
                socket_close($socket_msg);
                socket_close($socket_server);
                break;
            } else {
                if (!socket_write($socket_msg, $buf_server_recv)) {
                    proxy_die("socket_write() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
                }
                time_manager(true);
            }
        }
    }
} while (true);

socket_close($socket_client);


?>

