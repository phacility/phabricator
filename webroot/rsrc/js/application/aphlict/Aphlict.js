/**
 * @provides javelin-aphlict
 * @requires javelin-install
 *           javelin-util
 *           javelin-websocket
 *           javelin-leader
 *           javelin-json
 */

/**
 * Client for the notification server. Example usage:
 *
 *   var aphlict = new JX.Aphlict('ws://localhost:22280', subscriptions)
 *     .setHandler(function(message) {
 *       // ...
 *     })
 *     .start();
 *
 */
JX.install('Aphlict', {

  construct: function(uri, subscriptions) {
    if (__DEV__) {
      if (JX.Aphlict._instance) {
        JX.$E('Aphlict object is a singleton.');
      }
    }

    this._uri = uri;
    this._subscriptions = subscriptions;
    this._setStatus('setup');
    this._startTime = new Date().getTime();

    JX.Aphlict._instance = this;
  },

  events: ['didChangeStatus'],

  members: {
    _uri: null,
    _socket: null,
    _subscriptions: null,
    _status: null,
    _isReconnect: false,
    _keepaliveInterval: false,
    _startTime: null,

    start: function() {
      JX.Leader.listen('onBecomeLeader', JX.bind(this, this._lead));
      JX.Leader.listen('onReceiveBroadcast', JX.bind(this, this._receive));
      JX.Leader.start();

      JX.Leader.call(JX.bind(this, this._begin));
    },

    getSubscriptions: function() {
      return this._subscriptions;
    },

    setSubscriptions: function(subscriptions) {
      this._subscriptions = subscriptions;
      JX.Leader.broadcast(
        null,
        {type: 'aphlict.subscribe', data: this._subscriptions});
    },

    clearSubscriptions: function(subscriptions) {
      this._subscriptions = null;
      JX.Leader.broadcast(
        null,
        {type: 'aphlict.unsubscribe', data: subscriptions});
    },

    getStatus: function() {
      return this._status;
    },

    getWebsocket: function() {
      return this._socket;
    },

    _begin: function() {
      JX.Leader.broadcast(
        null,
        {type: 'aphlict.getstatus'});
      JX.Leader.broadcast(
        null,
        {type: 'aphlict.subscribe', data: this._subscriptions});
    },

    _lead: function() {
      this._socket = new JX.WebSocket(this._uri);
      this._socket.setOpenHandler(JX.bind(this, this._open));
      this._socket.setMessageHandler(JX.bind(this, this._message));
      this._socket.setCloseHandler(JX.bind(this, this._close));

      this._socket.open();
    },

    _open: function() {
      // If this is a reconnect, ask the server to replay recent messages
      // after other tabs have had a chance to subscribe. Do this before we
      // broadcast that the connection status is now open.
      if (this._isReconnect) {
        setTimeout(JX.bind(this, this._didReconnect), 100);
      }

      this._broadcastStatus('open');
      JX.Leader.broadcast(null, {type: 'aphlict.getsubscribers'});

      // By default, ELBs terminate connections after 60 seconds with no
      // traffic. Other load balancers may have similar configuration. Send
      // a keepalive message every 15 seconds to prevent load balancers from
      // deciding they can reap this connection.

      var keepalive = JX.bind(this, this._keepalive);
      this._keepaliveInterval = setInterval(keepalive, 15000);
    },

    _didReconnect: function() {
      this.replay();
      this.reconnect();
    },

    replay: function() {
      var age = 60000;

      // If the page was loaded a few moments ago, only query for recent
      // history. This keeps us from replaying events over and over again as
      // a user browses normally.

      // Allow a small margin of error for the actual page load time. It's
      // also fine to replay a notification which the user saw for a brief
      // moment on the previous page.
      var extra_time = 500;
      var now = new Date().getTime();

      age = Math.min(extra_time + (now - this._startTime), age);

      var replay = {
        age: age
      };

      JX.Leader.broadcast(null, {type: 'aphlict.replay', data: replay});
    },

    reconnect: function() {
      JX.Leader.broadcast(null, {type: 'aphlict.reconnect', data: null});
    },

    _close: function() {
      if (this._keepaliveInterval) {
        clearInterval(this._keepaliveInterval);
        this._keepaliveInterval = null;
      }

      this._broadcastStatus('closed');
    },

    _broadcastStatus: function(status) {
      JX.Leader.broadcast(null, {type: 'aphlict.status', data: status});
    },

    _message: function(raw) {
      var message = JX.JSON.parse(raw);
      var id = message.uniqueID || null;

      // If this is just a keepalive response, don't bother broadcasting it.
      if (message.type == 'pong') {
        return;
      }

      JX.Leader.broadcast(id, {type: 'aphlict.server', data: message});
    },

    _receive: function(message, is_leader) {
      switch (message.type) {
        case 'aphlict.status':
          this._setStatus(message.data);
          break;

        case 'aphlict.getstatus':
          if (is_leader) {
            this._broadcastStatus(this.getStatus());
          }
          break;

        case 'aphlict.getsubscribers':
          JX.Leader.broadcast(
            null,
            {type: 'aphlict.subscribe', data: this._subscriptions});
          break;

        case 'aphlict.subscribe':
          if (is_leader) {
            this._writeCommand('subscribe', message.data);
          }
          break;

        case 'aphlict.replay':
          if (is_leader) {
            this._writeCommand('replay', message.data);
          }
          break;

        default:
          var handler = this.getHandler();
          handler && handler(message);
          break;
      }
    },

    _setStatus: function(status) {
      this._status = status;

      // If we've ever seen an open connection, any new connection we make
      // is a reconnect and should replay history.
      if (status == 'open') {
        this._isReconnect = true;
      }

      this.invoke('didChangeStatus');
    },

    _write: function(message) {
      this._socket.send(JX.JSON.stringify(message));
    },

    _writeCommand: function(command, message) {
      var frame = {
        command: command,
        data: message
      };

      return this._write(frame);
    },

    _keepalive: function() {
      this._writeCommand('ping', null);
    }

  },

  properties: {
    handler: null
  },

  statics: {
    _instance: null,

    getInstance: function() {
      var self = JX.Aphlict;
      if (!self._instance) {
        return null;
      }
      return self._instance;
    }

  }

});
