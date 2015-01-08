var JX = require('./lib/javelin').JX;
var http = require('http');
var https = require('https');
var util = require('util');
var fs = require('fs');

JX.require('lib/AphlictListenerList', __dirname);
JX.require('lib/AphlictLog', __dirname);

function parse_command_line_arguments(argv) {
  var config = {
    port: 22280,
    admin: 22281,
    host: '127.0.0.1',
    log: '/var/log/aphlict.log',
    'ssl-key': null,
    'ssl-certificate': null,
    test: false
  };

  for (var ii = 2; ii < argv.length; ii++) {
    var arg = argv[ii];
    var matches = arg.match(/^--([^=]+)=(.*)$/);
    if (!matches) {
      throw new Error("Unknown argument '" + arg + "'!");
    }
    if (!(matches[1] in config)) {
      throw new Error("Unknown argument '" + matches[1] + "'!");
    }
    config[matches[1]] = matches[2];
  }

  config.port = parseInt(config.port, 10);
  config.admin = parseInt(config.admin, 10);

  return config;
}

var debug = new JX.AphlictLog()
  .addConsole(console);

var config = parse_command_line_arguments(process.argv);

process.on('uncaughtException', function(err) {
  debug.log('\n<<< UNCAUGHT EXCEPTION! >>>\n' + err.stack);
  process.exit(1);
});

var WebSocket;
try {
  WebSocket = require('ws');
} catch (ex) {
  throw new Error(
    'You need to install the Node.js "ws" module for websocket support. ' +
    'Usually, you can do this with `npm install -g ws`. ' + ex.toString());
}

var ssl_config = {
  enabled: (config['ssl-key'] || config['ssl-cert'])
};

// Load the SSL certificates (if any were provided) now, so that runs with
// `--test` will see any errors.
if (ssl_config.enabled) {
  ssl_config.key = fs.readFileSync(config['ssl-key']);
  ssl_config.cert = fs.readFileSync(config['ssl-cert']);
}

// Add the logfile so we'll fail if we can't write to it.
if (config.logfile) {
  debug.addLogfile(config.logfile);
}

// If we're just doing a configuration test, exit here before starting any
// servers.
if (config.test) {
  debug.log('Configuration test OK.');
  process.exit(0);
}

var start_time = new Date().getTime();
var messages_out = 0;
var messages_in = 0;

var clients = new JX.AphlictListenerList();

function https_discard_handler(req, res) {
  res.writeHead(501);
  res.end('HTTP/501 Use Websockets\n');
}

var ws;
if (ssl_config.enabled) {
  var https_server = https.createServer({
    key: ssl_config.key,
    cert: ssl_config.cert
  }, https_discard_handler).listen(config.port);

  ws = new WebSocket.Server({server: https_server});
} else {
  ws = new WebSocket.Server({port: config.port});
}

ws.on('connection', function(ws) {
  var listener = clients.addListener(ws);

  function log() {
    debug.log(
      util.format('<%s>', listener.getDescription()) +
      ' ' +
      util.format.apply(null, arguments));
  }

  log('Connected from %s.', ws._socket.remoteAddress);

  ws.on('message', function(data) {
    log('Received message: %s', data);

    var message;
    try {
      message = JSON.parse(data);
    } catch (err) {
      log('Message is invalid: %s', err.message);
      return;
    }

    switch (message.command) {
      case 'subscribe':
        log(
          'Subscribed to: %s',
          JSON.stringify(message.data));
        listener.subscribe(message.data);
        break;

      case 'unsubscribe':
        log(
          'Unsubscribed from: %s',
          JSON.stringify(message.data));
        listener.unsubscribe(message.data);
        break;

      default:
        log('Unrecognized command "%s".', message.command || '<undefined>');
    }
  });

  ws.on('close', function() {
    clients.removeListener(listener);
    log('Disconnected.');
  });

  ws.on('error', function(err) {
    log('Error: %s', err.message);
  });
});

function transmit(msg) {
  var listeners = clients.getListeners().filter(function(client) {
    return client.isSubscribedToAny(msg.subscribers);
  });

  for (var i = 0; i < listeners.length; i++) {
    var listener = listeners[i];

    try {
      listener.writeMessage(msg);

      ++messages_out;
      debug.log('<%s> Wrote Message', listener.getDescription());
    } catch (error) {
      clients.removeListener(listener);
      debug.log('<%s> Write Error: %s', listener.getDescription(), error);
    }
  }
}

http.createServer(function(request, response) {
  // Publishing a notification.
  if (request.url == '/') {
    if (request.method == 'POST') {
      var body = '';

      request.on('data', function(data) {
        body += data;
      });

      request.on('end', function() {
        try {
          var msg = JSON.parse(body);

          debug.log('Received notification: ' + JSON.stringify(msg));
          ++messages_in;

          try {
            transmit(msg);
            response.writeHead(200, {'Content-Type': 'text/plain'});
          } catch (err) {
            debug.log(
              '<%s> Internal Server Error! %s',
              request.socket.remoteAddress,
              err);
            response.writeHead(500, 'Internal Server Error');
          }
        } catch (err) {
          debug.log(
            '<%s> Bad Request! %s',
            request.socket.remoteAddress,
            err);
          response.writeHead(400, 'Bad Request');
        } finally {
          response.end();
        }
      });
    } else {
      response.writeHead(405, 'Method Not Allowed');
      response.end();
    }
  } else if (request.url == '/status/') {
    var status = {
      'uptime': (new Date().getTime() - start_time),
      'clients.active': clients.getActiveListenerCount(),
      'clients.total': clients.getTotalListenerCount(),
      'messages.in': messages_in,
      'messages.out': messages_out,
      'log': config.log,
      'version': 6
    };

    response.writeHead(200, {'Content-Type': 'application/json'});
    response.write(JSON.stringify(status));
    response.end();
  } else {
    response.writeHead(404, 'Not Found');
    response.end();
  }
}).listen(config.admin, config.host);

debug.log('Started Server (PID %d)', process.pid);
