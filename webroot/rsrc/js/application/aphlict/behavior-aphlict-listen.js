/**
 * @provides javelin-behavior-aphlict-listen
 * @requires javelin-behavior
 *           javelin-aphlict
 *           javelin-stratcom
 *           javelin-request
 *           javelin-uri
 *           javelin-dom
 *           javelin-json
 *           javelin-router
 *           javelin-util
 *           javelin-leader
 *           javelin-sound
 *           phabricator-notification
 */

JX.behavior('aphlict-listen', function(config) {
  var page_objects = config.pageObjects;
  var reload_notification = null;

  JX.Stratcom.listen('aphlict-server-message', null, function(e) {
    var message = e.getData();

    if (message.type != 'notification') {
      return;
    }

    JX.Leader.callIfLeader(function() {
      var request = new JX.Request(
        '/notification/individual/',
        onNotification);

      var routable = request
        .addData({key: message.key})
        .getRoutable();

      routable
        .setType('notification')
        .setPriority(250);

      JX.Router.getInstance().queue(routable);
    });
  });

  // Respond to a notification from the Aphlict notification server. We send
  // a request to Phabricator to get notification details.
  function onAphlictMessage(message) {
    switch (message.type) {
      case 'aphlict.server':
        JX.Stratcom.invoke('aphlict-server-message', null, message.data);
        break;

      case 'notification.individual':
        JX.Stratcom.invoke('aphlict-notification-message', null, message.data);
        break;

      case 'aphlict.reconnect':
        JX.Stratcom.invoke('aphlict-reconnect', null, message.data);
        break;
    }
  }

  // Respond to a response from Phabricator about a specific notification.
  function onNotification(response) {
    if (!response.pertinent) {
      return;
    }

    JX.Leader.broadcast(
      response.uniqueID,
      {
        type: 'notification.individual',
        data: response
      });
  }

  JX.Stratcom.listen('aphlict-notification-message', null, function(e) {
    JX.Stratcom.invoke('notification-panel-update', null, {});
    var response = e.getData();

    if (!response.showAnyNotification) {
      return;
    }

    // Show the notification itself.
    new JX.Notification()
      .setContent(JX.$H(response.content))
      .setKey(response.primaryObjectPHID)
      .setShowAsDesktopNotification(response.showDesktopNotification)
      .setTitle(response.title)
      .setBody(response.body)
      .setHref(response.href)
      .setIcon(response.icon)
      .show();

    // If the notification affected an object on this page, show a
    // permanent reload notification if we aren't already.
    if ((response.primaryObjectPHID in page_objects) &&
      reload_notification === null) {

      var reload = new JX.Notification()
        .setContent('Page updated, click to reload.')
        .alterClassName('jx-notification-alert', true)
        .setDuration(0);
      reload.listen(
        'activate',
        function() {
          // double check we are still on the page where re-loading makes
          // sense...!
          if (response.primaryObjectPHID in page_objects) {
            JX.$U().go();
          }
        });
      reload.show();

      reload_notification = {
        dialog: reload,
        phid: response.primaryObjectPHID
      };
    }
  });

  var client = new JX.Aphlict(
    config.websocketURI,
    config.subscriptions);

  var start_client = function() {
    client
      .setHandler(onAphlictMessage)
      .start();
  };

  // Don't start the client until other behaviors have had a chance to
  // initialize. In particular, we want to capture events into the log for
  // the DarkConsole "Realtime" panel.
  setTimeout(start_client, 0);

  JX.Stratcom.listen(
    'quicksand-redraw',
    null,
    function (e) {
      var old_data = e.getData().oldResponse;
      var new_data = e.getData().newResponse;
      client.clearSubscriptions(old_data.subscriptions);
      client.setSubscriptions(new_data.subscriptions);

      page_objects = new_data.pageObjects;
      if (reload_notification) {
        if (reload_notification.phid in page_objects) {
          return;
        }
        reload_notification.dialog.hide();
        reload_notification = null;
      }
    });

  JX.Leader.listen('onReceiveBroadcast', function(message, is_leader) {
    if (message.type !== 'sound') {
      return;
    }

    if (!is_leader) {
      return;
    }

    JX.Sound.play(message.data);
  });


});
