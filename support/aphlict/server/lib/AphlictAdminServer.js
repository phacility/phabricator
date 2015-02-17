'use strict';

var JX = require('./javelin').JX;

require('./AphlictListenerList');

var http = require('http');
var url = require('url');

JX.install('AphlictAdminServer', {

  construct: function() {
    this.setLogger(new JX.AphlictLog());

    this._startTime = new Date().getTime();
    this._messagesIn = 0;
    this._messagesOut = 0;

    var handler = this._handler.bind(this);
    this._server = http.createServer(handler);
  },

  members: {
    _messagesIn: null,
    _messagesOut: null,
    _server: null,
    _startTime: null,

    getListenerList: function(instance) {
      return this.getClientServer().getListenerList(instance);
    },

    listen: function() {
      return this._server.listen.apply(this._server, arguments);
    },

    _handler: function(request, response) {
      var self = this;
      var u = url.parse(request.url, true);
      var instance = u.query.instance || '/';

      // Publishing a notification.
      if (u.pathname == '/') {
        if (request.method == 'POST') {
          var body = '';

          request.on('data', function(data) {
            body += data;
          });

          request.on('end', function() {
            try {
              var msg = JSON.parse(body);

              self.getLogger().log(
                'Received notification (' + instance + '): ' +
                JSON.stringify(msg));
              ++self._messagesIn;

              try {
                self._transmit(instance, msg);
                response.writeHead(200, {'Content-Type': 'text/plain'});
              } catch (err) {
                self.getLogger().log(
                  '<%s> Internal Server Error! %s',
                  request.socket.remoteAddress,
                  err);
                response.writeHead(500, 'Internal Server Error');
              }
            } catch (err) {
              self.getLogger().log(
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
      } else if (u.pathname == '/status/') {
        var status = {
          'instance': instance,
          'uptime': (new Date().getTime() - this._startTime),
          'clients.active': this.getListenerList(instance)
            .getActiveListenerCount(),
          'clients.total': this.getListenerList(instance)
            .getTotalListenerCount(),
          'messages.in': this._messagesIn,
          'messages.out': this._messagesOut,
          'version': 7
        };

        response.writeHead(200, {'Content-Type': 'application/json'});
        response.write(JSON.stringify(status));
        response.end();
      } else {
        response.writeHead(404, 'Not Found');
        response.end();
      }
    },

    /**
     * Transmits a message to all subscribed listeners.
     */
    _transmit: function(instance, message) {
      var listeners = this.getListenerList(instance)
        .getListeners()
        .filter(function(client) {
          return client.isSubscribedToAny(message.subscribers);
        });

      for (var i = 0; i < listeners.length; i++) {
        var listener = listeners[i];

        try {
          listener.writeMessage(message);

          ++this._messagesOut;
          this.getLogger().log(
            '<%s> Wrote Message',
            listener.getDescription());
        } catch (error) {
          this.getListenerList(instance).removeListener(listener);
          this.getLogger().log(
            '<%s> Write Error: %s',
            listener.getDescription(),
            error);
        }
      }
    },
  },

  properties: {
    clientServer: null,
    logger: null,
  }

});
