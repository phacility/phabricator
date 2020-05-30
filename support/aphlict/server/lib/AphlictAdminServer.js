'use strict';

var JX = require('./javelin').JX;

require('./AphlictListenerList');

var url = require('url');

JX.install('AphlictAdminServer', {

  construct: function(server) {
    this._startTime = new Date().getTime();
    this._messagesIn = 0;
    this._messagesOut = 0;

    server.on('request', JX.bind(this, this._onrequest));
    this._server = server;
    this._clientServers = [];
    this._messageHistory = [];
  },

  properties: {
    clientServers: null,
    logger: null,
    peerList: null
  },

  members: {
    _messagesIn: null,
    _messagesOut: null,
    _server: null,
    _startTime: null,
    _messageHistory: null,

    getListenerLists: function(instance) {
      var clients = this.getClientServers();

      var lists = [];
      for (var ii = 0; ii < clients.length; ii++) {
        lists.push(clients[ii].getListenerList(instance));
      }
      return lists;
    },

    log: function() {
      var logger = this.getLogger();
      if (!logger) {
        return;
      }

      logger.log.apply(logger, arguments);

      return this;
    },

    trace: function() {
      var logger = this.getLogger();
      if (!logger) {
        return;
      }

      logger.trace.apply(logger, arguments);

      return this;
    },

    listen: function() {
      return this._server.listen.apply(this._server, arguments);
    },

    _onrequest: function(request, response) {
      var self = this;
      var u = url.parse(request.url, true);
      var instance = u.query.instance || 'default';

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

              self.trace(
                'Received notification (' + instance + '): ' +
                JSON.stringify(msg));
              ++self._messagesIn;

              try {
                self._transmit(instance, msg, response);
              } catch (err) {
                self.log(
                  '<%s> Internal Server Error! %s',
                  request.socket.remoteAddress,
                  err);
                response.writeHead(500, 'Internal Server Error');
              }
            } catch (err) {
              self.log(
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
        this._handleStatusRequest(request, response, instance);
      } else {
        response.writeHead(404, 'Not Found');
        response.end();
      }
    },

    _handleStatusRequest: function(request, response, instance) {
      var active_count = 0;
      var total_count = 0;

      var lists = this.getListenerLists(instance);
      for (var ii = 0; ii < lists.length; ii++) {
        var list = lists[ii];
        active_count += list.getActiveListenerCount();
        total_count += list.getTotalListenerCount();
      }

      var now = new Date().getTime();

      var history_size = this._messageHistory.length;
      var history_age = null;
      if (history_size) {
        history_age = (now - this._messageHistory[0].timestamp);
      }

      var server_status = {
        'instance': instance,
        'uptime': (now - this._startTime),
        'clients.active': active_count,
        'clients.total': total_count,
        'messages.in': this._messagesIn,
        'messages.out': this._messagesOut,
        'version': 7,
        'history.size': history_size,
        'history.age': history_age
      };

      response.writeHead(200, {'Content-Type': 'application/json'});
      response.write(JSON.stringify(server_status));
      response.end();
    },

    /**
     * Transmits a message to all subscribed listeners.
     */
    _transmit: function(instance, message, response) {
      var now = new Date().getTime();

      this._messageHistory.push(
        {
          timestamp: now,
          message: message
        });

      this._purgeHistory();

      var peer_list = this.getPeerList();

      message = peer_list.addFingerprint(message);
      if (message) {
        var lists = this.getListenerLists(instance);

        for (var ii = 0; ii < lists.length; ii++) {
          var list = lists[ii];
          var listeners = list.getListeners();
          this._transmitToListeners(list, listeners, message);
        }

        peer_list.broadcastMessage(instance, message);
      }

      // Respond to the caller with our fingerprint so it can stop sending
      // us traffic we don't need to know about if it's a peer. In particular,
      // this stops us from broadcasting messages to ourselves if we appear
      // in the cluster list.
      var receipt = {
        fingerprint: this.getPeerList().getFingerprint()
      };

      response.writeHead(200, {'Content-Type': 'application/json'});
      response.write(JSON.stringify(receipt));
    },

    _transmitToListeners: function(list, listeners, message) {
      for (var ii = 0; ii < listeners.length; ii++) {
        var listener = listeners[ii];

        if (!listener.isSubscribedToAny(message.subscribers)) {
          continue;
        }

        try {
          listener.writeMessage(message);

          ++this._messagesOut;
          this.trace(
            '<%s> Wrote Message',
            listener.getDescription());
        } catch (error) {
          list.removeListener(listener);

          this.trace(
            '<%s> Write Error: %s',
            listener.getDescription(),
            error);
        }
      }
    },

    getHistory: function(min_age) {
      var history = this._messageHistory;
      var results = [];

      for (var ii = 0; ii < history.length; ii++) {
        if (history[ii].timestamp >= min_age) {
          results.push(history[ii].message);
        }
      }

      return results;
    },

    _purgeHistory: function() {
      var messages = this._messageHistory;

      // Maximum number of messages to retain.
      var size_limit = 4096;

      // Find the index of the first item we're going to keep. If we have too
      // many items, this will be somewhere past the beginning of the list.
      var keep = Math.max(0, messages.length - size_limit);

      // Maximum number of milliseconds of history to retain.
      var age_limit = 60000;

      // Move the index forward until we find an item that is recent enough
      // to retain.
      var now = new Date().getTime();
      var min_age = (now - age_limit);
      for (keep; keep < messages.length; keep++) {
        if (messages[keep].timestamp >= min_age) {
          break;
        }
      }

      // Throw away extra messages.
      if (keep) {
        this._messageHistory.splice(0, keep);
      }
    }

  }

});
