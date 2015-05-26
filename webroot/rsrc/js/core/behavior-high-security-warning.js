/**
 * @provides javelin-behavior-high-security-warning
 * @requires javelin-behavior
 *           javelin-uri
 *           phabricator-notification
 */

JX.behavior('high-security-warning', function(config, statics) {

  function show_warning(message, uri) {
    var n = new JX.Notification()
      .setContent(message)
      .setDuration(0)
      .alterClassName('jx-notification-security', true);

    n.listen(
      'activate',
      function() {
        statics.showing = false;
        JX.$U(uri).go();
      });

    n.show();
    statics.showing = true;
  }

  if (statics.showing) {
    return;
  }

  if (config.show) {
    show_warning(config.message, config.uri);
  }

  JX.Stratcom.listen(
    'quicksand-redraw',
    null,
    function (e) {
      var new_data = e.getData().newResponse.hisecWarningConfig;

      if (!new_data.fromServer || !new_data.show || statics.showing) {
        return;
      }
      show_warning(new_data.message, new_data.uri);
    });
});
