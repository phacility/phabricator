'use strict';

var JX = require('./javelin').JX;

JX.install('AphlictListener', {
  construct: function(id, socket) {
    this._id = id;
    this._socket = socket;
  },

  members: {
    _id: null,
    _socket: null,
    _subscriptions: {},

    getID: function() {
      return this._id;
    },

    subscribe: function(phids) {
      for (var i = 0; i < phids.length; i++) {
        var phid = phids[i];
        this._subscriptions[phid] = true;
      }

      return this;
    },

    unsubscribe: function(phids) {
      for (var i = 0; i < phids.length; i++) {
        var phid = phids[i];
        delete this._subscriptions[phid];
      }

      return this;
    },

    isSubscribedToAny: function(phids) {
      var intersection = phids.filter(function(phid) {
        return phid in this._subscriptions;
      }, this);
      return intersection.length > 0;
    },

    getSocket: function() {
      return this._socket;
    },

    getDescription: function() {
      return 'Listener/' + this.getID();
    },

    writeMessage: function(message) {
      this._socket.send(JSON.stringify(message));
    },
  },
});
