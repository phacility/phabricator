/**
 * @provides javelin-behavior-differential-dropdown-menus
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           phabricator-dropdown-menu
 *           phabricator-menu-item
 *           phabricator-phtize
 */

JX.behavior('differential-dropdown-menus', function(config) {

  var pht = JX.phtize(config.pht);

  function show_more(container) {
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

  function build_menu(button, data) {

    function link_to(name, uri) {
      var item = new JX.PhabricatorMenuItem(
        name,
        JX.bind(null, window.open, uri),
        uri);
      item.setDisabled(!uri);
      return item;
    }

    var reveal_item = new JX.PhabricatorMenuItem('', function () {
      show_more(JX.$(data.containerID));
    });

    var diffusion_item;
    if (data.diffusionURI) {
      // Show this only if we have a link, since when this appears in Diffusion
      // it is otherwise potentially confusing.
      diffusion_item = link_to(pht('Browse in Diffusion'), data.diffusionURI);
    }

    var menu = new JX.PhabricatorDropdownMenu(buttons[ii])
      .addItem(reveal_item);

    var visible_item = new JX.PhabricatorMenuItem('', function () {
      JX.Stratcom.invoke('differential-toggle-file', null, {
        diff: JX.DOM.scry(JX.$(data.containerID), 'table', 'differential-diff'),
      });
    });
    menu.addItem(visible_item);

    if (diffusion_item) {
      menu.addItem(diffusion_item);
    }

    menu.addItem(link_to(pht('View Standalone'), data.standaloneURI));

    if (data.leftURI) {
      menu.addItem(link_to(pht('Show Raw File (Left)'), data.leftURI));
    }

    if (data.rightURI) {
      menu.addItem(link_to(pht('Show Raw File (Right)'), data.rightURI));
    }

    if (data.editor) {
      menu.addItem(new JX.PhabricatorMenuItem(
        pht('Open in Editor'),
        JX.bind(null, location.assign, data.editor), // Open in the same window.
        data.editor));
    }

    if (data.editorConfigure) {
      menu.addItem(link_to(pht('Configure Editor'), data.editorConfigure));
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
          reveal_item.setName(pht('Show Entire File'));
        } else {
          reveal_item.setDisabled(true);
          reveal_item.setName(pht('Entire File Shown'));
        }

        visible_item.setDisabled(true);
        visible_item.setName(pht("Can't Toggle Unloaded File"));
        var diffs = JX.DOM.scry(JX.$(data.containerID),
                               'table', 'differential-diff');
        if (diffs.length > 1) {
          JX.$E(
            'More than one node with sigil "differential-diff" was found in "'+
            data.containerID+'."');
        } else if (diffs.length == 1) {
          diff = diffs[0];
          visible_item.setDisabled(false);
          if (JX.Stratcom.getData(diff).hidden) {
            visible_item.setName(pht('Expand File'));
          } else {
            visible_item.setName(pht('Collapse File'));
          }
        } else {
          // Do nothing when there is no diff shown in the table. For example,
          // the file is binary.
        }
      });
  }

  var buttons = JX.DOM.scry(window.document, 'a', 'differential-view-options');
  for (var ii = 0; ii < buttons.length; ii++) {
    build_menu(buttons[ii], JX.Stratcom.getData(buttons[ii]));
  }

  JX.Stratcom.listen(
    'click',
    'differential-reveal-all',
    function(e) {
      var containers = JX.DOM.scry(
        JX.$('differential-review-stage'),
        'div',
        'differential-changeset');
      for (var i=0; i < containers.length; i++) {
        show_more(containers[i]);
      }
      e.kill();
    });

});
