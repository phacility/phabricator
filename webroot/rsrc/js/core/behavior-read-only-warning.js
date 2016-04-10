/**
 * @provides javelin-behavior-read-only-warning
 * @requires javelin-behavior
 *           javelin-uri
 *           phabricator-notification
 */

JX.behavior('read-only-warning', function(config) {

  var n = new JX.Notification()
    .setContent(config.message)
    .setDuration(0)
    .alterClassName('jx-notification-read-only', true);

  n.listen(
    'activate',
    function() {
      JX.$U(config.uri).go();
    });

  n.show();

});
