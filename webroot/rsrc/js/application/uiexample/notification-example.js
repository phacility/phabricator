/**
 * @requires phabricator-notification
 *           javelin-stratcom
 *           javelin-behavior
 * @provides javelin-behavior-phabricator-notification-example
 */

JX.behavior('phabricator-notification-example', function() {

  var sequence = 0;

  JX.Stratcom.listen(
    'click',
    'notification-example',
    function(e) {
      e.kill();

      var notification = new JX.Notification();
      switch (sequence % 4) {
        case 0:
          var update = function() {
            notification.setContent('It is ' + new Date().toString());
          };

          update();
          setInterval(update, 1000);

          break;
        case 1:
          notification
            .setContent('Permanent alert notification (until clicked).')
            .setDuration(0)
            .alterClassName('jx-notification-alert', true);
          break;
        case 2:
          notification
            .setContent('This notification reacts when you click it.');

          notification.listen(
            'activate',
            function() {
              if (!window.confirm('Close notification?')) {
                JX.Stratcom.context().kill();
              }
            });
          break;
        case 3:
          notification
            .setDuration(2000)
            .setContent('This notification will close after 2 seconds ' +
                        'unless you keep clicking it!');

          notification.listen(
            'activate',
            function() {
              notification.setDuration(2000);
              JX.Stratcom.context().kill();
            });
          break;
      }

      notification.show();
      sequence++;
    });

});
