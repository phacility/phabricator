/**
 * @provides javelin-behavior-user-menu
 * @requires javelin-behavior
 */

JX.behavior('user-menu', function(config) {
  var node = JX.$(config.menuID);
  var list = JX.$H(config.menu.items).getFragment().firstChild;

  var menu = new JX.PHUIXDropdownMenu(node);

  menu.listen('open', function() {
    menu.setContent(list);
  });

  // When the user navigates to a new page, we may need to update the links
  // to documentation in the menu.
  JX.Stratcom.listen('quicksand-redraw', null, function(e) {
    var data = e.getData();

    var new_help = data.newResponse.helpItems;
    var nodes;
    if (new_help) {
      nodes = JX.$H(new_help.items).getFragment().firstChild.children;
    } else {
      nodes = [];
    }

    var ii;

    var tail = [];
    for (ii = list.children.length - 1; ii >= 0; ii--) {
      var node = list.children[ii];

      // Remove any old help items.
      if (JX.Stratcom.hasSigil(node.firstChild, 'help-item')) {
        JX.DOM.remove(node);
      }

      // Place the logout items aside, if any exist.
      if (JX.Stratcom.hasSigil(node.firstChild, 'logout-item')) {
        JX.DOM.remove(node);
        tail.push(node);
      }
    }

    while (nodes.length) {
      list.appendChild(nodes[0]);
    }

    tail.reverse();
    for (ii = 0; ii < tail.length; ii++) {
      list.appendChild(tail[ii]);
    }
  });

});
