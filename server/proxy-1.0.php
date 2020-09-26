<?php

$request_stream='';

function parse_request($request_stream)
{
    $result=array(
        'method'=>'',
        'url'=>'',
        'HTTP_version'=>'',
        'headers'=>array(),
        'body'=>''
    );
    list($head, $result['body'])=preg_split("/\r\n\r\n/", $request_stream, 2);
    $head_splited=preg_split("/\r\n/", $head);
    list($result['method'], $result['url'], $result['HTTP_version'])=preg_split('/ /', $head_splited[0]);
    $length=count($head_splited);
    for ($i=1;$i<$length;++$i) {
        //$header=preg_split('/: /', $head_splited[$i], 2);
        //$result['headers'][$header[0]]=$header[1];
        $result['headers'][]=$head_splited[$i];
    }
    return $result;
}

function make_request($request)
{
    if ('HTTP/1.1'!=$request['HTTP_version']) {
        die('only support HTTP/1.1');
    }
    if (!function_exists("curl_init")) {
        die('the PHP server not support curl!');
    }
    $curl_hand=curl_init();
    curl_setopt($curl_hand, CURLOPT_CUSTOMREQUEST, $request['method']);
    curl_setopt($curl_hand, CURLOPT_URL, $request['url']);
    curl_setopt($curl_hand, CURLOPT_HTTPHEADER, $request['headers']);
    //curl_setopt($curl_hand, CURLOPT_PROXY, '127.0.0.1:888');
    if ('POST'===$request['method']) {
        curl_setopt($curl_hand, CURLOPT_POSTFIELDS, $request['body']);
    }
    $response=curl_exec($curl_hand);
    curl_close($curl_hand);
    if (!$response) {
        die('network error! can\'t get response data!');
    }
    return $response;
}

if ('POST'===$_SERVER['REQUEST_METHOD']) {
    $request_stream=file_get_contents('php://input');
} else {
    die('must use POST method!');
}

$request=parse_request($request_stream);
echo make_request($request);

?>

