/**
 * @provides javelin-aphlict
 * @requires javelin-install
 *           javelin-util
 */

/**
 * Simple JS API for the Flash Aphlict client. Example usage:
 *
 *   var aphlict = new JX.Aphlict('aphlict_swf', '127.0.0.1', 22280)
 *     .setHandler(function(type, message) {
 *       JX.log("Got " + type + " event!")
 *     })
 *     .start();
 *
 * Your handler will receive these events:
 *
 *  - `connect` The client initiated a connection to the server.
 *  - `connected` The client completed a connection to the server.
 *  - `close` The client disconnected from the server.
 *  - `error` There was an error.
 *  - `receive` Received a message from the server.
 *
 * You do not have to handle any of them in any specific way.
 */
JX.install('Aphlict', {

  construct: function(id, server, port, subscriptions) {
    if (__DEV__) {
      if (JX.Aphlict._instance) {
        JX.$E('Aphlict object is a singleton.');
      }
    }

    this._id = id;
    this._server = server;
    this._port = port;
    this._subscriptions = subscriptions;
    this._setStatus('setup');

    JX.Aphlict._instance = this;
  },

  events: ['didChangeStatus'],

  members: {
    _id: null,
    _server: null,
    _port: null,
    _subscriptions: null,
    _status: null,
    _statusCode: null,

    start: function(node, uri) {
      this._setStatus('start');

      // NOTE: This is grotesque, but seems to work everywhere.
      node.innerHTML =
        '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000">' +
          '<param name="movie" value="' + uri + '" />' +
          '<param name="allowScriptAccess" value="always" />' +
          '<param name="wmode" value="opaque" />' +
          '<embed src="' + uri + '" wmode="opaque"' +
            'width="0" height="0" id="' + this._id + '">' +
          '</embed>' +
        '</object>';
    },

    _didStartFlash: function() {
      var id = this._id;

      // Flash puts its "objects" into global scope in an inconsistent way,
      // because it was written in like 1816 when globals were awesome and IE4
      // didn't support other scopes since global scope is the best anyway.
      var container = document[id] || window[id];

      this._flashContainer = container;
      this._flashContainer.connect(
        this._server,
        this._port,
        this._subscriptions);
    },

    getStatus: function() {
      return this._status;
    },

    getStatusCode: function() {
      return this._statusCode;
    },

    _setStatus: function(status, code) {
      this._status = status;
      this._statusCode = code || null;
      this.invoke('didChangeStatus');
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
    },

    didReceiveEvent: function(type, message) {
      var client = JX.Aphlict.getInstance();
      if (!client) {
        return;
      }

      if (type == 'status') {
        client._setStatus(message.type, message.code);
        switch (message.type) {
          case 'ready':
            client._didStartFlash();
            break;
        }
      }

      var handler = client.getHandler();
      if (handler) {
        handler(type, message);
      }
    }
  }

});
