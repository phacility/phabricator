/**
 * @provides phabricator-diff-changeset-list
 * @requires javelin-install
 * @javelin
 */

JX.install('DiffChangesetList', {

  construct: function() {

    var onload = JX.bind(this, this._ifawake, this._onload);
    JX.Stratcom.listen('click', 'differential-load', onload);

    var onmore = JX.bind(this, this._ifawake, this._onmore);
    JX.Stratcom.listen('click', 'show-more', onmore);

    var onmenu = JX.bind(this, this._ifawake, this._onmenu);
    JX.Stratcom.listen('click', 'differential-view-options', onmenu);
  },

  properties: {
    translations: null
  },

  members: {
    _asleep: true,

    sleep: function() {
      this._asleep = true;
    },

    wake: function() {
      this._asleep = false;
    },

    isAsleep: function() {
      return this._asleep;
    },

    getChangesetForNode: function(node) {
      return JX.DiffChangeset.getForNode(node);
    },

    _ifawake: function(f) {
      // This function takes another function and only calls it if the
      // changeset list is awake, so we basically just ignore events when we
      // are asleep. This may move up the stack at some point as we do more
      // with Quicksand/Sheets.

      if (this.isAsleep()) {
        return;
      }

      return f.apply(this, [].slice.call(arguments, 1));
    },

    _onload: function(e) {
      var data = e.getNodeData('differential-load');

      // NOTE: We can trigger a load from either an explicit "Load" link on
      // the changeset, or by clicking a link in the table of contents. If
      // the event was a table of contents link, we let the anchor behavior
      // run normally.
      if (data.kill) {
        e.kill();
      }

      var node = JX.$(data.id);
      var changeset = this.getChangesetForNode(node);

      changeset.load();

      // TODO: Move this into Changeset.
      var routable = changeset.getRoutable();
      if (routable) {
        routable.setPriority(2000);
      }
    },

    _onmore: function(e) {
      e.kill();

      var node = e.getNode('differential-changeset');
      var changeset = this.getChangesetForNode(node);

      var data = e.getNodeData('show-more');
      var target = e.getNode('context-target');

      changeset.loadContext(data.range, target);
    },

    _onmenu: function(e) {
      var button = e.getNode('differential-view-options');

      var data = JX.Stratcom.getData(button);
      if (data.menu) {
        // We've already built this menu, so we can let the menu itself handle
        // the event.
        return;
      }

      e.prevent();

      var pht = this.getTranslations();

      var node = JX.DOM.findAbove(
        button,
        'div',
        'differential-changeset');

      var changeset = this.getChangesetForNode(node);

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
          if (changeset.isLoaded()) {
            var renderer = changeset.getRenderer();
            if (renderer == '1up') {
              renderer = '2up';
            } else {
              renderer = '1up';
            }
            changeset.setRenderer(renderer);
          }
          changeset.reload();

          e.prevent();
          menu.close();
        });
      list.addItem(up_item);

      var encoding_item = new JX.PHUIXActionView()
        .setIcon('fa-font')
        .setName(pht('Change Text Encoding...'))
        .setHandler(function(e) {
          var params = {
            encoding: changeset.getEncoding()
          };

          new JX.Workflow('/services/encoding/', params)
            .setHandler(function(r) {
              changeset.setEncoding(r.encoding);
              changeset.reload();
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
            highlight: changeset.getHighlight()
          };

          new JX.Workflow('/services/highlight/', params)
            .setHandler(function(r) {
              changeset.setHighlight(r.highlight);
              changeset.reload();
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
              changeset.loadAllContext();
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

        encoding_item.setDisabled(!changeset.isLoaded());
        highlight_item.setDisabled(!changeset.isLoaded());

        if (changeset.isLoaded()) {
          if (changeset.getRenderer() == '2up') {
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
    }



  }

});
