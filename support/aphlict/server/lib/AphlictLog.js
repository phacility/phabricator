'use strict';

var JX = require('./javelin').JX;

var fs = require('fs');
var util = require('util');

JX.install('AphlictLog', {
  construct: function() {
    this._consoles = [];
    this._logs = [];
  },

  members: {
    _consoles: null,
    _logs: null,
    _trace: null,

    setTrace: function(trace) {
      this._trace = trace;
      return this;
    },

    addConsole: function(console) {
      this._consoles.push(console);
      return this;
    },

    addLog: function(path) {
      this._logs.push(fs.createWriteStream(path, {
        flags: 'a',
        encoding: 'utf8',
        mode: '0664',
      }));
      return this;
    },

    trace: function() {
      if (!this._trace) {
        return;
      }

      return this.log.apply(this, arguments);
    },

    log: function() {
      var str = util.format.apply(null, arguments);
      var date = new Date().toLocaleString();
      str = '[' + date + '] ' + str;

      var ii;
      for (ii = 0; ii < this._consoles.length; ii++) {
        this._consoles[ii].log(str);
      }

      for (ii = 0; ii < this._logs.length; ii++) {
        this._logs[ii].write(str + '\n');
      }
    },
  },
});
