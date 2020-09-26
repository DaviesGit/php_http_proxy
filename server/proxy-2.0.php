<?php

$request_stream='';


function parse_request($request_stream)
{
    $result=array(
        'method'=>'',
        'url'=>'',
        'HTTP_version'=>'',
        'headers'=>array(),
        'body'=>'',
        'original'=>$request_stream
    );
    list($head, $result['body'])=preg_split("/\r\n\r\n/", $request_stream, 2);
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
function is_received($response)
{
    $location0=strpos($response, "\r\n\r\n");
    if (false===$location0) {
        return false;
    }
    $location1=strpos($response, 'Content-Length: ');
    if (false===$location1) {
        return true;
    }
    $location1+=16;
    $location2=strpos($response, "\r\n", $location1);
    $content_length=substr($response, $location1, $location2-$location1);
    $length=intval($content_length);
    if (0===$length) {
        die('server response error! in is_received()');
    }
    $total_lenth=$location0+4+$length;
    $response_length=strlen($response);
    if ($response_length<$total_lenth) {
        return false;
    } elseif ($response_length>$total_lenth) {
        die('server response error! $response_length>$total_lenth');
    }
    return true;
}

function make_request($request)
{
    $port=80;
    $host=$request['headers']['Host'];
    $length=strlen($host);
    if (':443'===substr($host,$length-4,4)){
        $host=substr($host,0,$length-4);
        $port=443;
    }
    $dns=dns_get_record($host, DNS_A);
    if (!$dns) {
        die('dns_get_record() failed!');
    }
    $IP=$dns[0]['ip'];
    if (!function_exists('socket_create')) {
        die('the PHP server not support socket!');
    }
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (false===$socket) {
        die("socket_create() Failed: ". socket_strerror(socket_last_error($socket))."\n");
    }
    if (!socket_connect($socket, $IP, $port)) {
        die("socket_connect() Failed: ". socket_strerror(socket_last_error($socket))."\n");
    }
    if (!socket_write($socket, $request['original'], strlen($request['original']))) {
        die("socket_send() Failed: ". socket_strerror(socket_last_error($socket))."\n");
    }
    $response='';
    while (!is_received($response)&&$readed_data = socket_read($socket, 2048)) {
        $response.=$readed_data;
    }
    socket_close($socket);
    return $response;
}

if ('CONNECT'===$_SERVER['REQUEST_METHOD']) {
    $request_stream=file_get_contents('php://input');
} else {
    die('must use CONNECT method!');
}
$request=parse_request($request_stream);
echo make_request($request);


?>

