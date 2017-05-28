'use strict';

var JX = require('./lib/javelin').JX;
var http = require('http');
var https = require('https');
var util = require('util');
var fs = require('fs');

function parse_command_line_arguments(argv) {
  var args = {
    test: false,
    config: null
  };

  for (var ii = 2; ii < argv.length; ii++) {
    var arg = argv[ii];
    var matches = arg.match(/^--([^=]+)=(.*)$/);
    if (!matches) {
      throw new Error('Unknown argument "' + arg + '"!');
    }
    if (!(matches[1] in args)) {
      throw new Error('Unknown argument "' + matches[1] + '"!');
    }
    args[matches[1]] = matches[2];
  }

  return args;
}

function parse_config(args) {
  var data = fs.readFileSync(args.config);
  return JSON.parse(data);
}

require('./lib/AphlictLog');

var debug = new JX.AphlictLog()
  .addConsole(console);

var args = parse_command_line_arguments(process.argv);
var config = parse_config(args);

function set_exit_code(code) {
  process.on('exit', function() {
    process.exit(code);
  });
}

process.on('uncaughtException', function(err) {
  var context = null;
  if (err.code == 'EACCES') {
    context = util.format(
      'Unable to open file ("%s"). Check that permissions are set ' +
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
require('./lib/AphlictPeerList');
require('./lib/AphlictPeer');

var ii;

var logs = config.logs || [];
for (ii = 0; ii < logs.length; ii++) {
  debug.addLog(logs[ii].path);
}

var servers = [];
for (ii = 0; ii < config.servers.length; ii++) {
  var spec = config.servers[ii];

  spec.listen = spec.listen || '0.0.0.0';

  if (spec['ssl.key']) {
    spec['ssl.key'] = fs.readFileSync(spec['ssl.key']);
  }

  if (spec['ssl.cert']){
    spec['ssl.cert'] = fs.readFileSync(spec['ssl.cert']);
  }

  if (spec['ssl.chain']){
    spec['ssl.chain'] = fs.readFileSync(spec['ssl.chain']);
  }

  servers.push(spec);
}

// If we're just doing a configuration test, exit here before starting any
// servers.
if (args.test) {
  debug.log('Configuration test OK.');
  set_exit_code(0);
  return;
}

debug.log('Starting servers (service PID %d).', process.pid);

for (ii = 0; ii < logs.length; ii++) {
  debug.log('Logging to "%s".', logs[ii].path);
}

var aphlict_servers = [];
var aphlict_clients = [];
var aphlict_admins = [];
for (ii = 0; ii < servers.length; ii++) {
  var server = servers[ii];
  var is_client = (server.type == 'client');

  var http_server;
  if (server['ssl.key']) {
    var https_config = {
      key: server['ssl.key'],
      cert: server['ssl.cert'],
    };

    if (server['ssl.chain']) {
      https_config.ca = server['ssl.chain'];
    }

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

  debug.log(
    'Started %s server (Port %d, %s).',
    server.type,
    server.port,
    server['ssl.key'] ? 'With SSL' : 'No SSL');

  aphlict_servers.push(aphlict_server);

  if (is_client) {
    aphlict_clients.push(aphlict_server);
  } else {
    aphlict_admins.push(aphlict_server);
  }
}

var peer_list = new JX.AphlictPeerList();

debug.log(
  'This server has fingerprint "%s".',
  peer_list.getFingerprint());

var cluster = config.cluster || [];
for (ii = 0; ii < cluster.length; ii++) {
  var peer = cluster[ii];

  var peer_client = new JX.AphlictPeer()
    .setHost(peer.host)
    .setPort(peer.port)
    .setProtocol(peer.protocol);

  peer_list.addPeer(peer_client);
}

for (ii = 0; ii < aphlict_admins.length; ii++) {
  var admin_server = aphlict_admins[ii];
  admin_server.setClientServers(aphlict_clients);
  admin_server.setPeerList(peer_list);
}

for (ii = 0; ii < aphlict_clients.length; ii++) {
  var client_server = aphlict_clients[ii];
  client_server.setAdminServers(aphlict_admins);
}
