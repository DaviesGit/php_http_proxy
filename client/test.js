

alert(1)

console.log('--00');

var net = require('net');

console.log('00');

console.log('11');




var http = require('http');
var proxyAddress='http://localhost/proxy/proxy.php?XDEBUG_SESSION_START=session_name';

http.get(proxyAddress,()=>{alert()});

//require("P:\\Works\\Nodejs\\proxy\\test.js")
//server=require("P:\\Works\\Nodejs\\proxy\\proxy.js")