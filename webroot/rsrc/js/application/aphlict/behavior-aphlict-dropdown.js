/**
 * @provides javelin-behavior-aphlict-dropdown
 * @requires javelin-behavior
 *           javelin-aphlict
 *           javelin-util
 *           javelin-request
 *           javelin-stratcom
 */

JX.behavior('aphlict-dropdown', function(config) {
  var dropdown = JX.$('phabricator-notification-dropdown');
  var indicator = JX.$('phabricator-notification-indicator');
  var visible = false;


  JX.DOM.hide(dropdown);

  //populate panel
  (new JX.Request('/notification/panel/',
    function(response) {
      JX.DOM.setContent(dropdown, JX.$H(response.content));
    })).send();


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
    indicator,
    'click',
    null,
    function(e) {
      if(visible) {
        JX.DOM.hide(dropdown);
      } else {
        JX.DOM.show(dropdown);
      }
      visible = !visible;
    }
  )

});
