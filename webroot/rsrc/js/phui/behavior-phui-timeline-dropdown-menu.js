/**
 * @provides javelin-behavior-phui-timeline-dropdown-menu
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phuix-dropdown-menu
 */

JX.behavior('phui-timeline-dropdown-menu', function() {

  JX.Stratcom.listen('click', 'phui-timeline-menu', function(e) {
    var data = e.getNodeData('phui-timeline-menu');
    if (data.menu) {
      return;
    }

    e.kill();

    var list = JX.$H(data.items).getFragment().firstChild;

    var icon = e.getNode('phui-timeline-menu');
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
