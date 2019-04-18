/**
 * @provides javelin-behavior-dashboard-tab-panel
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('dashboard-tab-panel', function() {

  JX.Stratcom.listen('click', 'dashboard-tab-panel-tab', function(e) {
    // On dashboard panels in edit mode, the user may click the dropdown caret
    // within a tab to open the context menu. If they do, this click should
    // just open the menu, not select the tab. For now, pass the event here
    // to let the menu handler act on it.
    if (JX.Stratcom.pass(e)) {
      return;
    }

    e.kill();

    var selected_key = e.getNodeData('dashboard-tab-panel-tab').panelKey;

    var root = e.getNode('dashboard-tab-panel-container');
    var data = JX.Stratcom.getData(root);


    var ii;
    // Give the tab the user clicked a selected style, and remove it from
    // the other tabs.
    var tabs = JX.DOM.scry(root, 'li', 'dashboard-tab-panel-tab');
    for (ii = 0; ii < tabs.length; ii++) {
      var tab = tabs[ii];
      var tab_data = JX.Stratcom.getData(tab);
      var is_selected = (tab_data.panelKey === selected_key);
      JX.DOM.alterClass(tabs[ii], 'phui-list-item-selected', is_selected);
    }

    // Switch the visible content to correspond to whatever the user clicked.
    for (ii = 0; ii < data.panels.length; ii++) {
      var panel = data.panels[ii];
      var node = JX.$(panel.panelContentID);

      if (panel.panelKey == selected_key) {
        JX.DOM.show(node);
      } else {
        JX.DOM.hide(node);
      }
    }

  });

});
