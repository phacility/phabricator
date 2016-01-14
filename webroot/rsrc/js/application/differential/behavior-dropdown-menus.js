/**
 * @provides javelin-behavior-differential-dropdown-menus
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-workflow
 *           phuix-dropdown-menu
 *           phuix-action-list-view
 *           phuix-action-view
 *           phabricator-phtize
 *           changeset-view-manager
 */

JX.behavior('differential-dropdown-menus', function(config) {
  var pht = JX.phtize(config.pht);

  function show_more(container) {
    var view = JX.ChangesetViewManager.getForNode(container);

    var nodes = JX.DOM.scry(container, 'tr', 'context-target');
    for (var ii = 0; ii < nodes.length; ii++) {
      var show = JX.DOM.scry(nodes[ii], 'a', 'show-more');
      for (var jj = 0; jj < show.length; jj++) {
        var data = JX.Stratcom.getData(show[jj]);
        if (data.type != 'all') {
          continue;
        }
        view.loadContext(data.range, nodes[ii], true);
      }
    }
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

  var buildmenu = function(e) {
    var button = e.getNode('differential-view-options');
    var data = JX.Stratcom.getData(button);
    if (data.menu) {
      return;
    }

    e.prevent();

    var changeset = JX.DOM.findAbove(
      button,
      'div',
      'differential-changeset');

    var view = JX.ChangesetViewManager.getForNode(changeset);
    var menu = new JX.PHUIXDropdownMenu(button);
    var list = new JX.PHUIXActionListView();

    var add_link = function(icon, name, href, local) {
      if (!href) {
        return;
      }

      var link = new JX.PHUIXActionView()
        .setIcon(icon)
        .setName(name)
        .setHref(href)
        .setHandler(function(e) {
          if (local) {
            window.location.assign(href);
          } else {
            window.open(href);
          }
          menu.close();
          e.prevent();
        });

      list.addItem(link);
      return link;
    };

    var reveal_item = new JX.PHUIXActionView()
      .setIcon('fa-eye');
    list.addItem(reveal_item);

    var visible_item = new JX.PHUIXActionView()
      .setHandler(function(e) {
        var diff = JX.DOM.scry(
          JX.$(data.containerID),
          'table',
          'differential-diff');

        JX.Stratcom.invoke('differential-toggle-file', null, {diff: diff});
        e.prevent();
        menu.close();
      });
    list.addItem(visible_item);

    add_link('fa-file-text', pht('Browse in Diffusion'), data.diffusionURI);
    add_link('fa-file-o', pht('View Standalone'), data.standaloneURI);

    var up_item = new JX.PHUIXActionView()
      .setHandler(function(e) {
        if (view.isLoaded()) {
          var renderer = view.getRenderer();
          if (renderer == '1up') {
            renderer = '2up';
          } else {
            renderer = '1up';
          }
          view.setRenderer(renderer);
        }
        view.reload();

        e.prevent();
        menu.close();
      });
    list.addItem(up_item);

    var encoding_item = new JX.PHUIXActionView()
      .setIcon('fa-font')
      .setName(pht('Change Text Encoding...'))
      .setHandler(function(e) {
        var params = {
          encoding: view.getEncoding()
        };

        new JX.Workflow('/services/encoding/', params)
          .setHandler(function(r) {
            view.setEncoding(r.encoding);
            view.reload();
          })
          .start();

        e.prevent();
        menu.close();
      });
    list.addItem(encoding_item);

    var highlight_item = new JX.PHUIXActionView()
      .setIcon('fa-sun-o')
      .setName(pht('Highlight As...'))
      .setHandler(function(e) {
        var params = {
          highlight: view.getHighlight()
        };

        new JX.Workflow('/services/highlight/', params)
          .setHandler(function(r) {
            view.setHighlight(r.highlight);
            view.reload();
          })
          .start();

        e.prevent();
        menu.close();
      });
    list.addItem(highlight_item);

    add_link('fa-arrow-left', pht('Show Raw File (Left)'), data.leftURI);
    add_link('fa-arrow-right', pht('Show Raw File (Right)'), data.rightURI);
    add_link('fa-pencil', pht('Open in Editor'), data.editor, true);
    add_link('fa-wrench', pht('Configure Editor'), data.editorConfigure);

    menu.setContent(list.getNode());

    menu.listen('open', function() {
      // When the user opens the menu, check if there are any "Show More"
      // links in the changeset body. If there aren't, disable the "Show
      // Entire File" menu item since it won't change anything.

      var nodes = JX.DOM.scry(JX.$(data.containerID), 'a', 'show-more');
      if (nodes.length) {
        reveal_item
          .setDisabled(false)
          .setName(pht('Show All Context'))
          .setIcon('fa-file-o')
          .setHandler(function(e) {
            show_more(JX.$(data.containerID));
            e.prevent();
            menu.close();
          });
      } else {
        reveal_item
          .setDisabled(true)
          .setIcon('fa-file')
          .setName(pht('All Context Shown'))
          .setHandler(function(e) { e.prevent(); });
      }

      encoding_item.setDisabled(!view.isLoaded());
      highlight_item.setDisabled(!view.isLoaded());

      if (view.isLoaded()) {
        if (view.getRenderer() == '2up') {
          up_item
            .setIcon('fa-list-alt')
            .setName(pht('View Unified'));
        } else {
          up_item
            .setIcon('fa-files-o')
            .setName(pht('View Side-by-Side'));
        }
      } else {
        up_item
          .setIcon('fa-refresh')
          .setName(pht('Load Changes'));
      }

      visible_item
        .setDisabled(true)
        .setIcon('fa-expand')
        .setName(pht('Can\'t Toggle Unloaded File'));
      var diffs = JX.DOM.scry(
        JX.$(data.containerID),
        'table',
        'differential-diff');

      if (diffs.length > 1) {
        JX.$E(
          'More than one node with sigil "differential-diff" was found in "'+
          data.containerID+'."');
      } else if (diffs.length == 1) {
        var diff = diffs[0];
        visible_item.setDisabled(false);
        if (JX.Stratcom.getData(diff).hidden) {
          visible_item
            .setName(pht('Expand File'))
            .setIcon('fa-expand');
        } else {
          visible_item
            .setName(pht('Collapse File'))
            .setIcon('fa-compress');
        }
      } else {
        // Do nothing when there is no diff shown in the table. For example,
        // the file is binary.
      }

    });

    data.menu = menu;
    menu.open();
  };

  JX.Stratcom.listen('click', 'differential-view-options', buildmenu);
});
