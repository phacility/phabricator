/**
 * @provides javelin-behavior-phui-tab-group
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phui-tab-group', function() {

  JX.Stratcom.listen(
    'click',
    'phui-tab-view',
    function (e) {
      e.kill();

      var map = e.getNodeData('phui-tab-group-view').tabMap;
      var key = e.getNodeData('phui-tab-view').tabKey;

      var group = e.getNode('phui-tab-group-view');
      var tab = e.getNode('phui-tab-view');
      var tabs = JX.DOM.scry(group, 'li', 'phui-tab-view');

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
