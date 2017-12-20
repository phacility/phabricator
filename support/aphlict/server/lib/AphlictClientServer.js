'use strict';

var JX = require('./javelin').JX;

require('./AphlictListenerList');
require('./AphlictLog');

var url = require('url');
var util = require('util');
var WebSocket = require('ws');

JX.install('AphlictClientServer', {

  construct: function(server) {
    server.on('request', JX.bind(this, this._onrequest));

    this._server = server;
    this._lists = {};
    this._adminServers = [];
  },

  properties: {
    logger: null,
    adminServers: null
  },

  members: {
    _server: null,
    _lists: null,

    getListenerList: function(instance) {
      if (!this._lists[instance]) {
        this._lists[instance] = new JX.AphlictListenerList(instance);
      }
      return this._lists[instance];
    },

    getHistory: function(age) {
      var results = [];

      var servers = this.getAdminServers();
      for (var ii = 0; ii < servers.length; ii++) {
        var messages = servers[ii].getHistory(age);
        for (var jj = 0; jj < messages.length; jj++) {
          results.push(messages[jj]);
        }
      }

      return results;
    },

    log: function() {
      var logger = this.getLogger();
      if (!logger) {
        return;
      }

      logger.log.apply(logger, arguments);

      return this;
    },

    _onrequest: function(request, response) {
      // The websocket code upgrades connections before they get here, so
      // this only handles normal HTTP connections. We just fail them with
      // a 501 response.
      response.writeHead(501);
      response.end('HTTP/501 Use Websockets\n');
    },

    _parseInstanceFromPath: function(path) {
      // If there's no "~" marker in the path, it's not an instance name.
      // Users sometimes configure nginx or Apache to proxy based on the
      // path.
      if (path.indexOf('~') === -1) {
        return 'default';
      }

      var instance = path.split('~')[1];

      // Remove any "/" characters.
      instance = instance.replace(/\//g, '');
      if (!instance.length) {
        return 'default';
      }

      return instance;
    },

    listen: function() {
      var self = this;
      var server = this._server.listen.apply(this._server, arguments);
      var wss = new WebSocket.Server({server: server});

      // This function checks for upgradeReq which is only available in
      // ws2 by default, not ws3. See T12755 for more information.
      wss.on('connection', function(ws, request) {
        if ('upgradeReq' in ws) {
          request = ws.upgradeReq;
        }

        var path = url.parse(request.url).pathname;
        var instance = self._parseInstanceFromPath(path);

        var listener = self.getListenerList(instance).addListener(ws);

        function log() {
          self.log(
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

            case 'replay':
              var age = message.data.age || 60000;
              var min_age = (new Date().getTime() - age);

              var old_messages = self.getHistory(min_age);
              for (var ii = 0; ii < old_messages.length; ii++) {
                var old_message = old_messages[ii];

                if (!listener.isSubscribedToAny(old_message.subscribers)) {
                  continue;
                }

                try {
                  listener.writeMessage(old_message);
                } catch (error) {
                  break;
                }
              }
              break;

            case 'ping':
              var pong = {
                type: 'pong'
              };

              try {
                listener.writeMessage(pong);
              } catch (error) {
                // Ignore any issues here, we'll clean up elsewhere.
              }
              break;

            default:
              log(
                'Unrecognized command "%s".',
                message.command || '<undefined>');
          }
        });

        ws.on('close', function() {
          self.getListenerList(instance).removeListener(listener);
          log('Disconnected.');
        });
      });

    }
  }

});
