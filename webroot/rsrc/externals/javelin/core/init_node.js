/**
 * Alternative Javelin init file for Node.js.
 *
 * @javelin-installs JX.enableDispatch
 * @javelin-installs JX.onload
 * @javelin-installs JX.flushHoldingQueue
 * @javelin-installs JX.require
 *
 * @javelin
 */

var JX = {};
var fs = require('fs');
var vm = require('vm');
var pathModule = require('path');

var noop = function() {};

JX.enableDispatch = noop;
JX.flushHoldingQueue = noop;

JX.onload = function(func) {
  func();
};

JX.require = function(thing, relative) {
  relative = relative || __dirname + '/..';
  var path = relative + '/' + thing + '.js';
  var content = fs.readFileSync(path);

  var sandbox = {
    JX : this,
    __DEV__ : 0,
    console : console,
    window : {},
    require : function (thing) {
      return require(pathModule.dirname(path) + '/' + thing);
    }
  };

  vm.createScript(content, path)
    .runInNewContext(sandbox, path);
};

exports.JX = JX;
