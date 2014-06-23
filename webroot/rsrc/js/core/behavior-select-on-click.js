/**
 * @provides javelin-behavior-select-on-click
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 * @javelin
 */

JX.behavior('select-on-click', function() {
  JX.Stratcom.listen(
    'click',
    'select-on-click',
    function(e) {
      e.kill();
      var node = e.getNode('select-on-click');
      JX.DOM.focus(node);
      node.select();
    });
});
