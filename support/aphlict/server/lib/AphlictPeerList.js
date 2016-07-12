'use strict';

var JX = require('./javelin').JX;

JX.install('AphlictPeerList', {

  construct: function() {
    this._peers = [];

    // Generate a new unique identify for this server. We just use this to
    // identify messages we have already seen and figure out which peer is
    // actually us, so we don't bounce messages around the cluster forever.
    this._fingerprint = this._generateFingerprint();
  },

  properties: {
  },

  members: {
    _peers: null,
    _fingerprint: null,

    addPeer: function(peer) {
      this._peers.push(peer);
      return this;
    },

    addFingerprint: function(message) {
      var fingerprint = this.getFingerprint();

      // Check if we've already touched this message. If we have, we do not
      // broadcast it again. If we haven't, we add our fingerprint and then
      // broadcast the modified version.
      var touched = message.touched || [];
      for (var ii = 0; ii < touched.length; ii++) {
        if (touched[ii] == fingerprint) {
          return null;
        }
      }
      touched.push(fingerprint);

      message.touched = touched;
      return message;
    },

    broadcastMessage: function(instance, message) {
      var ii;

      var touches = {};
      var touched = message.touched;
      for (ii = 0; ii < touched.length; ii++) {
        touches[touched[ii]] = true;
      }

      var peers = this._peers;
      for (ii = 0; ii < peers.length; ii++) {
        var peer = peers[ii];

        // If we know the peer's fingerprint and it has already touched
        // this message, don't broadcast it.
        var fingerprint = peer.getFingerprint();
        if (fingerprint && touches[fingerprint]) {
          continue;
        }

        peer.broadcastMessage(instance, message);
      }
    },

    getFingerprint: function() {
      return this._fingerprint;
    },

    _generateFingerprint: function() {
      var src = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
      var len = 16;
      var out = [];
      for (var ii = 0; ii < len; ii++) {
        var idx = Math.floor(Math.random() * src.length);
        out.push(src[idx]);
      }
      return out.join('');
    }
  }

});
