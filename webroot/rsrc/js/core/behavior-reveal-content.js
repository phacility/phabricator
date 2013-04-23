/**
 * @provides javelin-behavior-phabricator-reveal-content
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 * @javelin
 */

JX.behavior('phabricator-reveal-content', function(config) {
  JX.Stratcom.listen(
    'click',
    'reveal-content',
    function(e) {
      e.kill();
      var nodes = e.getNodeData('reveal-content');
      for (var ii = 0; ii < nodes.showIDs.length; ii++) {
        JX.DOM.show(JX.$(nodes.showIDs[ii]));
      }
      for (var ii = 0; ii < nodes.hideIDs.length; ii++) {
        JX.DOM.hide(JX.$(nodes.hideIDs[ii]));
      }
    });
});
