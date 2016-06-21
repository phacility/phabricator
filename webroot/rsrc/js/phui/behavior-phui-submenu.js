/**
 * @provides javelin-behavior-phui-submenu
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phui-submenu', function() {

  JX.Stratcom.listen('click', 'phui-submenu', function(e) {
    if (!e.isNormalClick()) {
      return;
    }

    var node = e.getNode('phui-submenu');
    var data = e.getNodeData('phui-submenu');

    e.kill();

    data.open = !data.open;

    for (var ii = 0; ii < data.itemIDs.length; ii++) {
      var id = data.itemIDs[ii];
      var item = JX.$(id);
      if (data.open) {
        JX.DOM.show(item);
      } else {
        JX.DOM.hide(item);
      }

      // Add a class so we can animate zany effects.
      JX.DOM.alterClass(item, 'phui-submenu-animate', data.open);
    }

    JX.DOM.alterClass(node, 'phui-submenu-open', data.open);

    // Toggle the caret from ">" to "V" when opening the menu, and back again
    // when closing it.
    var caret = JX.$(data.caretID);
    JX.DOM.alterClass(caret, 'caret', data.open);
    JX.DOM.alterClass(caret, 'caret-right', !data.open);
  });

});
