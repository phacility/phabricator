'use strict';

var JX = require('./javelin').JX;

JX.install('AphlictListener', {
  construct: function(id, socket, path) {
    this._id = id;
    this._socket = socket;
    this._path = path;
    this._subscriptions = {};
  },

  members: {
    _id: null,
    _socket: null,
    _path: null,
    _subscriptions: null,

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
      return 'Listener/' + this.getID() + this._path;
    },

    writeMessage: function(message) {
      this._socket.send(JSON.stringify(message));
    },
  },
});
