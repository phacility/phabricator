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

    var list;
    var placeholder;
    if (data.items) {
      list = JX.$H(data.items).getFragment().firstChild;
    } else {
      list = JX.$(data.menuID);
      placeholder = JX.$N('span');
    }

    var icon = e.getNode('phui-dropdown-menu');
    data.menu = new JX.PHUIXDropdownMenu(icon);

    data.menu.listen('open', function() {
      if (placeholder) {
        JX.DOM.replace(list, placeholder);
      }
      data.menu.setContent(list);
    });

    data.menu.listen('close', function() {
      if (placeholder) {
        JX.DOM.replace(placeholder, list);
      }
    });

    data.menu.open();
  });

});
