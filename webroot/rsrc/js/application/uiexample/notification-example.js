/**
 * @requires phabricator-notification
 *           javelin-stratcom
 *           javelin-behavior
 *           javelin-uri
 * @provides javelin-behavior-phabricator-notification-example
 */

JX.behavior('phabricator-notification-example', function(config) {
  JX.Stratcom.listen(
    'click',
    'notification-example',
    function(e) {
      e.kill();

      var notification = new JX.Notification();
      if (Math.random() > 0.1) {
        notification.setContent('It is ' + new Date().toString());

        notification.listen(
          'activate',
          function(e) {
            if (!confirm("Close notification?")) {
              e.kill();
            }
          });
      } else {
        notification
          .setContent('Alert! Click to reload!')
          .setDuration(0)
          .setClassName('jx-notification-alert');

        notification.listen(
          'activate',
          function(e) {
            new JX.$U().go();
          });
      }
      notification.show()
    });

});
