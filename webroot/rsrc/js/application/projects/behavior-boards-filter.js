/**
 * @provides javelin-behavior-boards-filter
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phuix-dropdown-menu
 */

JX.behavior('boards-filter', function(config) {

  JX.Stratcom.listen('click', 'boards-filter-menu', function(e) {
    var data = e.getNodeData('boards-filter-menu');
    if (data.menu) {
      return;
    }

    e.kill();

    var list = JX.$H(data.items).getFragment().firstChild;

    var button = e.getNode('boards-filter-menu');
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
