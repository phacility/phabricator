/**
 * @provides javelin-behavior-differential-dropdown-menus
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           phabricator-dropdown-menu
 *           phabricator-menu-item
 */

JX.behavior('differential-dropdown-menus', function(config) {

  function build_menu(button, data) {

    function show_more() {
      var container = JX.$(data.containerID);
      var nodes = JX.DOM.scry(container, 'tr', 'context-target');
      for (var ii = 0; ii < nodes.length; ii++) {
        var show = JX.DOM.scry(nodes[ii], 'a', 'show-more');
        for (var jj = 0; jj < show.length; jj++) {
          if (JX.Stratcom.getData(show[jj]).type != 'all') {
            continue;
          }
          var event_data = {
            context : nodes[ii],
            show : show[jj]
          };
          JX.Stratcom.invoke('differential-reveal-context', null, event_data);
        }
      }
    }

    function link_to(name, uri) {
      var item = new JX.PhabricatorMenuItem(
        name,
        JX.bind(null, window.open, uri),
        uri);
      item.setDisabled(!uri);
      return item;
    }

    var reveal_item = new JX.PhabricatorMenuItem('', show_more);

    var diffusion_item = link_to('Browse in Diffusion', data.diffusionURI);
    if (!data.diffusionURI) {
      diffusion_item.setDisabled(true);
    }

    var menu = new JX.PhabricatorDropdownMenu(buttons[ii])
      .addItem(reveal_item)
      .addItem(diffusion_item)
      .addItem(link_to('View Standalone', data.detailURI));
    if (data.leftURI) {
      menu.addItem(link_to('Show Raw File (Left)', data.leftURI));
    }
    if (data.rightURI) {
      menu.addItem(link_to('Show Raw File (Right)', data.rightURI));
    }
    if (data.editor) {
      menu.addItem(new JX.PhabricatorMenuItem(
        'Open in Editor',
        JX.bind(null, location.assign, data.editor), // Open in the same window.
        data.editor));
    }
    if (data.editorConfigure) {
      menu.addItem(link_to('Configure Editor', data.editorConfigure));
    }

    menu.listen(
      'open',
      function() {

        // When the user opens the menu, check if there are any "Show More"
        // links in the changeset body. If there aren't, disable the "Show
        // Entire File" menu item since it won't change anything.

        var nodes = JX.DOM.scry(JX.$(data.containerID), 'a', 'show-more');
        if (nodes.length) {
          reveal_item.setDisabled(false);
          reveal_item.setName('Show Entire File');
        } else {
          reveal_item.setDisabled(true);
          reveal_item.setName('Entire File Shown');
        }
      });
  }

  var buttons = JX.DOM.scry(window.document, 'a', 'differential-view-options');
  for (var ii = 0; ii < buttons.length; ii++) {
    build_menu(buttons[ii], JX.Stratcom.getData(buttons[ii]));
  }

});
