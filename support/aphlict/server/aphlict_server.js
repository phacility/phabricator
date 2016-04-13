'use strict';

var JX = require('./lib/javelin').JX;
var http = require('http');
var https = require('https');
var util = require('util');
var fs = require('fs');

function parse_command_line_arguments(argv) {
  var config = {
    'client-port': 22280,
    'admin-port': 22281,
    'client-host': '0.0.0.0',
    'admin-host': '127.0.0.1',
    log: '/var/log/aphlict.log',
    'ssl-key': null,
    'ssl-cert': null,
    test: false
  };

  for (var ii = 2; ii < argv.length; ii++) {
    var arg = argv[ii];
    var matches = arg.match(/^--([^=]+)=(.*)$/);
    if (!matches) {
      throw new Error('Unknown argument "' + arg + '"!');
    }
    if (!(matches[1] in config)) {
      throw new Error('Unknown argument "' + matches[1] + '"!');
    }
    config[matches[1]] = matches[2];
  }

  config['client-port'] = parseInt(config['client-port'], 10);
  config['admin-port'] = parseInt(config['admin-port'], 10);

  return config;
}

require('./lib/AphlictLog');

var debug = new JX.AphlictLog()
  .addConsole(console);

var config = parse_command_line_arguments(process.argv);

function set_exit_code(code) {
  process.on('exit', function() {
    process.exit(code);
  });
}

process.on('uncaughtException', function(err) {
  var context = null;
  if (err.code == 'EACCES' && err.path == config.log) {
    context = util.format(
      'Unable to open logfile ("%s"). Check that permissions are set ' +
      'correctly.',
      err.path);
  }

  var message = [
    '\n<<< UNCAUGHT EXCEPTION! >>>',
  ];
  if (context) {
    message.push(context);
  }
  message.push(err.stack);

  debug.log(message.join('\n\n'));
  set_exit_code(1);
});

// Add the logfile so we'll fail if we can't write to it.
if (config.log) {
  debug.addLog(config.log);
}

try {
  require('ws');
} catch (ex) {
  throw new Error(
    'You need to install the Node.js "ws" module for websocket support. ' +
    'See "Notifications User Guide: Setup and Configuration" in the ' +
    'documentation for instructions. ' + ex.toString());
}

// NOTE: Require these only after checking for the "ws" module, since they
// depend on it.

require('./lib/AphlictAdminServer');
require('./lib/AphlictClientServer');

var ssl_config = {
  enabled: (config['ssl-key'] || config['ssl-cert'])
};

// Load the SSL certificates (if any were provided) now, so that runs with
// `--test` will see any errors.
if (ssl_config.enabled) {
  ssl_config.key = fs.readFileSync(config['ssl-key']);
  ssl_config.cert = fs.readFileSync(config['ssl-cert']);
} else {
  ssl_config.key = null;
  ssl_config.cert = null;
}

var servers = [];

servers.push({
  type: 'client',
  port: config['client-port'],
  listen: config['client-host'],
  'ssl.key': ssl_config.key,
  'ssl.certificate': ssl_config.cert
});

servers.push({
  type: 'admin',
  port: config['admin-port'],
  listen: config['admin-host'],
  'ssl.key': null,
  'ssl.cert': null
});

// If we're just doing a configuration test, exit here before starting any
// servers.
if (config.test) {
  debug.log('Configuration test OK.');
  set_exit_code(0);
  return;
}

var aphlict_servers = [];
var aphlict_clients = [];
var aphlict_admins = [];

var ii;
for (ii = 0; ii < servers.length; ii++) {
  var server = servers[ii];
  var is_client = (server.type == 'client');

  var http_server;
  if (server['ssl.key']) {
    var https_config = {
      key: server['ssl.key'],
      cert: server['ssl.cert']
    };

    http_server = https.createServer(https_config);
  } else {
    http_server = http.createServer();
  }

  var aphlict_server;
  if (is_client) {
    aphlict_server = new JX.AphlictClientServer(http_server);
  } else {
    aphlict_server = new JX.AphlictAdminServer(http_server);
  }

  aphlict_server.setLogger(debug);
  aphlict_server.listen(server.port, server.listen);

  aphlict_servers.push(aphlict_server);

  if (is_client) {
    aphlict_clients.push(aphlict_server);
  } else {
    aphlict_admins.push(aphlict_server);
  }
}

for (ii = 0; ii < aphlict_admins.length; ii++) {
  var admin_server = aphlict_admins[ii];
  admin_server.setClientServers(aphlict_clients);
}

debug.log('Started Server (PID %d)', process.pid);
