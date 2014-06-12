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

  construct : function(id, server, port, subscriptions) {
    if (__DEV__) {
      if (JX.Aphlict._instance) {
        JX.$E('Aphlict object is sort of a singleton..!');
      }
    }

    JX.Aphlict._instance = this;

    this._server = server;
    this._port = port;
    this._subscriptions = subscriptions;

    // Flash puts its "objects" into global scope in an inconsistent way,
    // because it was written in like 1816 when globals were awesome and IE4
    // didn't support other scopes since global scope is the best anyway.
    var container = document[id] || window[id];

    this._flashContainer = container;
  },

  members : {
    _server : null,
    _port : null,
    _subscriptions : null,
    start : function() {
      this._flashContainer.connect(
        this._server,
        this._port,
        this._subscriptions);
    }
  },

  properties : {
    handler : null
  },

  statics : {
    _instance : null,
    didReceiveEvent : function(type, message) {
      if (!JX.Aphlict._instance) {
        return;
      }

      var handler = JX.Aphlict._instance.getHandler();
      if (handler) {
        handler(type, message);
      }
    }
  }

});
