/**
 * @provides phabricator-diff-changeset-list
 * @requires javelin-install
 *           phuix-button-view
 * @javelin
 */

JX.install('DiffChangesetList', {

  construct: function() {
    this._changesets = [];

    var onload = JX.bind(this, this._ifawake, this._onload);
    JX.Stratcom.listen('click', 'differential-load', onload);

    var onmore = JX.bind(this, this._ifawake, this._onmore);
    JX.Stratcom.listen('click', 'show-more', onmore);

    var onmenu = JX.bind(this, this._ifawake, this._onmenu);
    JX.Stratcom.listen('click', 'differential-view-options', onmenu);

    var oncollapse = JX.bind(this, this._ifawake, this._oncollapse, true);
    JX.Stratcom.listen('click', 'hide-inline', oncollapse);

    var onexpand = JX.bind(this, this._ifawake, this._oncollapse, false);
    JX.Stratcom.listen('click', 'reveal-inline', onexpand);

    var onedit = JX.bind(this, this._ifawake, this._onaction, 'edit');
    JX.Stratcom.listen(
      'click',
      ['differential-inline-comment', 'differential-inline-edit'],
      onedit);

    var ondone = JX.bind(this, this._ifawake, this._onaction, 'done');
    JX.Stratcom.listen(
      'click',
      ['differential-inline-comment', 'differential-inline-done'],
      ondone);

    var ondelete = JX.bind(this, this._ifawake, this._onaction, 'delete');
    JX.Stratcom.listen(
      'click',
      ['differential-inline-comment', 'differential-inline-delete'],
      ondelete);

    var onreply = JX.bind(this, this._ifawake, this._onaction, 'reply');
    JX.Stratcom.listen(
      'click',
      ['differential-inline-comment', 'differential-inline-reply'],
      onreply);

    var onresize = JX.bind(this, this._ifawake, this._onresize);
    JX.Stratcom.listen('resize', null, onresize);

    var onscroll = JX.bind(this, this._ifawake, this._onscroll);
    JX.Stratcom.listen('scroll', null, onscroll);

    var onselect = JX.bind(this, this._ifawake, this._onselect);
    JX.Stratcom.listen(
      'mousedown',
      ['differential-inline-comment', 'differential-inline-header'],
      onselect);

    var onhover = JX.bind(this, this._ifawake, this._onhover);
    JX.Stratcom.listen(
      ['mouseover', 'mouseout'],
      'differential-inline-comment',
      onhover);

    var onrangedown = JX.bind(this, this._ifawake, this._onrangedown);
    JX.Stratcom.listen(
      'mousedown',
      ['differential-changeset', 'tag:th'],
      onrangedown);

    var onrangemove = JX.bind(this, this._ifawake, this._onrangemove);
    JX.Stratcom.listen(
      ['mouseover', 'mouseout'],
      ['differential-changeset', 'tag:th'],
      onrangemove);

    var onrangeup = JX.bind(this, this._ifawake, this._onrangeup);
    JX.Stratcom.listen(
      'mouseup',
      null,
      onrangeup);
  },

  properties: {
    translations: null,
    inlineURI: null,
    inlineListURI: null
  },

  members: {
    _initialized: false,
    _asleep: true,
    _changesets: null,

    _cursorItem: null,

    _focusNode: null,
    _focusStart: null,
    _focusEnd: null,

    _hoverNode: null,
    _hoverInline: null,
    _hoverOrigin: null,
    _hoverTarget: null,

    _rangeActive: false,
    _rangeOrigin: null,
    _rangeTarget: null,

    _bannerNode: null,
    _unsavedButton: null,
    _unsubmittedButton: null,
    _doneButton: null,
    _doneMode: null,

    _dropdownMenu: null,
    _menuButton: null,
    _menuItems: null,

    sleep: function() {
      this._asleep = true;

      this._redrawFocus();
      this._redrawSelection();
      this.resetHover();
    },

    wake: function() {
      this._asleep = false;

      this._redrawFocus();
      this._redrawSelection();

      if (this._initialized) {
        return;
      }

      this._initialized = true;
      var pht = this.getTranslations();

      var label;

      label = pht('Jump to next change.');
      this._installJumpKey('j', label, 1);

      label = pht('Jump to previous change.');
      this._installJumpKey('k', label, -1);

      label = pht('Jump to next file.');
      this._installJumpKey('J', label, 1, 'file');

      label = pht('Jump to previous file.');
      this._installJumpKey('K', label, -1, 'file');

      label = pht('Jump to next inline comment.');
      this._installJumpKey('n', label, 1, 'comment');

      label = pht('Jump to previous inline comment.');
      this._installJumpKey('p', label, -1, 'comment');

      label = pht('Jump to next inline comment, including collapsed comments.');
      this._installJumpKey('N', label, 1, 'comment', true);

      label = pht(
        'Jump to previous inline comment, including collapsed comments.');
      this._installJumpKey('P', label, -1, 'comment', true);

      label = pht('Hide or show the current file.');
      this._installKey('h', label, this._onkeytogglefile);

      label = pht('Jump to the table of contents.');
      this._installKey('t', label, this._ontoc);

      label = pht('Reply to selected inline comment or change.');
      this._installKey('r', label, JX.bind(this, this._onkeyreply, false));

      label = pht('Reply and quote selected inline comment.');
      this._installKey('R', label, JX.bind(this, this._onkeyreply, true));

      label = pht('Edit selected inline comment.');
      this._installKey('e', label, this._onkeyedit);

      label = pht('Mark or unmark selected inline comment as done.');
      this._installKey('w', label, this._onkeydone);

      label = pht('Collapse or expand inline comment.');
      this._installKey('q', label, this._onkeycollapse);

      label = pht('Hide or show all inline comments.');
      this._installKey('A', label, this._onkeyhideall);

    },

    isAsleep: function() {
      return this._asleep;
    },

    newChangesetForNode: function(node) {
      var changeset = JX.DiffChangeset.getForNode(node);

      this._changesets.push(changeset);
      changeset.setChangesetList(this);

      return changeset;
    },

    getChangesetForNode: function(node) {
      return JX.DiffChangeset.getForNode(node);
    },

    getInlineByID: function(id) {
      var inline = null;

      for (var ii = 0; ii < this._changesets.length; ii++) {
        inline = this._changesets[ii].getInlineByID(id);
        if (inline) {
          break;
        }
      }

      return inline;
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

    _installKey: function(key, label, handler) {
      handler = JX.bind(this, this._ifawake, handler);

      return new JX.KeyboardShortcut(key, label)
        .setHandler(handler)
        .register();
    },

    _installJumpKey: function(key, label, delta, filter, show_collapsed) {
      filter = filter || null;

      var options = {
        filter: filter,
        collapsed: show_collapsed
      };

      var handler = JX.bind(this, this._onjumpkey, delta, options);
      return this._installKey(key, label, handler);
    },

    _ontoc: function(manager) {
      var toc = JX.$('toc');
      manager.scrollTo(toc);
    },

    getSelectedInline: function() {
      var cursor = this._cursorItem;

      if (cursor) {
        if (cursor.type == 'comment') {
          return cursor.target;
        }
      }

      return null;
    },

    _onkeyreply: function(is_quote) {
      var cursor = this._cursorItem;

      if (cursor) {
        if (cursor.type == 'comment') {
          var inline = cursor.target;
          if (inline.canReply()) {
            this.setFocus(null);

            var text;
            if (is_quote) {
              text = inline.getRawText();
              text = '> ' + text.replace(/\n/g, '\n> ') + '\n\n';
            } else {
              text = '';
            }

            inline.reply(text);
            return;
          }
        }

        // If the keyboard cursor is selecting a range of lines, we may have
        // a mixture of old and new changes on the selected rows. It is not
        // entirely unambiguous what the user means when they say they want
        // to reply to this, but we use this logic: reply on the new file if
        // there are any new lines. Otherwise (if there are only removed
        // lines) reply on the old file.

        if (cursor.type == 'change') {
          var origin = cursor.nodes.begin;
          var target = cursor.nodes.end;

          // The "origin" and "target" are entire rows, but we need to find
          // a range of "<th />" nodes to actually create an inline, so go
          // fishing.

          var old_list = [];
          var new_list = [];

          var row = origin;
          while (row) {
            var header = row.firstChild;
            while (header) {
              if (JX.DOM.isType(header, 'th')) {
                if (header.className.indexOf('old') !== -1) {
                  old_list.push(header);
                } else if (header.className.indexOf('new') !== -1) {
                  new_list.push(header);
                }
              }
              header = header.nextSibling;
            }

            if (row == target) {
              break;
            }

            row = row.nextSibling;
          }

          var use_list;
          if (new_list.length) {
            use_list = new_list;
          } else {
            use_list = old_list;
          }

          var src = use_list[0];
          var dst = use_list[use_list.length - 1];

          cursor.changeset.newInlineForRange(src, dst);

          this.setFocus(null);
          return;
        }
      }

      var pht = this.getTranslations();
      this._warnUser(pht('You must select a comment or change to reply to.'));
    },

    _onkeyedit: function() {
      var cursor = this._cursorItem;

      if (cursor) {
        if (cursor.type == 'comment') {
          var inline = cursor.target;
          if (inline.canEdit()) {
            this.setFocus(null);

            inline.edit();
            return;
          }
        }
      }

      var pht = this.getTranslations();
      this._warnUser(pht('You must select a comment to edit.'));
    },

    _onkeydone: function() {
      var cursor = this._cursorItem;

      if (cursor) {
        if (cursor.type == 'comment') {
          var inline = cursor.target;
          if (inline.canDone()) {
            this.setFocus(null);

            inline.toggleDone();
            return;
          }
        }
      }

      var pht = this.getTranslations();
      this._warnUser(pht('You must select a comment to mark done.'));
    },

    _onkeytogglefile: function() {
      var cursor = this._cursorItem;

      if (cursor) {
        if (cursor.type == 'file') {
          cursor.changeset.toggleVisibility();
          return;
        }
      }

      var pht = this.getTranslations();
      this._warnUser(pht('You must select a file to hide or show.'));
    },

    _onkeycollapse: function() {
      var cursor = this._cursorItem;

      if (cursor) {
        if (cursor.type == 'comment') {
          var inline = cursor.target;
          if (inline.canCollapse()) {
            this.setFocus(null);

            inline.setCollapsed(!inline.isCollapsed());
            return;
          }
        }
      }

      var pht = this.getTranslations();
      this._warnUser(pht('You must select a comment to hide.'));
    },

    _onkeyhideall: function() {
      var inlines = this._getInlinesByType();
      if (inlines.visible.length) {
        this._toggleInlines('all');
      } else {
        this._toggleInlines('show');
      }
    },

    _warnUser: function(message) {
      new JX.Notification()
        .setContent(message)
        .alterClassName('jx-notification-alert', true)
        .setDuration(3000)
        .show();
    },

    _onjumpkey: function(delta, options) {
      var state = this._getSelectionState();

      var filter = options.filter || null;
      var collapsed = options.collapsed || false;
      var wrap = options.wrap || false;
      var attribute = options.attribute || null;
      var show = options.show || false;

      var cursor = state.cursor;
      var items = state.items;

      // If there's currently no selection and the user tries to go back,
      // don't do anything.
      if ((cursor === null) && (delta < 0)) {
        return;
      }

      var did_wrap = false;
      while (true) {
        if (cursor === null) {
          cursor = 0;
        } else {
          cursor = cursor + delta;
        }

        // If we've gone backward past the first change, bail out.
        if (cursor < 0) {
          return;
        }

        // If we've gone forward off the end of the list, figure out where we
        // should end up.
        if (cursor >= items.length) {
          if (!wrap) {
            // If we aren't wrapping around, we're done.
            return;
          }

          if (did_wrap) {
            // If we're already wrapped around, we're done.
            return;
          }

          // Otherwise, wrap the cursor back to the top.
          cursor = 0;
          did_wrap = true;
        }

        // If we're selecting things of a particular type (like only files)
        // and the next item isn't of that type, move past it.
        if (filter !== null) {
          if (items[cursor].type !== filter) {
            continue;
          }
        }

        // If the item is collapsed, don't select it when iterating with jump
        // keys. It can still potentially be selected in other ways.
        if (!collapsed) {
          if (items[cursor].collapsed) {
            continue;
          }
        }

        // If the item has been deleted, don't select it when iterating. The
        // cursor may remain on it until it is removed.
        if (items[cursor].deleted) {
          continue;
        }

        // If we're selecting things with a particular attribute, like
        // "unsaved", skip items without the attribute.
        if (attribute !== null) {
          if (!(items[cursor].attributes || {})[attribute]) {
            continue;
          }
        }

        // If this item is a hidden inline but we're clicking a button which
        // selects inlines of a particular type, make it visible again.
        if (items[cursor].hidden) {
          if (!show) {
            continue;
          }
          items[cursor].target.setHidden(false);
        }

        // Otherwise, we've found a valid item to select.
        break;
      }

      this._setSelectionState(items[cursor], true);
    },

    _getSelectionState: function() {
      var items = this._getSelectableItems();

      var cursor = null;
      if (this._cursorItem !== null) {
        for (var ii = 0; ii < items.length; ii++) {
          var item = items[ii];
          if (this._cursorItem.target === item.target) {
            cursor = ii;
            break;
          }
        }
      }

      return {
        cursor: cursor,
        items: items
      };
    },

    _setSelectionState: function(item, scroll) {
      this._cursorItem = item;
      this._redrawSelection(scroll);

      return this;
    },

    _redrawSelection: function(scroll) {
      var cursor = this._cursorItem;
      if (!cursor) {
        this.setFocus(null);
        return;
      }

      // If this item has been removed from the document (for example: create
      // a new empty comment, then use the "Unsaved" button to select it, then
      // cancel it), we can still keep the cursor here but do not want to show
      // a selection reticle over an invisible node.
      if (cursor.deleted) {
        this.setFocus(null);
        return;
      }

      this.setFocus(cursor.nodes.begin, cursor.nodes.end);

      if (scroll) {
        var pos = JX.$V(cursor.nodes.begin);
        JX.DOM.scrollToPosition(0, pos.y - 60);
      }

      return this;
    },

    redrawCursor: function() {
      // NOTE: This is setting the cursor to the current cursor. Usually, this
      // would have no effect.

      // However, if the old cursor pointed at an inline and the inline has
      // been edited so the rows have changed, this updates the cursor to point
      // at the new inline with the proper rows for the current state, and
      // redraws the reticle correctly.

      var state = this._getSelectionState();
      if (state.cursor !== null) {
        this._setSelectionState(state.items[state.cursor], false);
      }
    },

    _getSelectableItems: function() {
      var result = [];

      for (var ii = 0; ii < this._changesets.length; ii++) {
        var items = this._changesets[ii].getSelectableItems();
        for (var jj = 0; jj < items.length; jj++) {
          result.push(items[jj]);
        }
      }

      return result;
    },

    _onhover: function(e) {
      if (e.getIsTouchEvent()) {
        return;
      }

      var inline;
      if (e.getType() == 'mouseout') {
        inline = null;
      } else {
        inline = this._getInlineForEvent(e);
      }

      this._setHoverInline(inline);
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

      var changeset_list = this;
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
          e.prevent();
          menu.close();

          changeset.toggleVisibility();
        });
      list.addItem(visible_item);

      add_link('fa-file-text', pht('Browse in Diffusion'), data.diffusionURI);
      add_link('fa-file-o', pht('View Standalone'), data.standaloneURI);

      var up_item = new JX.PHUIXActionView()
        .setHandler(function(e) {
          if (changeset.isLoaded()) {

            // Don't let the user swap display modes if a comment is being
            // edited, since they might lose their work. See PHI180.
            var inlines = changeset.getInlines();
            for (var ii = 0; ii < inlines.length; ii++) {
              if (inlines[ii].isEditing()) {
                changeset_list._warnUser(
                  pht(
                    'Finish editing inline comments before changing display ' +
                    'modes.'));
                e.prevent();
                menu.close();
                return;
              }
            }

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
          if (!changeset.isVisible()) {
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
    },

    _oncollapse: function(is_collapse, e) {
      e.kill();

      var inline = this._getInlineForEvent(e);

      inline.setCollapsed(is_collapse);
    },

    _onresize: function() {
      this._redrawFocus();
      this._redrawSelection();
      this._redrawHover();

      // Force a banner redraw after a resize event. Particularly, this makes
      // sure the inline state updates immediately after an inline edit
      // operation, even if the changeset itself has not changed.
      this._bannerChangeset = null;

      this._redrawBanner();
    },

    _onscroll: function() {
      this._redrawBanner();
    },

    _onselect: function(e) {
      // If the user clicked some element inside the header, like an action
      // icon, ignore the event. They have to click the header element itself.
      if (e.getTarget() !== e.getNode('differential-inline-header')) {
        return;
      }

      var inline = this._getInlineForEvent(e);
      if (!inline) {
        return;
      }

      // The user definitely clicked an inline, so we're going to handle the
      // event.
      e.kill();

      this.selectInline(inline);
    },

    selectInline: function(inline) {
      var selection = this._getSelectionState();
      var item;

      // If the comment the user clicked is currently selected, deselect it.
      // This makes it easy to undo things if you clicked by mistake.
      if (selection.cursor !== null) {
        item = selection.items[selection.cursor];
        if (item.target === inline) {
          this._setSelectionState(null, false);
          return;
        }
      }

      // Otherwise, select the item that the user clicked. This makes it
      // easier to resume keyboard operations after using the mouse to do
      // something else.
      var items = selection.items;
      for (var ii = 0; ii < items.length; ii++) {
        item = items[ii];
        if (item.target === inline) {
          this._setSelectionState(item, false);
        }
      }
    },

    _onaction: function(action, e) {
      e.kill();

      var inline = this._getInlineForEvent(e);
      var is_ref = false;

      // If we don't have a natural inline object, the user may have clicked
      // an action (like "Delete") inside a preview element at the bottom of
      // the page.

      // If they did, try to find an associated normal inline to act on, and
      // pretend they clicked that instead. This makes the overall state of
      // the page more consistent.

      // However, there may be no normal inline (for example, because it is
      // on a version of the diff which is not visible). In this case, we
      // act by reference.

      if (inline === null) {
        var data = e.getNodeData('differential-inline-comment');
        inline = this.getInlineByID(data.id);
        if (inline) {
          is_ref = true;
        } else {
          switch (action) {
            case 'delete':
              this._deleteInlineByID(data.id);
              return;
          }
        }
      }

      // TODO: For normal operations, highlight the inline range here.

      switch (action) {
        case 'edit':
          inline.edit();
          break;
        case 'done':
          inline.toggleDone();
          break;
        case 'delete':
          inline.delete(is_ref);
          break;
        case 'reply':
          inline.reply();
          break;
      }
    },

    redrawPreview: function() {
      // TODO: This isn't the cleanest way to find the preview form, but
      // rendering no longer has direct access to it.
      var forms = JX.DOM.scry(document.body, 'form', 'transaction-append');
      if (forms.length) {
        JX.DOM.invoke(forms[0], 'shouldRefresh');
      }

      // Clear the mouse hover reticle after a substantive edit: we don't get
      // a "mouseout" event if the row vanished because of row being removed
      // after an edit.
      this.resetHover();
    },

    setFocus: function(node, extended_node) {
      this._focusStart = node;
      this._focusEnd = extended_node;
      this._redrawFocus();
    },

    _redrawFocus: function() {
      var node = this._focusStart;
      var extended_node = this._focusEnd || node;

      var reticle = this._getFocusNode();
      if (!node || this.isAsleep()) {
        JX.DOM.remove(reticle);
        return;
      }

      // Outset the reticle some pixels away from the element, so there's some
      // space between the focused element and the outline.
      var p = JX.Vector.getPos(node);
      var s = JX.Vector.getAggregateScrollForNode(node);

      p.add(s).add(-4, -4).setPos(reticle);
      // Compute the size we need to extend to the full extent of the focused
      // nodes.
      JX.Vector.getPos(extended_node)
        .add(-p.x, -p.y)
        .add(JX.Vector.getDim(extended_node))
        .add(8, 8)
        .setDim(reticle);

      JX.DOM.getContentFrame().appendChild(reticle);
    },

    _getFocusNode: function() {
      if (!this._focusNode) {
        var node = JX.$N('div', {className : 'keyboard-focus-focus-reticle'});
        this._focusNode = node;
      }
      return this._focusNode;
    },

    _setHoverInline: function(inline) {
      this._hoverInline = inline;

      if (inline) {
        var changeset = inline.getChangeset();

        var changeset_id;
        var side = inline.getDisplaySide();
        if (side == 'right') {
          changeset_id = changeset.getRightChangesetID();
        } else {
          changeset_id = changeset.getLeftChangesetID();
        }

        var new_part;
        if (inline.isNewFile()) {
          new_part = 'N';
        } else {
          new_part = 'O';
        }

        var prefix = 'C' + changeset_id + new_part + 'L';

        var number = inline.getLineNumber();
        var length = inline.getLineLength();

        try {
          var origin = JX.$(prefix + number);
          var target = JX.$(prefix + (number + length));

          this._hoverOrigin = origin;
          this._hoverTarget = target;
        } catch (error) {
          // There may not be any nodes present in the document. A case where
          // this occurs is when you reply to a ghost inline which was made
          // on lines near the bottom of "long.txt" in an earlier diff, and
          // the file was later shortened so those lines no longer exist. For
          // more details, see T11662.

          this._hoverOrigin = null;
          this._hoverTarget = null;
        }
      } else {
        this._hoverOrigin = null;
        this._hoverTarget = null;
      }

      this._redrawHover();
    },

    _setHoverRange: function(origin, target) {
      this._hoverOrigin = origin;
      this._hoverTarget = target;

      this._redrawHover();
    },

    resetHover: function() {
      this._setHoverInline(null);

      this._hoverOrigin = null;
      this._hoverTarget = null;
    },

    _redrawHover: function() {
      var reticle = this._getHoverNode();
      if (!this._hoverOrigin || this.isAsleep()) {
        JX.DOM.remove(reticle);
        return;
      }

      JX.DOM.getContentFrame().appendChild(reticle);

      var top = this._hoverOrigin;
      var bot = this._hoverTarget;
      if (JX.$V(top).y > JX.$V(bot).y) {
        var tmp = top;
        top = bot;
        bot = tmp;
      }

      // Find the leftmost cell that we're going to highlight: this is the next
      // <td /> in the row. In 2up views, it should be directly adjacent. In
      // 1up views, we may have to skip over the other line number column.
      var l = top;
      while (JX.DOM.isType(l, 'th')) {
        l = l.nextSibling;
      }

      // Find the rightmost cell that we're going to highlight: this is the
      // farthest consecutive, adjacent <td /> in the row. Sometimes the left
      // and right nodes are the same (left side of 2up view); sometimes we're
      // going to highlight several nodes (copy + code + coverage).
      var r = l;
      while (r.nextSibling && JX.DOM.isType(r.nextSibling, 'td')) {
        r = r.nextSibling;
      }

      var pos = JX.$V(l)
        .add(JX.Vector.getAggregateScrollForNode(l));

      var dim = JX.$V(r)
        .add(JX.Vector.getAggregateScrollForNode(r))
        .add(-pos.x, -pos.y)
        .add(JX.Vector.getDim(r));

      var bpos = JX.$V(bot)
        .add(JX.Vector.getAggregateScrollForNode(bot));
      dim.y = (bpos.y - pos.y) + JX.Vector.getDim(bot).y;

      pos.setPos(reticle);
      dim.setDim(reticle);

      JX.DOM.show(reticle);
    },

    _getHoverNode: function() {
      if (!this._hoverNode) {
        var attributes = {
          className: 'differential-reticle'
        };
        this._hoverNode = JX.$N('div', attributes);
      }

      return this._hoverNode;
    },

    _deleteInlineByID: function(id) {
      var uri = this.getInlineURI();
      var data = {
        op: 'refdelete',
        id: id
      };

      var handler = JX.bind(this, this.redrawPreview);

      new JX.Workflow(uri, data)
        .setHandler(handler)
        .start();
    },

    _getInlineForEvent: function(e) {
      var node = e.getNode('differential-changeset');
      if (!node) {
        return null;
      }

      var changeset = this.getChangesetForNode(node);

      var inline_row = e.getNode('inline-row');
      return changeset.getInlineForRow(inline_row);
    },

    getLineNumberFromHeader: function(th) {
      try {
        return parseInt(th.id.match(/^C\d+[ON]L(\d+)$/)[1], 10);
      } catch (x) {
        return null;
      }
    },

    getDisplaySideFromHeader: function(th) {
      return (th.parentNode.firstChild != th) ? 'right' : 'left';
    },

    _onrangedown: function(e) {
      // NOTE: We're allowing "mousedown" from a touch event through so users
      // can leave inlines on a single line.
      if (e.isRightButton()) {
        return;
      }

      if (this._rangeActive) {
        return;
      }

      var target = e.getTarget();
      var number = this.getLineNumberFromHeader(target);
      if (!number) {
        return;
      }

      e.kill();
      this._rangeActive = true;

      this._rangeOrigin = target;
      this._rangeTarget = target;

      this._setHoverRange(this._rangeOrigin, this._rangeTarget);
    },

    _onrangemove: function(e) {
      if (e.getIsTouchEvent()) {
        return;
      }

      var is_out = (e.getType() == 'mouseout');
      var target = e.getTarget();

      this._updateRange(target, is_out);
    },

    _updateRange: function(target, is_out) {
      // Don't update the range if this "<th />" doesn't correspond to a line
      // number. For instance, this may be a dead line number, like the empty
      // line numbers on the left hand side of a newly added file.
      var number = this.getLineNumberFromHeader(target);
      if (!number) {
        return;
      }

      if (this._rangeActive) {
        var origin = this._hoverOrigin;

        // Don't update the reticle if we're selecting a line range and the
        // "<th />" under the cursor is on the wrong side of the file. You can
        // only leave inline comments on the left or right side of a file, not
        // across lines on both sides.
        var origin_side = this.getDisplaySideFromHeader(origin);
        var target_side = this.getDisplaySideFromHeader(target);
        if (origin_side != target_side) {
          return;
        }

        // Don't update the reticle if we're selecting a line range and the
        // "<th />" under the cursor corresponds to a different file. You can
        // only leave inline comments on lines in a single file, not across
        // multiple files.
        var origin_table = JX.DOM.findAbove(origin, 'table');
        var target_table = JX.DOM.findAbove(target, 'table');
        if (origin_table != target_table) {
          return;
        }
      }

      if (is_out) {
        if (this._rangeActive) {
          // If we're dragging a range, just leave the state as it is. This
          // allows you to drag over something invalid while selecting a
          // range without the range flickering or getting lost.
        } else {
          // Otherwise, clear the current range.
          this.resetHover();
        }
        return;
      }

      if (this._rangeActive) {
        this._rangeTarget = target;
      } else {
        this._rangeOrigin = target;
        this._rangeTarget = target;
      }

      this._setHoverRange(this._rangeOrigin, this._rangeTarget);
    },

    _onrangeup: function(e) {
      if (!this._rangeActive) {
        return;
      }

      e.kill();

      var origin = this._rangeOrigin;
      var target = this._rangeTarget;

      // If the user dragged a range from the bottom to the top, swap the node
      // order around.
      if (JX.$V(origin).y > JX.$V(target).y) {
        var tmp = target;
        target = origin;
        origin = tmp;
      }

      var node = JX.DOM.findAbove(origin, null, 'differential-changeset');
      var changeset = this.getChangesetForNode(node);

      changeset.newInlineForRange(origin, target);

      this._rangeActive = false;
      this._rangeOrigin = null;
      this._rangeTarget = null;

      this.resetHover();
    },

    _redrawBanner: function() {
      // If the inline comment menu is open and we've done a redraw, close it.
      // In particular, this makes it close when you scroll the document:
      // otherwise, it stays open but the banner moves underneath it.
      if (this._dropdownMenu) {
        this._dropdownMenu.close();
      }

      var node = this._getBannerNode();
      var changeset = this._getVisibleChangeset();

      // Don't do anything if nothing has changed. This seems to avoid some
      // flickering issues in Safari, at least.
      if (this._bannerChangeset === changeset) {
        return;
      }
      this._bannerChangeset = changeset;

      if (!changeset) {
        JX.DOM.remove(node);
        return;
      }

      var inlines = this._getInlinesByType();

      var unsaved = inlines.unsaved;
      var unsubmitted = inlines.unsubmitted;
      var undone = inlines.undone;
      var done = inlines.done;
      var draft_done = inlines.draftDone;

      JX.DOM.alterClass(
        node,
        'diff-banner-has-unsaved',
        !!unsaved.length);

      JX.DOM.alterClass(
        node,
        'diff-banner-has-unsubmitted',
        !!unsubmitted.length);

      JX.DOM.alterClass(
        node,
        'diff-banner-has-draft-done',
        !!draft_done.length);

      var pht = this.getTranslations();
      var unsaved_button = this._getUnsavedButton();
      var unsubmitted_button = this._getUnsubmittedButton();
      var done_button = this._getDoneButton();
      var menu_button = this._getMenuButton();

      if (unsaved.length) {
        unsaved_button.setText(unsaved.length + ' ' + pht('Unsaved'));
        JX.DOM.show(unsaved_button.getNode());
      } else {
        JX.DOM.hide(unsaved_button.getNode());
      }

      if (unsubmitted.length || draft_done.length) {
        var any_draft_count = unsubmitted.length + draft_done.length;

        unsubmitted_button.setText(any_draft_count + ' ' + pht('Unsubmitted'));
        JX.DOM.show(unsubmitted_button.getNode());
      } else {
        JX.DOM.hide(unsubmitted_button.getNode());
      }

      if (done.length || undone.length) {
        // If you haven't marked any comments as "Done", we just show text
        // like "3 Comments". If you've marked at least one done, we show
        // "1 / 3 Comments".

        var done_text;
        if (done.length) {
          done_text = [
            done.length,
            ' / ',
            (done.length + undone.length),
            ' ',
            pht('Comments')
          ];
        } else {
          done_text = [
            undone.length,
            ' ',
            pht('Comments')
          ];
        }

        done_button.setText(done_text);

        JX.DOM.show(done_button.getNode());

        // If any comments are not marked "Done", this cycles through the
        // missing comments. Otherwise, it cycles through all the saved
        // comments.
        if (undone.length) {
          this._doneMode = 'undone';
        } else {
          this._doneMode = 'done';
        }

      } else {
        JX.DOM.hide(done_button.getNode());
      }

      var path_view = [icon, ' ', changeset.getDisplayPath()];

      var buttons_attrs = {
        className: 'diff-banner-buttons'
      };

      var buttons_list = [
        unsaved_button.getNode(),
        unsubmitted_button.getNode(),
        done_button.getNode(),
        menu_button.getNode()
      ];

      var buttons_view = JX.$N('div', buttons_attrs, buttons_list);

      var icon = new JX.PHUIXIconView()
        .setIcon(changeset.getIcon())
        .getNode();
      JX.DOM.setContent(node, [buttons_view, path_view]);

      document.body.appendChild(node);
    },

    _getInlinesByType: function() {
      var changesets = this._changesets;
      var unsaved = [];
      var unsubmitted = [];
      var undone = [];
      var done = [];
      var draft_done = [];

      var visible_done = [];
      var visible_collapsed = [];
      var visible_ghosts = [];
      var visible = [];
      var hidden = [];

      for (var ii = 0; ii < changesets.length; ii++) {
        var inlines = changesets[ii].getInlines();
        var inline;
        var jj;
        for (jj = 0; jj < inlines.length; jj++) {
          inline = inlines[jj];

          if (inline.isDeleted()) {
            continue;
          }

          if (inline.isSynthetic()) {
            continue;
          }

          if (inline.isEditing()) {
            unsaved.push(inline);
          } else if (!inline.getID()) {
            // These are new comments which have been cancelled, and do not
            // count as anything.
            continue;
          } else if (inline.isDraft()) {
            unsubmitted.push(inline);
          } else {
            // NOTE: Unlike other states, an inline may be marked with a
            // draft checkmark and still be a "done" or "undone" comment.
            if (inline.isDraftDone()) {
              draft_done.push(inline);
            }

            if (!inline.isDone()) {
              undone.push(inline);
            } else {
              done.push(inline);
            }
          }
        }

        for (jj = 0; jj < inlines.length; jj++) {
          inline = inlines[jj];
          if (inline.isDeleted()) {
            continue;
          }

          if (inline.isEditing()) {
            continue;
          }

          if (inline.isHidden()) {
            hidden.push(inline);
            continue;
          }

          visible.push(inline);

          if (inline.isDone()) {
            visible_done.push(inline);
          }

          if (inline.isCollapsed()) {
            visible_collapsed.push(inline);
          }

          if (inline.isGhost()) {
            visible_ghosts.push(inline);
          }
        }
      }

      return {
        unsaved: unsaved,
        unsubmitted: unsubmitted,
        undone: undone,
        done: done,
        draftDone: draft_done,
        visibleDone: visible_done,
        visibleGhosts: visible_ghosts,
        visibleCollapsed: visible_collapsed,
        visible: visible,
        hidden: hidden
      };

    },

    _getUnsavedButton: function() {
      if (!this._unsavedButton) {
        var button = new JX.PHUIXButtonView()
          .setIcon('fa-commenting-o')
          .setButtonType(JX.PHUIXButtonView.BUTTONTYPE_SIMPLE);

        var node = button.getNode();

        var onunsaved = JX.bind(this, this._onunsavedclick);
        JX.DOM.listen(node, 'click', null, onunsaved);

        this._unsavedButton = button;
      }

      return this._unsavedButton;
    },

    _getUnsubmittedButton: function() {
      if (!this._unsubmittedButton) {
        var button = new JX.PHUIXButtonView()
          .setIcon('fa-comment-o')
          .setButtonType(JX.PHUIXButtonView.BUTTONTYPE_SIMPLE);

        var node = button.getNode();

        var onunsubmitted = JX.bind(this, this._onunsubmittedclick);
        JX.DOM.listen(node, 'click', null, onunsubmitted);

        this._unsubmittedButton = button;
      }

      return this._unsubmittedButton;
    },

    _getDoneButton: function() {
      if (!this._doneButton) {
        var button = new JX.PHUIXButtonView()
          .setIcon('fa-comment')
          .setButtonType(JX.PHUIXButtonView.BUTTONTYPE_SIMPLE);

        var node = button.getNode();

        var ondone = JX.bind(this, this._ondoneclick);
        JX.DOM.listen(node, 'click', null, ondone);

        this._doneButton = button;
      }

      return this._doneButton;
    },

    _getMenuButton: function() {
      if (!this._menuButton) {
        var button = new JX.PHUIXButtonView()
          .setIcon('fa-bars')
          .setButtonType(JX.PHUIXButtonView.BUTTONTYPE_SIMPLE);

        var dropdown = new JX.PHUIXDropdownMenu(button.getNode());
        this._menuItems = {};

        var list = new JX.PHUIXActionListView();
        dropdown.setContent(list.getNode());

        var map = {
          hideDone: {
            type: 'done'
          },
          hideCollapsed: {
            type: 'collapsed'
          },
          hideGhosts: {
            type: 'ghosts'
          },
          hideAll: {
            type: 'all'
          },
          showAll: {
            type: 'show'
          }
        };

        for (var k in map) {
          var spec = map[k];

          var handler = JX.bind(this, this._onhideinlines, spec.type);
          var item = new JX.PHUIXActionView()
            .setHandler(handler);

          list.addItem(item);
          this._menuItems[k] = item;
        }

        dropdown.listen('open', JX.bind(this, this._ondropdown));

        var pht = this.getTranslations();

        if (this.getInlineListURI()) {
          list.addItem(
            new JX.PHUIXActionView()
              .setDivider(true));

          list.addItem(
            new JX.PHUIXActionView()
              .setIcon('fa-external-link')
              .setName(pht('List Inline Comments'))
              .setHref(this.getInlineListURI()));
        }

        this._menuButton = button;
        this._dropdownMenu = dropdown;
      }

      return this._menuButton;
    },

    _ondropdown: function() {
      var inlines = this._getInlinesByType();
      var items = this._menuItems;
      var pht = this.getTranslations();

      items.hideDone
        .setName(pht('Hide "Done" Inlines'))
        .setDisabled(!inlines.visibleDone.length);

      items.hideCollapsed
        .setName(pht('Hide Collapsed Inlines'))
        .setDisabled(!inlines.visibleCollapsed.length);

      items.hideGhosts
        .setName(pht('Hide Older Inlines'))
        .setDisabled(!inlines.visibleGhosts.length);

      items.hideAll
        .setName(pht('Hide All Inlines'))
        .setDisabled(!inlines.visible.length);

      items.showAll
        .setName(pht('Show All Inlines'))
        .setDisabled(!inlines.hidden.length);
    },

    _onhideinlines: function(type, e) {
      this._dropdownMenu.close();
      e.prevent();

      this._toggleInlines(type);
    },

    _toggleInlines: function(type) {
      var inlines = this._getInlinesByType();

      // Clear the selection state since we end up in a weird place if the
      // user hides the selected inline.
      this._setSelectionState(null);

      var targets;
      var mode = true;
      switch (type) {
        case 'done':
          targets = inlines.visibleDone;
          break;
        case 'collapsed':
          targets = inlines.visibleCollapsed;
          break;
        case 'ghosts':
          targets = inlines.visibleGhosts;
          break;
        case 'all':
          targets = inlines.visible;
          break;
        case 'show':
          targets = inlines.hidden;
          mode = false;
          break;
      }

      for (var ii = 0; ii < targets.length; ii++) {
        targets[ii].setHidden(mode);
      }
    },

    _onunsavedclick: function(e) {
      e.kill();

      var options = {
        filter: 'comment',
        wrap: true,
        show: true,
        attribute: 'unsaved'
      };

      this._onjumpkey(1, options);
    },

    _onunsubmittedclick: function(e) {
      e.kill();

      var options = {
        filter: 'comment',
        wrap: true,
        show: true,
        attribute: 'anyDraft'
      };

      this._onjumpkey(1, options);
    },

    _ondoneclick: function(e) {
      e.kill();

      var options = {
        filter: 'comment',
        wrap: true,
        show: true,
        attribute: this._doneMode
      };

      this._onjumpkey(1, options);
    },

    _getBannerNode: function() {
      if (!this._bannerNode) {
        var attributes = {
          className: 'diff-banner',
          id: 'diff-banner'
        };

        this._bannerNode = JX.$N('div', attributes);
      }

      return this._bannerNode;
    },

    _getVisibleChangeset: function() {
      if (this.isAsleep()) {
        return null;
      }

      if (JX.Device.getDevice() != 'desktop') {
        return null;
      }

      // Never show the banner if we're very near the top of the page.
      var margin = 480;
      var s = JX.Vector.getScroll();
      if (s.y < margin) {
        return null;
      }

      // We're going to find the changeset which spans an invisible line a
      // little underneath the bottom of the banner. This makes the header
      // tick over from "A.txt" to "B.txt" just as "A.txt" scrolls completely
      // offscreen.
      var detect_height = 64;

      for (var ii = 0; ii < this._changesets.length; ii++) {
        var changeset = this._changesets[ii];
        var c = changeset.getVectors();

        // If the changeset starts above the line...
        if (c.pos.y <= (s.y + detect_height)) {
          // ...and ends below the line, this is the current visible changeset.
          if ((c.pos.y + c.dim.y) >= (s.y + detect_height)) {
            return changeset;
          }
        }
      }

      return null;
    }
  }

});
