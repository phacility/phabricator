/**
 * @provides javelin-behavior-high-security-warning
 * @requires javelin-behavior
 *           javelin-uri
 *           phabricator-notification
 */

JX.behavior('high-security-warning', function(config) {

  var n = new JX.Notification()
    .setContent(config.message)
    .setDuration(0)
    .alterClassName('jx-notification-security', true);

  n.listen('activate', function() { JX.$U(config.uri).go(); });

  n.show();

});
