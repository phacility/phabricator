/**
 * @provides javelin-behavior-select-content
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 * @javelin
 */

JX.behavior('select-content', function() {
  JX.Stratcom.listen(
    'click',
    'select-content',
    function(e) {
      e.kill();

      var node = e.getNode('select-content');
      var data = JX.Stratcom.getData(node);

      var target = JX.$(data.selectID);
      JX.DOM.focus(target);
      target.select();
    });
});
