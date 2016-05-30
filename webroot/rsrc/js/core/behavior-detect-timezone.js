/**
 * @provides javelin-behavior-detect-timezone
 * @requires javelin-behavior
 *           javelin-uri
 *           phabricator-notification
 */

JX.behavior('detect-timezone', function(config) {

  var offset = new Date().getTimezoneOffset();
  var ignore = config.ignore;

  if (ignore !== null) {
    // If we're ignoring a client offset and it's the current offset, just
    // bail. This means the user has chosen to ignore the clock difference
    // between the current client setting and their server setting.
    if (offset == ignore) {
      return;
    }

    // If we're ignoring a client offset but the current offset is different,
    // wipe the offset. If you go from SF to NY, ignore the difference, return
    // to SF, then travel back to NY a few months later, we want to prompt you
    // again. This code will clear the ignored setting upon your return to SF.
    new JX.Request('/settings/adjust/', JX.bag)
      .setData({key: config.ignoreKey, value: ''})
      .send();

    ignore = null;
  }

  // If the client and server clocks are in sync, we're all set.
  if (offset == config.offset) {
    return;
  }

  var notification = new JX.Notification()
    .alterClassName('jx-notification-alert', true)
    .setContent(config.message)
    .setDuration(0);

  notification.listen('activate', function() {
    JX.Stratcom.context().kill();
    notification.hide();

    var uri = config.uri + offset + '/';

    // Some browsers (notably, Chrome) expose an "Intl" API which gives us
    // direct access to a timezone setting. If we are able to read this, use
    // it to guess which timezone the user is in so we can prefill the
    // dropdown.
    try {
      var guess = Intl.DateTimeFormat().resolvedOptions().timeZone;
      uri = JX.$U(uri).setQueryParam('guess', guess);
    } catch (error) {
      // Ignore any errors here, we'll just make the user pick from the big
      // list.
    }

    new JX.Workflow(uri)
      .start();
  });

  notification.show();
});
