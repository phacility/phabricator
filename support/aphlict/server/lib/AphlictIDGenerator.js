var JX = require('javelin').JX;

JX.install('AphlictIDGenerator', {

  members : {
    _next : 0,

    generateNext : function() {
      this._next = ((this._next + 1) % 1000000000000);
      return this._next;
    },

    getTotalCount : function() {
      return this._next;
    }
  }

});
