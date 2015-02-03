'use strict';

var JX = require('./javelin').JX;

require('./AphlictListenerList');
require('./AphlictLog');

var util = require('util');
var WebSocket = require('ws');

JX.install('AphlictClientServer', {

  construct: function(server) {
    this.setListenerList(new JX.AphlictListenerList());
    this.setLogger(new JX.AphlictLog());
    this._server = server;
  },

  members: {
    _server: null,

    listen: function() {
      var self = this;
      var server = this._server.listen.apply(this._server, arguments);
      var wss = new WebSocket.Server({server: server});

      wss.on('connection', function(ws) {
        var listener = self.getListenerList().addListener(ws);

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
          self.getListenerList().removeListener(listener);
          log('Disconnected.');
        });

        wss.on('close', function() {
          self.getListenerList().removeListener(listener);
          log('Disconnected.');
        });

        wss.on('error', function(err) {
          log('Error: %s', err.message);
        });

      });

    },

  },

  properties: {
    listenerList: null,
    logger: null,
  }

});
