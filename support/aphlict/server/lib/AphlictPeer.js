'use strict';

var JX = require('./javelin').JX;

var http = require('http');
var https = require('https');

JX.install('AphlictPeer', {

  construct: function() {
  },

  properties: {
    host: null,
    port: null,
    protocol: null,
    fingerprint: null
  },

  members: {
    broadcastMessage: function(instance, message) {
      var data;
      try {
        data = JSON.stringify(message);
      } catch (error) {
        return;
      }

      // TODO: Maybe use "agent" stuff to pool connections?

      var options = {
        hostname: this.getHost(),
        port: this.getPort(),
        method: 'POST',
        path: '/?instance=' + instance,
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': data.length
        }
      };

      var onresponse = JX.bind(this, this._onresponse);

      var request;
      if (this.getProtocol() == 'https') {
        request = https.request(options, onresponse);
      } else {
        request = http.request(options, onresponse);
      }

      request.write(data);
      request.end();
    },

    _onresponse: function(response) {
      var peer = this;
      var data = '';

      response.on('data', function(bytes) {
        data += bytes;
      });

      response.on('end', function() {
        var message;
        try {
          message = JSON.parse(data);
        } catch (error) {
          return;
        }

        // If we got a valid receipt, update the fingerprint for this server.
        var fingerprint = message.fingerprint;
        if (fingerprint) {
          peer.setFingerprint(fingerprint);
        }
      });
    }
  }

});
