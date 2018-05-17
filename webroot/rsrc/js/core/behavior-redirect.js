/**
 * @provides javelin-behavior-redirect
 * @requires javelin-behavior
 *           javelin-uri
 */

JX.behavior('redirect', function(config) {
  JX.$U(config.uri).go();
});
