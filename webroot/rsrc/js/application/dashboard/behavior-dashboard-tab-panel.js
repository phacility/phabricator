/**
 * @provides javelin-behavior-dashboard-tab-panel
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('dashboard-tab-panel', function() {

  JX.Stratcom.listen('click', 'dashboard-tab-panel-tab', function(e) {
    e.kill();

    var ii;
    var idx = e.getNodeData('dashboard-tab-panel-tab').idx;

    var root = e.getNode('dashboard-tab-panel-container');
    var data = JX.Stratcom.getData(root);

    // Give the tab the user clicked a selected style, and remove it from
    // the other tabs.
    var tabs = JX.DOM.scry(root, 'li', 'dashboard-tab-panel-tab');
    for (ii = 0; ii < tabs.length; ii++) {
      JX.DOM.alterClass(tabs[ii], 'phui-list-item-selected', (ii == idx));
    }

    // Switch the visible content to correspond to whatever the user clicked.
    for (ii = 0; ii < data.panels.length; ii++) {
      var panel = JX.$(data.panels[ii]);
      if (ii == idx) {
        JX.DOM.show(panel);
      } else {
        JX.DOM.hide(panel);
      }
    }

  });

});
