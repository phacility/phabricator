/**
 * @provides javelin-behavior-aphlict-dropdown
 * @requires javelin-behavior
 *           javelin-request
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 */

JX.behavior('aphlict-dropdown', function(config) {
  var dropdown = JX.$(config.dropdownID);
  var count = JX.$(config.countID);
  var bubble = JX.$(config.bubbleID);
  var visible = false;
  var request = null;

  function refresh() {
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
      JX.DOM.setContent(dropdown, JX.$H(response.content));
      request = null;
    });
    request.send();
  }

  JX.Stratcom.listen(
    'click',
    null,
    function(e) {
      if(e.getNode('phabricator-notification-menu')) {
         // Click is inside the dropdown.
        return;
      }

      JX.DOM.hide(dropdown);
      visible = false;
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
        refresh();

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

  JX.Stratcom.listen('notification-panel-update', null, refresh);
});
