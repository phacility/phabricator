/**
 * @provides javelin-behavior-aphlict-dropdown
 * @requires javelin-behavior
 *           javelin-request
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 *           javelin-uri
 */

JX.behavior('aphlict-dropdown', function(config) {
  var dropdown = JX.$(config.dropdownID);
  var count = JX.$(config.countID);
  var bubble = JX.$(config.bubbleID);
  var visible = false;
  var request = null;
  var dirty = true;

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
    request = new JX.Request('/notification/panel/', function(response) {
      var display = (response.number > 999)
        ? "\u221E"
        : response.number;

      JX.DOM.setContent(count, display);
      if (response.number == 0) {
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
        visible = false;
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

      if (visible) {
        JX.DOM.hide(dropdown);
      } else {
        if (dirty) {
          refresh();
        }

        var p = JX.$V(bubble);
        p.y = null;
        p.x -= 6;
        p.setPos(dropdown);

        JX.DOM.show(dropdown);
      }
      visible = !visible;
      e.kill();
    }
  )

  JX.Stratcom.listen('notification-panel-update', null, function() {
    dirty = true;
    refresh();
  });
});
