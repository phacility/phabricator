var JX = require('javelin').JX;

JX.install('AphlictListener', {
  construct : function(id, socket) {
    this._id = id;
    this._socket = socket;
  },

  members : {
    _id : null,
    _socket : null,

    getID : function() {
      return this._id;
    },

    getSocket : function() {
      return this._socket;
    },

    getDescription : function() {
      return 'Listener/' + this.getID();
    },

    writeMessage : function(message) {
      var serial = JSON.stringify(message);

      var length = Buffer.byteLength(serial, 'utf8');
      length = length.toString();
      while (length.length < 8) {
        length = '0' + length;
      }

      this._socket.write(length + serial);
    }

  }

});
