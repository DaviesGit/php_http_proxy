<?php




// Close the master sockets
socket_close($sock);

function request($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return $response;
}

$response=request("http://baidu.com");
echo json_encode($response);


function sendto_socket($data, $port)
{
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo "Failed: ". socket_strerror(socket_last_error($socket))."\n";
    }
    $result = socket_connect($socket, $address, $service_port);

    if ($result === false) {
        echo "Failed: ". socket_strerror(socket_last_error($socket))."\n";
    }

    socket_send($socket, $data, strlen($data), MSG_WAITALL);

    socket_close($socket);
}

$query = $_GET['query'];
echo $query;
error_reporting(E_ALL);
$service_port = 4000;
$address = "localhost";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "Failed: ". socket_strerror(socket_last_error($socket))."\n";
}

echo "Attempting to connect to '$address' on port '$service_port'...'\n";
$result = socket_connect($socket, $address, $service_port);

if ($result === false) {
    echo "Failed: ". socket_strerror(socket_last_error($socket))."\n";
}

$in = $query;
$out = '';

echo "Sending...\n";
socket_send($socket, $in, strlen($in), MSG_WAITALL);
echo "OK.\n";

echo "Reading response:\n\n";
while ($out = socket_read($socket, 2048)) {
    echo $out;
}

socket_close($socket);



// $method=$_SERVER['REQUEST_METHOD'];
// switch ($method) {
//     case 'GET':
//         $requestStream.='GET ';
//         break;
//     case 'POST':
//         $requestStream.='POST ';
//         break;
//     default:
//         die('this request type is not supported!');
//         break;
// }
// $requestStream.=$_SERVER['REQUEST_URI'];
// $requestStream.=" HTTP/1.1\r\n";
// $headers=apache_request_headers();
// foreach ($headers as $key => $value) {
//     $requestStream.="{$key}: {$value}\r\n";
// }
// $requestStream.="\r\n";
