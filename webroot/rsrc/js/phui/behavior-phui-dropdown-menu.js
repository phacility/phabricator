/**
 * @provides javelin-behavior-phui-dropdown-menu
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phuix-dropdown-menu
 */

JX.behavior('phui-dropdown-menu', function() {

  JX.Stratcom.listen('click', 'phui-dropdown-menu', function(e) {
    var data = e.getNodeData('phui-dropdown-menu');
    if (data.menu) {
      return;
    }

    e.kill();

    var list = JX.$H(data.items).getFragment().firstChild;

    var icon = e.getNode('phui-dropdown-menu');
    data.menu = new JX.PHUIXDropdownMenu(icon);
    data.menu.setContent(list);
    data.menu.open();

    JX.DOM.listen(list, 'click', 'tag:a', function(e) {
      if (!e.isNormalClick()) {
        return;
      }
      data.menu.close();
    });
  });

});
