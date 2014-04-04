/**
 * @provides javelin-behavior-aphlict-dropdown
 * @requires javelin-behavior
 *           javelin-request
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 *           javelin-uri
 *           javelin-behavior-device
 */

JX.behavior('aphlict-dropdown', function(config, statics) {
  // Track the current globally visible menu.
  statics.visible = statics.visible || null;

  var dropdown = JX.$(config.dropdownID);
  var bubble = JX.$(config.bubbleID);

  var count;
  if (config.countID) {
    count = JX.$(config.countID);
  }

  var request = null;
  var dirty = config.local ? false : true;

  function refresh() {
    if (dirty) {
      JX.DOM.setContent(dropdown, config.loadingText);
      JX.DOM.alterClass(
        dropdown,
        'phabricator-notification-menu-loading',
        true);
    }

    if (request) { //already fetching
      return;
    }

    request = new JX.Request(config.uri, function(response) {
      var display = (response.number > 999) ? "\u221E" : response.number;

      JX.DOM.setContent(count, display);
      if (response.number === 0) {
        JX.DOM.alterClass(bubble, 'alert-unread', false);
      } else {
        JX.DOM.alterClass(bubble, 'alert-unread', true);
      }
      dirty = false;
      JX.DOM.alterClass(
        dropdown,
        'phabricator-notification-menu-loading',
        false);
      JX.DOM.setContent(dropdown, JX.$H(response.content));
      request = null;
    });
    request.send();
  }

  JX.Stratcom.listen(
    'click',
    null,
    function(e) {
      if (!e.getNode('phabricator-notification-menu')) {
        // Click outside the dropdown; hide it.
        JX.DOM.hide(dropdown);
        statics.visible = null;
        return;
      }

      if (e.getNode('tag:a')) {
        // User clicked a link, just follow the link.
        return;
      }

      // If the user clicked a notification (but missed a link) and it has a
      // primary URI, go there.
      var href = e.getNodeData('notification').href;
      if (href) {
        JX.$U(href).go();
        e.kill();
      }
    });


  JX.DOM.listen(
    bubble,
    'click',
    null,
    function(e) {
      if (!e.isNormalClick()) {
        return;
      }

      if (config.desktop && JX.Device.getDevice() != 'desktop') {
        return;
      }

      e.kill();

      // If a menu is currently open, close it.
      if (statics.visible) {
        var previously_visible = statics.visible;
        JX.DOM.hide(statics.visible);
        statics.visible = null;

        // If the menu we just closed was the menu attached to the clicked
        // icon, we're all done -- clicking the icon for an open menu just
        // closes it. Otherwise, we closed some other menu and still need to
        // open the one the user just clicked.
        if (previously_visible === dropdown) {
          return;
        }
      }

      if (dirty) {
        refresh();
      }

      var p = JX.$V(bubble);
      JX.DOM.show(dropdown);

      p.y = null;
      if (config.right) {
        p.x -= (JX.Vector.getDim(dropdown).x - JX.Vector.getDim(bubble).x);
      } else {
        p.x -= 6;
      }
      p.setPos(dropdown);

      statics.visible = dropdown;
    }
  );

  JX.Stratcom.listen('notification-panel-update', null, function() {
    if (config.local) {
      return;
    }
    dirty = true;
    refresh();
  });
});
