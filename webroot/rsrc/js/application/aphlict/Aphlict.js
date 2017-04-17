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

    JX.Aphlict._instance = this;
  },

  events: ['didChangeStatus'],

  members: {
    _uri: null,
    _socket: null,
    _subscriptions: null,
    _status: null,
    _isReconnect: false,

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
    },

    _didReconnect: function() {
      this.replay();
      this.reconnect();
    },

    replay: function() {
      var replay = {
        age: 60000
      };

      JX.Leader.broadcast(null, {type: 'aphlict.replay', data: replay});
    },

    reconnect: function() {
      JX.Leader.broadcast(null, {type: 'aphlict.reconnect', data: null});
    },

    _close: function() {
      this._broadcastStatus('closed');
    },

    _broadcastStatus: function(status) {
      JX.Leader.broadcast(null, {type: 'aphlict.status', data: status});
    },

    _message: function(raw) {
      var message = JX.JSON.parse(raw);
      var id = message.uniqueID || null;

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
