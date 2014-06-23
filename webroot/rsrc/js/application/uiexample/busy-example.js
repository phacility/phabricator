/**
 * @provides javelin-behavior-phabricator-busy-example
 * @requires phabricator-busy
 *           javelin-behavior
 */

JX.behavior('phabricator-busy-example', function() {
  JX.Busy.start();
});
