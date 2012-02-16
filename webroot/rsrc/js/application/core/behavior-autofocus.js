/**
 * @provides javelin-behavior-phabricator-autofocus
 * @requires javelin-behavior javelin-dom
 */

JX.behavior('phabricator-autofocus', function(config) {
  try { JX.$(config.id).focus(); } catch (x) { }
});
