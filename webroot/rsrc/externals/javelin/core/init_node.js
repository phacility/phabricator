'use strict';

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

JX.require = function(thing) {
  var path = __dirname + '/../' + thing + '.js';
  var content = fs.readFileSync(path);
  var dir = pathModule.dirname(path);

  var k;
  var sandbox = {};

  for (k in global) {
    sandbox[k] = global[k];
  }

  var extra = {
    JX: this,
    __DEV__: 0,
    window: {},
    __dirname: dir
  };

  for (k in extra) {
    sandbox[k] = extra[k];
  }

  vm.createScript(content, path)
    .runInNewContext(sandbox, path);
};

exports.JX = JX;
