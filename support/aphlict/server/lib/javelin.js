var javelin_root = '../../../../webroot/rsrc/externals/javelin/';
var JX = require(javelin_root + 'core/init_node.js').JX;

JX.require('core/util');
JX.require('core/install');

// NOTE: This is faking out a piece of code in JX.install which waits for
// Stratcom before running static initializers.
JX.Stratcom = {ready: true};
JX.require('core/Event');
JX.require('core/Stratcom');

exports.JX = JX;
