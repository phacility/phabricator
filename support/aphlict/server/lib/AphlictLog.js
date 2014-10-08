var JX = require('javelin').JX;

var fs = require('fs');
var util = require('util');

JX.install('AphlictLog', {
  construct: function() {
    this._writeToLogs = [];
    this._writeToConsoles = [];
  },

  members: {
    _writeToConsoles: null,
    _writeToLogs: null,

    addLogfile: function(path) {
      var options = {
        flags: 'a',
        encoding: 'utf8',
        mode: 066
      };

      var logfile = fs.createWriteSteam(path, options);

      this._writeToLogs.push(logfile);

      return this;
    },

    addConsole: function(console) {
      this._writeToConsoles.push(console);
      return this;
    },

    log: function() {
      var str = util.format.apply(null, arguments);
      var date = new Date().toLocaleString();
      str = '[' + date + '] ' + str;

      var ii;
      for (ii = 0; ii < this._writeToConsoles.length; ii++) {
        this._writeToConsoles[ii].log(str);
      }

      for (ii = 0; ii < this._writeToLogs.length; ii++) {
        this._writeToLogs[ii].write(str + '\n');
      }
    }

  }

});
