var JX = require('javelin').JX;
JX.require('AphlictListener', __dirname);

JX.install('AphlictListenerList', {
  construct: function() {
    this._listeners = {};
  },

  members: {
    _listeners: null,
    _nextID: 0,
    _totalListenerCount: 0,

    addListener: function(socket) {
      var listener = new JX.AphlictListener(this._generateNextID(), socket);

      this._listeners[listener.getID()] = listener;
      this._totalListenerCount++;

      return listener;
    },

    removeListener: function(listener) {
      var id = listener.getID();
      if (id in this._listeners) {
        delete this._listeners[id];
      }
    },

    getListeners: function() {
      var keys = Object.keys(this._listeners);
      var listeners = [];

      for (var i = 0; i < keys.length; i++) {
        listeners.push(this._listeners[keys[i]]);
      }

      return listeners;
    },

    getActiveListenerCount: function() {
      return Object.keys(this._listeners).length;
    },

    getTotalListenerCount: function() {
      return this._totalListenerCount;
    },

    _generateNextID: function() {
      do {
        this._nextID = ((this._nextID + 1) % 1000000000000);
      } while (this._nextID in this._listeners);

      return this._nextID;
    }

  }

});
