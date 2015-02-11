'use strict';

var JX = require('./javelin').JX;

require('./AphlictListenerList');

var http = require('http');

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

    getListeners: function() {
      return this.getListenerList().getListeners();
    },

    getListenerList: function() {
      return this.getClientServer().getListenerList();
    },

    listen: function() {
      return this._server.listen.apply(this._server, arguments);
    },

    _handler: function(request, response) {
      var self = this;

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

              self.getLogger().log(
                'Received notification: ' + JSON.stringify(msg));
              ++self._messagesIn;

              try {
                self._transmit(msg);
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
      } else if (request.url == '/status/') {
        var status = {
          'uptime': (new Date().getTime() - this._startTime),
          'clients.active': this.getListenerList().getActiveListenerCount(),
          'clients.total': this.getListenerList().getTotalListenerCount(),
          'messages.in': this._messagesIn,
          'messages.out': this._messagesOut,
          'version': 6
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
    _transmit: function(message) {
      var listeners = this.getListeners().filter(function(client) {
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
          this.getListenerList().removeListener(listener);
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
