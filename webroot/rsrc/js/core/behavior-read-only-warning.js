/**
 * @provides javelin-behavior-read-only-warning
 * @requires javelin-behavior
 *           javelin-uri
 *           phabricator-notification
 */

JX.behavior('read-only-warning', function(config) {

  new JX.Notification()
    .setContent(config.message)
    .setDuration(0)
    .alterClassName('jx-notification-read-only', true)
    .show();

});
