/**
 * @provides javelin-behavior-aphlict-dropdown
 * @requires javelin-behavior
 *           javelin-request
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 *           javelin-uri
 *           javelin-behavior-device
 *           phabricator-title
 */

JX.behavior('aphlict-dropdown', function(config, statics) {
  // Track the current globally visible menu.
  statics.visible = statics.visible || null;

  var dropdown = JX.$(config.dropdownID);
  var bubble = JX.$(config.bubbleID);
  var icon = JX.DOM.scry(bubble, 'span', 'menu-icon')[0];

  var count;
  if (config.countID) {
    count = JX.$(config.countID);
  }

  var request = null;
  var dirty = config.local ? false : true;

  JX.Title.setCount(config.countType, config.countNumber);

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
      JX.Title.setCount(config.countType, response.number);

      var display = (response.number > 999) ? '\u221E' : response.number;

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

  function set_visible(menu, icon) {
    if (menu) {
      statics.visible = {menu: menu, icon: icon};
      if (icon) {
        JX.DOM.alterClass(icon, 'white', true);
      }
    } else {
      if (statics.visible) {
        JX.DOM.hide(statics.visible.menu);
        if (statics.visible.icon) {
          JX.DOM.alterClass(statics.visible.icon, 'white', false);
        }
      }
      statics.visible = null;
    }
  }

  JX.Stratcom.listen(
    'click',
    null,
    function(e) {
      if (!e.getNode('phabricator-notification-menu')) {
        // Click outside the dropdown; hide it.
        set_visible(null);
        return;
      }

      if (e.getNode('tag:a')) {
        // User clicked a link, just follow the link.
        return;
      }

      if (!e.getNode('notification')) {
        // User clicked somewhere in the dead area of the menu, like the header
        // or footer.
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
        set_visible(null);

        // If the menu we just closed was the menu attached to the clicked
        // icon, we're all done -- clicking the icon for an open menu just
        // closes it. Otherwise, we closed some other menu and still need to
        // open the one the user just clicked.
        if (previously_visible.menu === dropdown) {
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

      set_visible(dropdown, icon);
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
