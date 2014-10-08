/**
 * @provides javelin-behavior-boards-dropdown
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phuix-dropdown-menu
 */

JX.behavior('boards-dropdown', function() {

  JX.Stratcom.listen('click', 'boards-dropdown-menu', function(e) {
    var data = e.getNodeData('boards-dropdown-menu');
    if (data.menu) {
      return;
    }

    e.kill();

    var list = JX.$H(data.items).getFragment().firstChild;

    var button = e.getNode('boards-dropdown-menu');
    data.menu = new JX.PHUIXDropdownMenu(button);
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
