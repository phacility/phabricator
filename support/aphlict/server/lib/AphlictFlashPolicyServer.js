var JX = require('javelin').JX;

var net = require('net');

/**
 * Server which handles cross-domain policy requests for Flash.
 *
 *   var server = new AphlictFlashPolicyServer()
 *     .setAccessPort(9999)
 *     .start();
 */
JX.install('AphlictFlashPolicyServer', {

  members: {
    _server: null,
    _port: 843,
    _accessPort: null,
    _debug: null,

    setDebugLog : function(log) {
      this._debug = log;
      return this;
    },

    setAccessPort : function(port) {
      this._accessPort = port;
      return this;
    },

    start: function() {
      this._server = net.createServer(JX.bind(this, this._didConnect));
      this._server.listen(this._port);
      return this;
    },

    _didConnect: function(socket) {
      this._log('<FlashPolicy> Policy Request From %s', socket.remoteAddress);

      socket.on('error', JX.bind(this, this._didSocketError, socket));

      socket.write(this._getFlashPolicyResponse());
      socket.end();
    },

    _didSocketError: function(socket, error) {
      this._log('<FlashPolicy> Socket Error: %s', error);
    },

    _log: function(pattern) {
      this._debug && this._debug.log.apply(this._debug, arguments);
    },

    _getFlashPolicyResponse: function() {
      var policy = [
        '<?xml version="1.0"?>',
        '<!DOCTYPE cross-domain-policy SYSTEM ' +
          '"http://www.macromedia.com/xml/dtds/cross-domain-policy.dtd">',
        '<cross-domain-policy>',
          '<allow-access-from domain="*" to-ports="' + this._accessPort + '"/>',
        '</cross-domain-policy>'
      ];

      return policy.join("\n") + "\0";
    }

  }

});
