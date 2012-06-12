var net = require('net');
var http  = require('http');
var url = require('url');
var querystring = require('querystring');
var fs = require('fs');

// set up log file
logfile = fs.createWriteStream('/var/log/aphlict.log',
        { flags: 'a',
          encoding: null,
          mode: 0666 });
logfile.write('----- ' + (new Date()).toLocaleString() + ' -----\n');

function log(str) {
    console.log(str);
    logfile.write(str + '\n');
}


function getFlashPolicy() {
  return [
    '<?xml version="1.0"?>',
    '<!DOCTYPE cross-domain-policy SYSTEM ' +
      '"http://www.macromedia.com/xml/dtds/cross-domain-policy.dtd">',
    '<cross-domain-policy>',
    '<allow-access-from domain="*" to-ports="2600"/>',
    '</cross-domain-policy>'
  ].join('\n');
}

net.createServer(function(socket) {
  socket.on('data', function() {
    socket.write(getFlashPolicy() + '\0');
  });

  socket.on('error', function (e) {
    log('Error in policy server: ' + e);
  });
}).listen(843);



function write_json(socket, data) {
  var serial = JSON.stringify(data);
  var length = Buffer.byteLength(serial, 'utf8');
  length = length.toString();
  while (length.length < 8) {
    length = '0' + length;
  }
  socket.write(length + serial);
}


var clients = {};
var current_connections = 0;
// According to the internet up to 2^53 can
// be stored in javascript, this is less than that
var MAX_ID = 9007199254740991;//2^53 -1

// If we get one connections per millisecond this will
// be fine as long as someone doesn't maintain a
// connection for longer than 6854793 years.  If
// you want to write something pretty be my guest

function generate_id() {
  if (typeof generate_id.current_id == 'undefined'
      || generate_id.current_id > MAX_ID) {
    generate_id.current_id = 0;
  }
  return generate_id.current_id++;
}

var send_server = net.createServer(function(socket) {
  var client_id = generate_id();

  socket.on('connect', function() {
    clients[client_id] = socket;
    current_connections++;
    log(client_id + ': connected\t\t('
        + current_connections + ' current connections)');
  });

  socket.on('close', function() {
    delete clients[client_id];
    current_connections--;
    log(client_id + ': closed\t\t('
        + current_connections + ' current connections)');
  });

  socket.on('timeout', function() {
    log(client_id + ': timed out!');
  });

  socket.on('end', function() {
    log(client_id + ': ended the connection');
    // node automatically closes half-open connections
  });

  socket.on('error', function (e) {
    console.log('Uncaught error in send server: ' + e);
  });
}).listen(2600);



var receive_server = http.createServer(function(request, response) {
  response.writeHead(200, {'Content-Type' : 'text/plain'});

  if (request.method == 'POST') { // Only pay attention to POST requests
    var body = '';

    request.on('data', function (data) {
      body += data;
    });

    request.on('end', function () {
      var data = querystring.parse(body);
      log('notification: ' + JSON.stringify(data));
      broadcast(data);
      response.end();
    });
  }
}).listen(22281, '127.0.0.1');

function broadcast(data) {
  for(var client_id in clients) {
    try {
      write_json(clients[client_id], data);
      log(' wrote to client ' + client_id);
    } catch (error) {
      delete clients[client_id];
      current_connections--;
      log(' ERROR: could not write to client ' + client_id);
    }
  }
}

