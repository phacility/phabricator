/**
 * Notification server. Launch with:
 *
 *   sudo node aphlict_server.js --user=aphlict
 *
 * You can also specify `port`, `admin`, `host` and `log`.
 */

var config = parse_command_line_arguments(process.argv);

function parse_command_line_arguments(argv) {
  var config = {
    port : 22280,
    admin : 22281,
    host : '127.0.0.1',
    user : null,
    log: '/var/log/aphlict.log'
  };

  for (var ii = 2; ii < argv.length; ii++) {
    var arg = argv[ii];
    var matches = arg.match(/^--([^=]+)=(.*)$/);
    if (!matches) {
      throw new Error("Unknown argument '"+arg+"'!");
    }
    if (!(matches[1] in config)) {
      throw new Error("Unknown argument '"+matches[1]+"'!");
    }
    config[matches[1]] = matches[2];
  }

  config.port = parseInt(config.port, 10);
  config.admin = parseInt(config.admin, 10);

  return config;
}

if (process.getuid() != 0) {
  console.log(
    "ERROR: "+
    "This server must be run as root because it needs to bind to privileged "+
    "port 843 to start a Flash policy server. It will downgrade to run as a "+
    "less-privileged user after binding if you pass a user in the command "+
    "line arguments with '--user=alincoln'.");
  process.exit(1);
}

var net = require('net');
var http  = require('http');
var url = require('url');
var querystring = require('querystring');
var fs = require('fs');

// set up log file
var logfile = fs.createWriteStream(
  config.log,
  {
    flags: 'a',
    encoding: null,
    mode: 0666
  });

function log(str) {
  console.log(str);
  logfile.write(str + '\n');
}

process.on('uncaughtException', function (err) {
  log("\n<<< UNCAUGHT EXCEPTION! >>>\n\n" + err);
  process.exit(1);
});

log('----- ' + (new Date()).toLocaleString() + ' -----\n');

function getFlashPolicy() {
  return [
    '<?xml version="1.0"?>',
    '<!DOCTYPE cross-domain-policy SYSTEM ' +
      '"http://www.macromedia.com/xml/dtds/cross-domain-policy.dtd">',
    '<cross-domain-policy>',
    '<allow-access-from domain="*" to-ports="'+config.port+'"/>',
    '</cross-domain-policy>'
  ].join('\n');
}

net.createServer(function(socket) {
  socket.write(getFlashPolicy() + '\0');
  socket.end();

  log('[' + socket.remoteAddress + '] Sent Flash Policy');

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
  var client_name = '[' + socket.remoteAddress + '] [#' + client_id + '] ';

  socket.on('connect', function() {
    clients[client_id] = socket;
    current_connections++;
    log(client_name + 'connected\t\t('
        + current_connections + ' current connections)');
  });

  socket.on('close', function() {
    delete clients[client_id];
    current_connections--;
    log(client_name + 'closed\t\t('
        + current_connections + ' current connections)');
  });

  socket.on('timeout', function() {
    log(client_name + 'timed out!');
  });

  socket.on('end', function() {
    log(client_name + 'ended the connection');
    // node automatically closes half-open connections
  });

  socket.on('error', function (e) {
    log(cliient_name + 'Uncaught error in send server: ' + e);
  });
}).listen(config.port);


var messages_out = 0;
var messages_in = 0;
var start_time = new Date().getTime();

var receive_server = http.createServer(function(request, response) {
  response.writeHead(200, {'Content-Type' : 'text/plain'});

  // Publishing a notification.
  if (request.method == 'POST') {
    var body = '';

    request.on('data', function (data) {
      body += data;
    });

    request.on('end', function () {
      ++messages_in;

      var data = querystring.parse(body);
      log('notification: ' + JSON.stringify(data));
      broadcast(data);
      response.end();
    });
  } else if (request.url == '/status/') {
    request.on('data', function(data) {
      // We just ignore the request data, but newer versions of Node don't
      // get to 'end' if we don't process the data. See T2953.
    });

    request.on('end', function() {
      var status = {
        'uptime': (new Date().getTime() - start_time),
        'clients.active': current_connections,
        'clients.total': generate_id.current_id || 0,
        'messages.in': messages_in,
        'messages.out': messages_out,
        'log': config.log
      };

      response.write(JSON.stringify(status));
      response.end();
    });
  } else {
    response.statusCode = 400;
    response.write('400 Bad Request');
    response.end();
  }

}).listen(config.admin, config.host);

function broadcast(data) {
  for (var client_id in clients) {
    try {
      write_json(clients[client_id], data);
      ++messages_out;
      log('wrote to client ' + client_id);
    } catch (error) {
      delete clients[client_id];
      current_connections--;
      log('ERROR: could not write to client ' + client_id);
    }
  }
}

// If we're configured to drop permissions, get rid of them now that we've
// bound to the ports we need and opened logfiles.
if (config.user) {
  process.setuid(config.user);
}

