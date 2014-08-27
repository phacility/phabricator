/**
 * @provides javelin-behavior-phui-object-box-tabs
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phui-object-box-tabs', function() {

  JX.Stratcom.listen(
    'click',
    'phui-object-box-tab',
    function (e) {
      e.kill();
      var key = e.getNodeData('phui-object-box-tab').tabKey;
      var map = e.getNodeData('phui-object-box').tabMap;
      var tab = e.getNode('phui-object-box-tab');

      var box = e.getNode('phui-object-box');
      var tabs = JX.DOM.scry(box, 'li', 'phui-object-box-tab');
      for (var ii = 0; ii < tabs.length; ii++) {
        JX.DOM.alterClass(
          tabs[ii],
          'phui-list-item-selected',
          (tabs[ii] == tab));
      }

      for (var k in map) {
        if (k == key) {
          JX.DOM.show(JX.$(map[k]));
        } else {
          JX.DOM.hide(JX.$(map[k]));
        }
      }
    });

});
