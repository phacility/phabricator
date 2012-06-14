/**
 * @requires phabricator-notification
 *           javelin-stratcom
 *           javelin-behavior
 * @provides javelin-behavior-phabricator-notification-example
 */

JX.behavior('phabricator-notification-example', function(config) {
  JX.Stratcom.listen(
    'click',
    'notification-example',
    function(e) {
      e.kill();

      var notification = new JX.Notification()
        .setContent('It is ' + new Date().toString());

      notification.listen(
        'activate',
        function(e) {
          if (!confirm("Close notification?")) {
            e.kill();
          }
        });

      notification.show()
    });

});
