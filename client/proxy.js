var net = require('net');
var http = require('http');
var jQuery = require('./jquery-3.2.1.js');
debugger;
var HOST = '127.0.0.1';
var PORT = 6969;
var proxyAddress = 'http://localhost/proxy/proxy.php?XDEBUG_SESSION_START=session_name';

var server = net.createServer();
server.listen(PORT, HOST);
server.on('listening', function() {
	var serverAddress = server.address();
	console.log('Server listening on ' + serverAddress.address + ':' + serverAddress.port);
});
server.on('connection', function(user_client) {
	var request = null;
	var transClient = null;
	var transDataArray = [];
	console.log('CONNECTED: ' + user_client.remoteAddress + ':' + user_client.remotePort);
	// other stuff is the same from here

	user_client.on('data', function(data) {
		var proxyConfigReceive = '';
		transDataArray.push(data);
		var transData = function(transDataArray) {
			var data = null
			if (transClient)
				while (data = transDataArray.shift()) {
					transClient.write(data);
					//console.log('write data:\r\n' + data);
				};
		}
		if (!request) {
			request = http.get(proxyAddress, function(result) {
				result.on('data', function(data) {
					proxyConfigReceive += data;
				});
				result.on('end', function() {
					var proxy = JSON.parse(proxyConfigReceive);
					transClient = net.createConnection(proxy.port_bind, proxy.address_bind);
					transClient.on('data', function(data) {
						user_client.write(data);
						//console.log('return data:\r\n' + data);
					});
					transClient.on('close', function(data) {
						user_client.destroy();
					});
					transData(transDataArray);
				});
			});
		} else {
			transData(transDataArray);
		}
		//request.end();



		//console.log('DATA ' + user_client.remoteAddress + ': ' + data);
		// Write the data back to the user_client, the client will receive it as data from the server
		//user_client.write('You said "' + data + '"');
	});

	// Add a 'close' event handler to this instance of user_clientet
	user_client.on('close', function(data) {
		if (transClient)
			transClient.destroy();
	});

});

module.exports = server;