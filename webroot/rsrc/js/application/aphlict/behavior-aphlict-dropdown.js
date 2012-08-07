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
  var indicator = JX.$(config.indicatorID);
  var menu = JX.$(config.menuID);
  var visible = false;
  var request = null;

  function refresh() {
    if (request) { //already fetching
      return;
    }
    request = new JX.Request('/notification/panel/', function(response) {
      JX.DOM.setContent(indicator, response.number);
      if (response.number == 0) {
        JX.DOM.alterClass(indicator,
          "phabricator-main-menu-alert-indicator-unread", false);
      } else {
        JX.DOM.alterClass(indicator,
          "phabricator-main-menu-alert-indicator-unread", true);
      }
      JX.DOM.setContent(dropdown, JX.$H(response.content));
      request = null;
    });
    request.send();
  }

  refresh();

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
    menu,
    'click',
    null,
    function(e) {
      if (visible) {
        JX.DOM.hide(dropdown);
      } else {
        var p = JX.$V(menu);
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
