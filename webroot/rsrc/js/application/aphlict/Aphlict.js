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
      this._broadcastStatus('open');
      JX.Leader.broadcast(null, {type: 'aphlict.getsubscribers'});
    },

    _close: function() {
      this._broadcastStatus('closed');
    },

    _broadcastStatus: function(status) {
      JX.Leader.broadcast(null, {type: 'aphlict.status', data: status});
    },

    _message: function(raw) {
      var message = JX.JSON.parse(raw);
      JX.Leader.broadcast(null, {type: 'aphlict.server', data: message});
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
            this._write({
              command: 'subscribe',
              data: message.data
            });
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
      this.invoke('didChangeStatus');
    },

    _write: function(message) {
      this._socket.send(JX.JSON.stringify(message));
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
