/**
 * @provides javelin-behavior-desktop-notifications-control
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-uri
 *           phabricator-notification
 */

JX.behavior('desktop-notifications-control', function(config, statics) {

  function findEl(id) {
    var el = null;
    try {
      el = JX.$(id);
    } catch (e) {
      // not found
    }
    return el;
  }
  function updateFormStatus(permission) {
    var status_node = findEl(config.statusID);
    if (!status_node) {
      return;
    }

    var message_node = JX.$(config.messageID);

    switch (permission) {
      case 'default':
        JX.DOM.setContent(message_node, config.cancelAsk);
        break;
      case 'granted':
        JX.DOM.setContent(message_node, config.grantedAsk);
        break;
      case 'denied':
        JX.DOM.setContent(message_node, config.deniedAsk);
        break;
    }

    JX.DOM.show(status_node);
  }

  function updateBrowserStatus(permission) {
    var browserStatusEl = findEl(config.browserStatusID);
    if (!browserStatusEl) {
      return;
    }
    switch (permission) {
      case 'default':
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-notice', true);
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-success', false);
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-error', false);
        JX.DOM.setContent(browserStatusEl, JX.$H(config.defaultStatus));
        break;
      case 'granted':
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-success', true);
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-notice', false);
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-error', false);
        JX.DOM.setContent(browserStatusEl, JX.$H(config.grantedStatus));
        break;
      case 'denied':
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-error', true);
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-notice', false);
        JX.DOM.alterClass(browserStatusEl, 'phui-info-severity-success', false);
        JX.DOM.setContent(browserStatusEl, JX.$H(config.deniedStatus));
        break;
    }
    JX.DOM.show(browserStatusEl);
  }

  function installSelectListener() {
    var controlEl = findEl(config.controlID);
    if (!controlEl) {
      return;
    }
    var select = JX.DOM.find(controlEl, 'select');
    JX.DOM.listen(
      select,
      'change',
      null,
      function (e) {
        if (!JX.Notification.supportsDesktopNotifications()) {
          return;
        }
        var value = e.getTarget().value;
        if ((value == config.desktop) || (value == config.desktopOnly)) {
            window.Notification.requestPermission(
              function (permission) {
                updateFormStatus(permission);
                updateBrowserStatus(permission);
              });
        } else {
          var statusEl = JX.$(config.statusID);
          JX.DOM.hide(statusEl);
        }
      });
  }

  function install() {
    JX.Stratcom.listen(
      'click',
      'desktop-notifications-permission-button',
        function () {
          window.Notification.requestPermission(
            function (permission) {
              updateFormStatus(permission);
              updateBrowserStatus(permission);
            });
        });

    return true;
  }

  statics.installed = statics.installed || install();
  if (!JX.Notification.supportsDesktopNotifications()) {
    var statusEl = JX.$(config.statusID);
    JX.DOM.setContent(statusEl.firstChild, config.noSupport);
    JX.DOM.show(statusEl);
  } else {
    updateBrowserStatus(window.Notification.permission);
  }
  installSelectListener();
});
