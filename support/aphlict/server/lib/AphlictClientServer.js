'use strict';

var JX = require('./javelin').JX;

require('./AphlictListenerList');
require('./AphlictLog');

var url = require('url');
var util = require('util');
var WebSocket = require('ws');

JX.install('AphlictClientServer', {

  construct: function(server) {
    this.setLogger(new JX.AphlictLog());
    this._server = server;
    this._lists = {};
  },

  members: {
    _server: null,
    _lists: null,

    getListenerList: function(path) {
      if (!this._lists[path]) {
        this._lists[path] = new JX.AphlictListenerList(path);
      }
      return this._lists[path];
    },

    listen: function() {
      var self = this;
      var server = this._server.listen.apply(this._server, arguments);
      var wss = new WebSocket.Server({server: server});

      wss.on('connection', function(ws) {
        var path = url.parse(ws.upgradeReq.url).pathname;
        var listener = self.getListenerList(path).addListener(ws);

        function log() {
          self.getLogger().log(
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
              log(
                'Unrecognized command "%s".',
                message.command || '<undefined>');
          }
        });

        ws.on('close', function() {
          self.getListenerList(path).removeListener(listener);
          log('Disconnected.');
        });

        wss.on('close', function() {
          self.getListenerList(path).removeListener(listener);
          log('Disconnected.');
        });

        wss.on('error', function(err) {
          log('Error: %s', err.message);
        });

      });

    },

  },

  properties: {
    logger: null,
  }

});
