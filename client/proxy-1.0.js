var net = require('net');
var http=require('http');
var jQuery=require('./jquery-3.2.1.js');
//debugger;
var HOST = '127.0.0.1';
var PORT = 6969;
var postOptions={
  hostname: 'localhost',
  port: 80,
  path: '/proxy/proxy.php?XDEBUG_SESSION_START=session_name',
  method: 'POST',
  headers: {
    'Content-Length': 0
  }
};

var server = net.createServer();
server.listen(PORT, HOST);
server.on('listening',function(){
	var serverAddress=server.address();
	console.log('Server listening on ' + serverAddress.address +':'+ serverAddress.port);
});
server.on('connection', function(socket) {
	var dataReceived='';
	var headers='';
	var method='';
    console.log('CONNECTED: ' + socket.remoteAddress +':'+ socket.remotePort);
    // other stuff is the same from here

    socket.on('data', function(data) {

    	dataReceived+=data;

    	if (7>dataReceived.length) {
    		return;
    	}

    	if(!method){
    		var methodTemp=dataReceived.substr(0,4);
    		if('GET '===methodTemp){
    			method='GET';
    		}else if('POST'===methodTemp){
    			method='POST';
    		}else if('CONN'===methodTemp){
    			if ('CONNECT'===dataReceived.substr(0,7)){
    				method='CONNECT';
    			}
    		}else{
    			socket.end();
    			return;
    		}
    	}

    	if (!headers){
    		var location1=dataReceived.indexOf('\r\n');
    		var location2=dataReceived.indexOf('\r\n\r\n');
    		if(-1===location2)
    			return;
    		headers=dataReceived.substring(location1+2,location2);
    	}
    	var options={};
    	jQuery.extend(options,postOptions,{
    		headers:{
    			'Content-Length': dataReceived.length
    		}
    	});
    	var request=http.request(options,function(result){
    		result.on('data',function(data){
    			socket.write(data);
    		})
    		result.on('end',function(){
    			socket.end();
    		})
    	})
    	request.write(dataReceived);
    	request.end();



        //console.log('DATA ' + socket.remoteAddress + ': ' + data);
        // Write the data back to the socketet, the client will receive it as data from the server
        //socket.write('You said "' + data + '"');
    });

    // Add a 'close' event handler to this instance of socketet
    socket.on('close', function(data) {
        console.log('CLOSED: ' + socket.remoteAddress +' '+ socket.remotePort);
    });
    
});

module.exports=server;


