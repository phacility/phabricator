/**
 * @provides javelin-behavior-aphlict-dropdown
 * @requires javelin-behavior
 *           javelin-request
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 */

JX.behavior('aphlict-dropdown', function(config) {
  var dropdown = JX.$('phabricator-notification-dropdown');
  var indicator = JX.$('phabricator-notification-indicator');
  var menu = JX.$('phabricator-notification-menu');
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
          "phabricator-notification-indicator-unread", false);
      } else {
        JX.DOM.alterClass(indicator,
          "phabricator-notification-indicator-unread", true);
      }
      JX.DOM.setContent(dropdown, JX.$H(response.content));
      request = null;
    });
    request.send();
  }

  //populate panel
  refresh();

  JX.Stratcom.listen(
    'click',
    null,
    function(e) {
      if(e.getNode('aphlict-dropdown') ||
         e.getNode('aphlict-indicator')) {
         // Click is inside the dropdown, or on indicator
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
