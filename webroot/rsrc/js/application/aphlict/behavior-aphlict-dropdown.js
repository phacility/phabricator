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

  function _updateCount(number) {
    JX.Title.setCount(config.countType, number);

    JX.DOM.setContent(count, number);
    if (number === 0) {
      JX.DOM.alterClass(bubble, config.unreadClass, false);
    } else {
      JX.DOM.alterClass(bubble, config.unreadClass, true);
    }
  }

  function refresh() {
    if (dirty) {
      JX.DOM.setContent(dropdown, config.loadingText);
      JX.DOM.alterClass(
        dropdown,
        'phabricator-notification-menu-loading',
        true);
    }

    if (request) {
      // Already fetching.
      return;
    }

    request = new JX.Request(config.uri, function(response) {
      var number = response.number;
      _updateCount(number);
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
    'quicksand-redraw',
    null,
    function (e) {
      var data = e.getData();
      if (config.local && config.applicationClass) {
        var local_dropdowns = data.newResponse.aphlictDropdowns;
        if (local_dropdowns[config.applicationClass]) {
          JX.DOM.replace(
            dropdown,
            JX.$H(local_dropdowns[config.applicationClass]));
          dropdown = JX.$(config.dropdownID);
          if (dropdown.childNodes.length === 0) {
            JX.DOM.hide(bubble);
          } else {
            JX.DOM.show(bubble);
          }
        } else {
          JX.DOM.hide(bubble);
        }
        return;
      }

      if (!data.fromServer) {
        return;
      }
      var new_data = data.newResponse.aphlictDropdownData;
      update_counts(new_data);
    });

  JX.Stratcom.listen(
    'conpherence-redraw-aphlict',
    null,
    function (e) {
      update_counts(e.getData());
    });

  function update_counts(new_data) {
    var updated = false;
    for (var ii = 0; ii < new_data.length; ii++) {
      if (new_data[ii].countType != config.countType) {
        continue;
      }
      if (!new_data[ii].isInstalled) {
        continue;
      }
      updated = true;
      _updateCount(parseInt(new_data[ii].count));
    }
    if (updated) {
      dirty = true;
    }
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
        // User clicked a link. Hide the menu, then follow the link.
        set_visible(null);
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
        set_visible(null);
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
      if (config.containerDivID) {
        var pc = JX.$V(JX.$(config.containerDivID));
        p.x -= (JX.Vector.getDim(dropdown).x - JX.Vector.getDim(bubble).x +
            pc.x);
      } else if (config.right) {
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

  JX.Stratcom.listen('notification-panel-close', null, function() {
    set_visible(null);
  });
});
