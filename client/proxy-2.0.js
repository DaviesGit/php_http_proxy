var net = require('net');
var http = require('http');
var jQuery = require('./jquery-3.2.1.js');
//debugger;
var HOST = '127.0.0.1';
var PORT = 6969;
var options = {
	hostname: 'localhost',
	port: 80,
	path: '/proxy/proxy.php?XDEBUG_SESSION_START=session_name',
	method: 'CONNECT'
};

var server = net.createServer();
server.listen(PORT, HOST);
server.on('listening', function() {
	var serverAddress = server.address();
	console.log('Server listening on ' + serverAddress.address + ':' + serverAddress.port);
});
server.on('connection', function(socket) {
	var request = null;
	console.log('CONNECTED: ' + socket.remoteAddress + ':' + socket.remotePort);
	// other stuff is the same from here

	socket.on('data', function(data) {

		if (!request) {
			request = http.request(options,  bfunction(result) {
				result.on('data', function(data) {
					socket.write(data);
				})
				result.on('end', function() {
					socket.end();
				})
			})
		}
		request.write(data);
		//request.end();



		//console.log('DATA ' + socket.remoteAddress + ': ' + data);
		// Write the data back to the socketet, the client will receive it as data from the server
		//socket.write('You said "' + data + '"');
	});

	// Add a 'close' event handler to this instance of socketet
	socket.on('close', function(data) {
		console.log('CLOSED: ' + socket.remoteAddress + ' ' + socket.remotePort);
		if (request)
			request.end();
	});

});

module.exports = server;