<?php

function sendto_socket($data, $port)
{
    $address='127.0.0.1';
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo "Failed: ". socket_strerror(socket_last_error($socket))."\n";
    }
    $result = socket_connect($socket, $address, $port);

    if ($result === false) {
        echo "Failed: ". socket_strerror(socket_last_error($socket))."\n";
    }

    socket_write($socket, $data, strlen($data));

    socket_close($socket);
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
    
    $length=strlen($request_info['Host']);
    if (':443'===substr($request_info['Host'], $length-4, 4)) {
        $request_info['Host']=substr($request_info['Host'], 0, $length-4);
        $request_info['PORT']=443;
    }
    if ('localhost'===substr($request_info['Host'], 0, 9)) {
        $request_info['IP']='127.0.0.1';
    } else {
        $dns=dns_get_record($request_info['Host'], DNS_A);
        if (!$dns) {
            die('dns_get_record() failed!');
        }
        $request_info['IP']=$dns[0]['ip'];
    }
    return true;
}

// if ('CONNECT'===$_SERVER['REQUEST_METHOD']) {
//     $request_stream=file_get_contents('php://input');
// } else {
//     die('must use CONNECT method!');
// }

// $request=parse_request($request_stream);


error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit(60*60);

/* Turn on implicit output flushing so we see what we're getting
 * as it comes in. */
ob_implicit_flush();

//configuration:
$buf_size=8*1024;
$address_trans = '127.0.0.1';
$port_trans = 0;
$proxyResponse="HTTP/1.1 200 Connection Established\r\n\r\n";


ob_end_clean();
header('Connection: close');
ignore_user_abort(true); // just to be safe
ob_start();

if (!function_exists('socket_create')) {
    die('the PHP server not support socket!');
}

if (($socket_client = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

if (socket_bind($socket_client, $address_trans, $port_trans) === false) {
    echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n";
}
socket_getsockname($socket_client, $address_bind, $port_bind);
if (socket_listen($socket_client, 1) === false) {
    echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n";
}

$configuration=array(
    'address_bind'=>$address_bind,
    'port_bind'=>$port_bind
    );

echo json_encode($configuration);

$size = ob_get_length();
header("Content-Length: $size");
header('Content-Type: application/json');
ob_end_flush(); // Strange behaviour, will not work
flush(); // Unless both are called !
// Do processing here


if (($socket_msg = socket_accept($socket_client)) === false) {
    echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n";
}
if (!socket_set_nonblock($socket_msg)) {
    echo "socket_set_nonblock() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n";
}


$buf_recv=null;
$socket_server=null;
$buf_temp='';
$request_info=array(
    'isInit'=>false,
    'Host'=>'',
    'IP'=>'',
    'PORT'=>80
);

do {
    if (false===($buf_recv=socket_read($socket_msg, $buf_size))) {
        $socket_error_code=socket_last_error($socket_msg);
        if (10035!==$socket_error_code) {
            die("socket_recv() failed: reason: " . socket_strerror($socket_error_code) . "\n");
        }
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
            die("socket_create() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
        }
        if (!socket_connect($socket_server, $request_info['IP'], $request_info['PORT'])) {
            die("socket_connect() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
        }
        if (!socket_set_nonblock($socket_server)) {
            echo "socket_set_nonblock() failed: reason: " . socket_strerror(socket_last_error($socket_client)) . "\n";
        }
        if (!socket_write($socket_server, $buf_temp, strlen($buf_temp))) {
            die("socket_write() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
        }
    } else {
        if ($buf_recv) {
            $len=socket_write($socket_server, $buf_recv);
            if (!$len) {
                die("socket_write() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
            }
        }
        if (false===($buf_server_recv=socket_read($socket_server, $buf_size))) {
            $socket_error_code=socket_last_error($socket_server);
            if (10054===$socket_error_code||10053===$socket_error_code) {
                socket_close($socket_client);
                socket_close($socket_server);
                break;
            } elseif (10035!==$socket_error_code) {
                die("socket_read() failed: reason: " . socket_strerror($socket_error_code) . "\n");
            }
        } else {
            if (''===$buf_server_recv) {
                socket_close($socket_msg);
                socket_close($socket_server);
                break;
            } else {
                if (!socket_write($socket_msg, $buf_server_recv)) {
                    die("socket_write() Failed: ". socket_strerror(socket_last_error($socket_server))."\n");
                }
            }
        }
    }
} while (true);

socket_close($socket_client);


?>

