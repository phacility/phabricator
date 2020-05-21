/**
 * @provides phabricator-diff-changeset-list
 * @requires javelin-install
 *           phuix-button-view
 *           phabricator-diff-tree-view
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

    var onexpand = JX.bind(this, this._ifawake, this._oncollapse, false);
    JX.Stratcom.listen('click', 'reveal-inline', onexpand);

    var onresize = JX.bind(this, this._ifawake, this._onresize);
    JX.Stratcom.listen('resize', null, onresize);

    var onscroll = JX.bind(this, this._ifawake, this._onscroll);
    JX.Stratcom.listen('scroll', null, onscroll);

    JX.enableDispatch(window, 'selectstart');

    var onselect = JX.bind(this, this._ifawake, this._onselect);
    JX.Stratcom.listen(
      ['mousedown', 'selectstart'],
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
      ['differential-changeset', 'tag:td'],
      onrangedown);

    var onrangemove = JX.bind(this, this._ifawake, this._onrangemove);
    JX.Stratcom.listen(
      ['mouseover', 'mouseout'],
      ['differential-changeset', 'tag:td'],
      onrangemove);

    var onrangeup = JX.bind(this, this._ifawake, this._onrangeup);
    JX.Stratcom.listen(
      'mouseup',
      null,
      onrangeup);

    var onrange = JX.bind(this, this._ifawake, this._onSelectRange);
    JX.enableDispatch(window, 'selectionchange');
    JX.Stratcom.listen('selectionchange', null, onrange);

    this._setupInlineCommentListeners();
  },

  properties: {
    translations: null,
    inlineURI: null,
    inlineListURI: null,
    isStandalone: false,
    formationView: null
  },

  members: {
    _initialized: false,
    _asleep: true,
    _changesets: null,

    _cursorItem: null,

    _focusNode: null,
    _focusStart: null,
    _focusEnd: null,

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
    _selectedChangeset: null,

    sleep: function() {
      this._asleep = true;

      this._redrawFocus();
      this._redrawSelection();
      this.resetHover();

      this._bannerChangeset = null;
      this._redrawBanner();
    },

    wake: function() {
      this._asleep = false;

      this._redrawFocus();
      this._redrawSelection();

      this._bannerChangeset = null;
      this._redrawBanner();

      this._redrawFiletree();

      if (this._initialized) {
        return;
      }

      this._initialized = true;
      var pht = this.getTranslations();

      // We may be viewing the normal "/D123" view (with all the changesets)
      // or the standalone view (with just one changeset). In the standalone
      // view, some options (like jumping to next or previous file) do not
      // make sense and do not function.
      var standalone = this.getIsStandalone();

      var label;

      if (!standalone) {
        label = pht('Jump to the table of contents.');
        this._installKey('t', 'diff-nav', label, this._ontoc);

        label = pht('Jump to the comment area.');
        this._installKey('x', 'diff-nav', label, this._oncomments);
      }

      label = pht('Jump to next change.');
      this._installJumpKey('j', label, 1);

      label = pht('Jump to previous change.');
      this._installJumpKey('k', label, -1);

      if (!standalone) {
        label = pht('Jump to next file.');
        this._installJumpKey('J', label, 1, 'file');

        label = pht('Jump to previous file.');
        this._installJumpKey('K', label, -1, 'file');
      }

      label = pht('Jump to next inline comment.');
      this._installJumpKey('n', label, 1, 'comment');

      label = pht('Jump to previous inline comment.');
      this._installJumpKey('p', label, -1, 'comment');

      label = pht('Jump to next inline comment, including collapsed comments.');
      this._installJumpKey('N', label, 1, 'comment', true);

      label = pht(
        'Jump to previous inline comment, including collapsed comments.');
      this._installJumpKey('P', label, -1, 'comment', true);

      var formation = this.getFormationView();
      if (formation) {
        var filetree = formation.getColumn(0);
        var toggletree = JX.bind(filetree, filetree.toggleVisibility);
        label = pht('Hide or show the paths panel.');
        this._installKey('f', 'diff-vis', label, toggletree);
      }

      if (!standalone) {
        label = pht('Hide or show the current changeset.');
        this._installKey('h', 'diff-vis', label, this._onkeytogglefile);
      }

      label = pht('Reply to selected inline comment or change.');
      this._installKey('r', 'inline', label,
        JX.bind(this, this._onkeyreply, false));

      label = pht('Reply and quote selected inline comment.');
      this._installKey('R', 'inline', label,
        JX.bind(this, this._onkeyreply, true));

      label = pht('Add new inline comment on selected source text.');
      this._installKey('c', 'inline', label,
        JX.bind(this, this._onKeyCreate));

      label = pht('Edit selected inline comment.');
      this._installKey('e', 'inline', label, this._onkeyedit);

      label = pht('Mark or unmark selected inline comment as done.');
      this._installKey('w', 'inline', label, this._onkeydone);

      label = pht('Collapse or expand inline comment.');
      this._installKey('q', 'diff-vis', label, this._onkeycollapse);

      label = pht('Hide or show all inline comments.');
      this._installKey('A', 'diff-vis', label, this._onkeyhideall);

      label = pht('Show path in repository.');
      this._installKey('d', 'diff-nav', label, this._onkeyshowpath);

      label = pht('Show directory in repository.');
      this._installKey('D', 'diff-nav', label, this._onkeyshowdirectory);

      label = pht('Open file in external editor.');
      this._installKey('\\', 'diff-nav', label, this._onkeyopeneditor);
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

    _installKey: function(key, group, label, handler) {
      handler = JX.bind(this, this._ifawake, handler);

      return new JX.KeyboardShortcut(key, label)
        .setHandler(handler)
        .setGroup(group)
        .register();
    },

    _installJumpKey: function(key, label, delta, filter, show_collapsed) {
      filter = filter || null;

      var options = {
        filter: filter,
        collapsed: show_collapsed
      };

      var handler = JX.bind(this, this._onjumpkey, delta, options);
      return this._installKey(key, 'diff-nav', label, handler);
    },

    _ontoc: function(manager) {
      var toc = JX.$('toc');
      manager.scrollTo(toc);
    },

    _oncomments: function(manager) {
      var reply = JX.$('reply');
      manager.scrollTo(reply);
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
            inline.reply(is_quote);
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
          var cells = this._getLineNumberCellsForChangeBlock(
            cursor.nodes.begin,
            cursor.nodes.end);

          cursor.changeset.newInlineForRange(cells.src, cells.dst);

          this.setFocus(null);
          return;
        }
      }

      var pht = this.getTranslations();
      this._warnUser(pht('You must select a comment or change to reply to.'));
    },

    _getLineNumberCellsForChangeBlock: function(origin, target) {
      // The "origin" and "target" are entire rows, but we need to find
      // a range of cell nodes to actually create an inline, so go
      // fishing.

      var old_list = [];
      var new_list = [];

      var row = origin;
      while (row) {
        var header = row.firstChild;
        while (header) {
          if (this.getLineNumberFromHeader(header)) {
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

      return {
        src: src,
        dst: dst
      };
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

    _onKeyCreate: function() {
      var start = this._sourceSelectionStart;
      var end = this._sourceSelectionEnd;

      if (!this._sourceSelectionStart) {
        var pht = this.getTranslations();
        this._warnUser(
          pht(
            'You must select source text to create a new inline comment.'));
        return;
      }

      this._setSourceSelection(null, null);

      var changeset = start.changeset;

      var config = {};
      if (changeset.getResponseDocumentEngineKey() === null) {
        // If the changeset is using a document renderer, we ignore the
        // selection range and just treat this as a comment from the first
        // block to the last block.

        // If we don't discard the range, we later render a bogus highlight
        // if the block content is complex (like a Jupyter notebook cell
        // with images).

        config.startOffset = start.offset;
        config.endOffset = end.offset;
      }

      changeset.newInlineForRange(start.targetNode, end.targetNode, config);
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
      var pht = this.getTranslations();
      var changeset = this._getChangesetForKeyCommand();

      if (!changeset) {
        this._warnUser(pht('You must select a file to hide or show.'));
        return;
      }

      changeset.toggleVisibility();
    },

    _getChangesetForKeyCommand: function() {
      var cursor = this._cursorItem;

      var changeset;
      if (cursor) {
        changeset = cursor.changeset;
      }

      if (!changeset) {
        changeset = this._getVisibleChangeset();
      }

      return changeset;
    },

    _onkeyopeneditor: function(e) {
      var pht = this.getTranslations();
      var changeset = this._getChangesetForKeyCommand();

      if (!changeset) {
        this._warnUser(pht('You must select a file to edit.'));
        return;
      }

      this._openEditor(changeset);
    },

    _openEditor: function(changeset) {
      var pht = this.getTranslations();

      var editor_template = changeset.getEditorURITemplate();
      if (editor_template === null) {
        this._warnUser(pht('No external editor is configured.'));
        return;
      }

      var line = null;

      // See PHI1749. We aren't exactly sure what the user intends when they
      // use the keyboard to select a change block and then activate the
      // "Open in Editor" function: they might mean to open the old or new
      // offset, and may have the old or new state (or some other state) in
      // their working copy.

      // For now, pick: the new state line number if one exists; or the old
      // state line number if one does not. If nothing else, this behavior is
      // simple.

      // If there's a document engine, just open the file to the first line.
      // We currently can not map display blocks to source lines.

      // If there's an inline, open the file to that line.

      if (changeset.getResponseDocumentEngineKey() === null) {
        var cursor = this._cursorItem;
        if (cursor && (cursor.changeset === changeset)) {
          if (cursor.type == 'change') {
            var cells = this._getLineNumberCellsForChangeBlock(
              cursor.nodes.begin,
              cursor.nodes.end);
            line = this.getLineNumberFromHeader(cells.src);
          }

          if (cursor.type === 'comment') {
            var inline = cursor.target;
            line = inline.getLineNumber();
          }
        }
      }

      var variables = {
        l: line || 1
      };

      var editor_uri = new JX.ExternalEditorLinkEngine()
        .setTemplate(editor_template)
        .setVariables(variables)
        .newURI();

      JX.$U(editor_uri).go();
    },

    _onkeyshowpath: function() {
      this._onrepositorykey(false);
    },

    _onkeyshowdirectory: function() {
      this._onrepositorykey(true);
    },

    _onrepositorykey: function(is_directory) {
      var pht = this.getTranslations();
      var changeset = this._getChangesetForKeyCommand();

      if (!changeset) {
        this._warnUser(pht('You must select a file to open.'));
        return;
      }

      var show_uri;
      if (is_directory) {
        show_uri = changeset.getShowDirectoryURI();
      } else {
        show_uri = changeset.getShowPathURI();
      }

      if (show_uri === null) {
        return;
      }

      window.open(show_uri);
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

    selectChangeset: function(changeset, scroll) {
      var items = this._getSelectableItems();

      var cursor = null;
      for (var ii = 0; ii < items.length; ii++) {
        var item = items[ii];
        if (changeset === item.target) {
          cursor = ii;
          break;
        }
      }

      if (cursor !== null) {
        this._setSelectionState(items[cursor], scroll);
      } else {
        this._setSelectionState(null, false);
      }

      return this;
    },

    _setSelectionState: function(item, scroll) {
      var old = this._cursorItem;

      if (old) {
        if (old.type === 'comment') {
          old.target.setIsSelected(false);
        }
      }

      this._cursorItem = item;

      if (item) {
        if (item.type === 'comment') {
          item.target.setIsSelected(true);
        }
      }

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

      var changeset = cursor.changeset;

      var tree = this._getTreeView();
      if (changeset) {
        tree.setSelectedPath(cursor.changeset.getPathView());
      } else {
        tree.setSelectedPath(null);
      }

      this._selectChangeset(changeset);

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

      var menu = new JX.PHUIXDropdownMenu(button)
        .setWidth(240);
      var list = new JX.PHUIXActionListView();

      var add_link = function(icon, name, href, local) {
        var link = new JX.PHUIXActionView()
          .setIcon(icon)
          .setName(name)
          .setHandler(function(e) {
            if (local) {
              window.location.assign(href);
            } else {
              window.open(href);
            }
            menu.close();
            e.prevent();
          });

        if (href) {
          link.setHref(href);
        } else {
          link
            .setDisabled(true)
            .setUnresponsive(true);
        }

        list.addItem(link);
        return link;
      };

      var visible_item = new JX.PHUIXActionView()
        .setKeyCommand('h')
        .setHandler(function(e) {
          e.prevent();
          menu.close();

          changeset.select(false);
          changeset.toggleVisibility();
        });
      list.addItem(visible_item);

      var reveal_item = new JX.PHUIXActionView()
        .setIcon('fa-eye');
      list.addItem(reveal_item);

      list.addItem(
        new JX.PHUIXActionView()
          .setDivider(true));

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

            var renderer = changeset.getRendererKey();
            if (renderer == '1up') {
              renderer = '2up';
            } else {
              renderer = '1up';
            }
            changeset.reload({renderer: renderer});
          } else {
            changeset.reload();
          }

          e.prevent();
          menu.close();
        });
      list.addItem(up_item);

      var encoding_item = new JX.PHUIXActionView()
        .setIcon('fa-font')
        .setName(pht('Change Text Encoding...'))
        .setHandler(function(e) {
          var params = {
            encoding: changeset.getCharacterEncoding()
          };

          new JX.Workflow('/services/encoding/', params)
            .setHandler(function(r) {
              changeset.reload({encoding: r.encoding});
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
              changeset.reload({highlight: r.highlight});
            })
            .start();

          e.prevent();
          menu.close();
        });
      list.addItem(highlight_item);

      var engine_item = new JX.PHUIXActionView()
        .setIcon('fa-file-image-o')
        .setName(pht('View As Document Type...'))
        .setHandler(function(e) {
          var options = changeset.getAvailableDocumentEngineKeys() || [];
          options = options.join(',');

          var params = {
            engine: changeset.getResponseDocumentEngineKey(),
            options: options
          };

          new JX.Workflow('/services/viewas/', params)
            .setHandler(function(r) {
              changeset.reload({engine: r.engine});
            })
            .start();

          e.prevent();
          menu.close();
        });
      list.addItem(engine_item);

      list.addItem(
        new JX.PHUIXActionView()
          .setDivider(true));

      add_link('fa-external-link', pht('View Standalone'), data.standaloneURI);

      add_link('fa-arrow-left', pht('Show Raw File (Left)'), data.leftURI);
      add_link('fa-arrow-right', pht('Show Raw File (Right)'), data.rightURI);

      add_link(
        'fa-folder-open-o',
        pht('Show Directory in Repository'),
        changeset.getShowDirectoryURI())
        .setKeyCommand('D');

      add_link(
        'fa-file-text-o',
        pht('Show Path in Repository'),
        changeset.getShowPathURI())
        .setKeyCommand('d');

      var editor_template = changeset.getEditorURITemplate();
      if (editor_template !== null) {
        var editor_item = new JX.PHUIXActionView()
          .setIcon('fa-i-cursor')
          .setName(pht('Open in Editor'))
          .setKeyCommand('\\')
          .setHandler(function(e) {

            changeset_list._openEditor(changeset);

            e.prevent();
            menu.close();
          });

        list.addItem(editor_item);
      } else {
        var configure_uri = changeset.getEditorConfigureURI();
        if (configure_uri !== null) {
          add_link('fa-wrench', pht('Configure Editor'), configure_uri);
        }
      }

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
            .setIcon('fa-arrows-v')
            .setHandler(function(e) {
              changeset.loadAllContext();
              e.prevent();
              menu.close();
            });
        } else {
          reveal_item
            .setDisabled(true)
            .setUnresponsive(true)
            .setIcon('fa-file')
            .setName(pht('All Context Shown'))
            .setHref(null);
        }

        encoding_item.setDisabled(!changeset.isLoaded());
        highlight_item.setDisabled(!changeset.isLoaded());
        engine_item.setDisabled(!changeset.isLoaded());

        if (changeset.isLoaded()) {
          if (changeset.getRendererKey() == '2up') {
            up_item
              .setIcon('fa-list-alt')
              .setName(pht('View Unified Diff'));
          } else {
            up_item
              .setIcon('fa-columns')
              .setName(pht('View Side-by-Side Diff'));
          }
        } else {
          up_item
            .setIcon('fa-refresh')
            .setName(pht('Load Changes'));
        }

        visible_item
          .setDisabled(true)
          .setIcon('fa-eye-slash')
          .setName(pht('Hide Changeset'));

        var diffs = JX.DOM.scry(
          JX.$(data.containerID),
          'table',
          'differential-diff');

        if (diffs.length > 1) {
          JX.$E(
            'More than one node with sigil "differential-diff" was found in "'+
            data.containerID+'."');
        } else if (diffs.length == 1) {
          visible_item.setDisabled(false);
        } else {
          // Do nothing when there is no diff shown in the table. For example,
          // the file is binary.
        }

      });

      data.menu = menu;
      changeset.setViewMenu(menu);
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

      // Force a banner redraw after a resize event. Particularly, this makes
      // sure the inline state updates immediately after an inline edit
      // operation, even if the changeset itself has not changed.
      this._bannerChangeset = null;

      this._redrawBanner();

      var changesets = this._changesets;
      for (var ii = 0; ii < changesets.length; ii++) {
        changesets[ii].redrawFileTree();
      }
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

      // If the user has double-clicked or triple-clicked a header, we want to
      // toggle the inline selection mode, not select text. Kill select events
      // originating with this element as the target.
      if (e.getType() === 'selectstart') {
        e.kill();
        return;
      }

      var inline = this._getInlineForEvent(e);
      if (!inline) {
        return;
      }

      // NOTE: Don't kill or prevent the event. In particular, we want this
      // click to clear any text selection as it normally would.

      this.selectInline(inline);
    },

    selectInline: function(inline, force, scroll) {
      var selection = this._getSelectionState();
      var item;

      if (!force) {
        // If the comment the user clicked is currently selected, deselect it.
        // This makes it easy to undo things if you clicked by mistake.
        if (selection.cursor !== null) {
          item = selection.items[selection.cursor];
          if (item.target === inline) {
            this._setSelectionState(null, false);
            return;
          }
        }
      }

      // Otherwise, select the item that the user clicked. This makes it
      // easier to resume keyboard operations after using the mouse to do
      // something else.
      var items = selection.items;
      for (var ii = 0; ii < items.length; ii++) {
        item = items[ii];
        if (item.target === inline) {
          this._setSelectionState(item, scroll);
        }
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
      if (!node) {
        var tree = this._getTreeView();
        tree.setSelectedPath(null);
        this._selectChangeset(null);
      }

      this._focusStart = node;
      this._focusEnd = extended_node;
      this._redrawFocus();
    },

    _selectChangeset: function(changeset) {
      if (this._selectedChangeset === changeset) {
        return;
      }

      if (this._selectedChangeset !== null) {
        this._selectedChangeset.setIsSelected(false);
        this._selectedChangeset = null;
      }

      this._selectedChangeset = changeset;
      if (this._selectedChangeset !== null) {
        this._selectedChangeset.setIsSelected(true);
      }
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
      var d = JX.Vector.getDim(node);

      p.add(s).add(d.x + 1, 4).setPos(reticle);
      // Compute the size we need to extend to the full extent of the focused
      // nodes.
      JX.Vector.getPos(extended_node)
        .add(-p.x, -p.y)
        .add(0, JX.Vector.getDim(extended_node).y)
        .add(10, -4)
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
      var origin = null;
      var target = null;

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
          origin = JX.$(prefix + number);
          target = JX.$(prefix + (number + length));
        } catch (error) {
          // There may not be any nodes present in the document. A case where
          // this occurs is when you reply to a ghost inline which was made
          // on lines near the bottom of "long.txt" in an earlier diff, and
          // the file was later shortened so those lines no longer exist. For
          // more details, see T11662.

          origin = null;
          target = null;
        }
      }

      this._setHoverRange(origin, target, inline);
    },

    _setHoverRange: function(origin, target, inline) {
      inline = inline || null;

      var origin_dirty = (origin !== this._hoverOrigin);
      var target_dirty = (target !== this._hoverTarget);
      var inline_dirty = (inline !== this._hoverInline);

      var any_dirty = (origin_dirty || target_dirty || inline_dirty);
      if (any_dirty) {
        this._hoverOrigin = origin;
        this._hoverTarget = target;
        this._hoverInline = inline;
        this._redrawHover();
      }
    },

    resetHover: function() {
      this._setHoverRange(null, null, null);
    },

    _redrawHover: function() {
      var map = this._hoverMap;
      if (map) {
        this._hoverMap = null;
        this._applyHoverHighlight(map, false);
      }

      var rows = this._hoverRows;
      if (rows) {
        this._hoverRows = null;
        this._applyHoverHighlight(rows, false);
      }

      if (!this._hoverOrigin || this.isAsleep()) {
        return;
      }

      var top = this._hoverOrigin;
      var bot = this._hoverTarget;
      if (JX.$V(top).y > JX.$V(bot).y) {
        var tmp = top;
        top = bot;
        bot = tmp;
      }

      // Find the leftmost cell that we're going to highlight. This is the
      // next sibling with a "data-copy-mode" attribute, which is a marker
      // for the cell with actual content in it.
      var content_cell = top;
      while (content_cell && !this._isContentCell(content_cell)) {
        content_cell = content_cell.nextSibling;
      }

      // If we didn't find a cell to highlight, don't highlight anything.
      if (!content_cell) {
        return;
      }

      rows = this._findContentCells(top, bot, content_cell);

      var inline = this._hoverInline;
      if (!inline) {
        this._hoverRows = rows;
        this._applyHoverHighlight(this._hoverRows, true);
        return;
      }

      if (!inline.hoverMap) {
        inline.hoverMap = this._newHoverMap(rows, inline);
      }

      this._hoverMap = inline.hoverMap;
      this._applyHoverHighlight(this._hoverMap, true);
    },

    _applyHoverHighlight: function(items, on) {
      for (var ii = 0; ii < items.length; ii++) {
        var item = items[ii];

        JX.DOM.alterClass(item.lineNode, 'inline-hover', on);
        JX.DOM.alterClass(item.cellNode, 'inline-hover', on);

        if (item.bright) {
          JX.DOM.alterClass(item.cellNode, 'inline-hover-bright', on);
        }

        if (item.hoverNode) {
          if (on) {
            item.cellNode.insertBefore(
              item.hoverNode,
              item.cellNode.firstChild);
          } else {
            JX.DOM.remove(item.hoverNode);
          }
        }
      }
    },

    _findContentCells: function(top, bot, content_cell) {
      var head_row = JX.DOM.findAbove(top, 'tr');
      var last_row = JX.DOM.findAbove(bot, 'tr');

      var cursor = head_row;
      var rows = [];
      var idx = null;
      var ii;
      var line_cell = null;
      do {
        line_cell = null;
        for (ii = 0; ii < cursor.childNodes.length; ii++) {
          var child = cursor.childNodes[ii];
          if (!JX.DOM.isType(child, 'td')) {
            continue;
          }

          if (child.getAttribute('data-n')) {
            line_cell = child;
          }

          if (child === content_cell) {
            idx = ii;
          }

          if (ii !== idx) {
            continue;
          }

          if (this._isContentCell(child)) {
            rows.push({
              lineNode: line_cell,
              cellNode: child
            });
          }

          break;
        }

        if (cursor === last_row) {
          break;
        }

        cursor = cursor.nextSibling;
      } while (cursor);

      return rows;
    },

    _newHoverMap: function(rows, inline) {
      var start = inline.getStartOffset();
      var end = inline.getEndOffset();

      var info;
      var content;
      for (ii = 0; ii < rows.length; ii++) {
        info = this._getSelectionOffset(rows[ii].cellNode, null);

        content = info.content;
        content = content.replace(/\n+$/, '');

        rows[ii].content = content;
      }

      var attr_dull = {
        className: 'inline-hover-text'
      };

      var attr_bright = {
        className: 'inline-hover-text inline-hover-text-bright'
      };

      var attr_container = {
        className: 'inline-hover-container'
      };

      var min = 0;
      var max = rows.length - 1;
      var offset_min;
      var offset_max;
      var len;
      var node;
      var text;
      var any_highlight = false;
      for (ii = 0; ii < rows.length; ii++) {
        content = rows[ii].content;
        len = content.length;

        if (ii === min && (start !== null)) {
          offset_min = start;
        } else {
          offset_min = 0;
        }

        if (ii === max && (end !== null)) {
          offset_max = Math.min(end, len);
        } else {
          offset_max = len;
        }

        var has_min = (offset_min > 0);
        var has_max = (offset_max < len);

        if (has_min || has_max) {
          any_highlight = true;
        }

        rows[ii].min = offset_min;
        rows[ii].max = offset_max;
        rows[ii].hasMin = has_min;
        rows[ii].hasMax = has_max;
      }

      for (ii = 0; ii < rows.length; ii++) {
        content = rows[ii].content;
        offset_min = rows[ii].min;
        offset_max = rows[ii].max;

        var has_highlight = (rows[ii].hasMin || rows[ii].hasMax);

        if (any_highlight) {
          var parts = [];

          if (offset_min > 0) {
            text = content.substring(0, offset_min);
            node = JX.$N('span', attr_dull, text);
            parts.push(node);
          }

          if (len) {
            text = content.substring(offset_min, offset_max);
            node = JX.$N('span', attr_bright, text);
            parts.push(node);
          }

          if (offset_max < len) {
            text = content.substring(offset_max, len);
            node = JX.$N('span', attr_dull, text);
            parts.push(node);
          }

          rows[ii].hoverNode = JX.$N('div', attr_container, parts);
        } else {
          rows[ii].hoverNode = null;
        }

        rows[ii].bright = (any_highlight && !has_highlight);
      }

      return rows;
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

    getLineNumberFromHeader: function(node) {
      var n = parseInt(node.getAttribute('data-n'));

      if (!n) {
        return null;
      }

      // If this is a line number that's part of a row showing more context,
      // we don't want to let users leave inlines here.

      try {
        JX.DOM.findAbove(node, 'tr', 'context-target');
        return null;
      } catch (ex) {
        // Ignore.
      }

      return n;
    },

    getDisplaySideFromHeader: function(th) {
      return (th.parentNode.firstChild != th) ? 'right' : 'left';
    },

    _onrangedown: function(e) {
      // NOTE: We're allowing "mousedown" from a touch event through so users
      // can leave inlines on a single line.

      // See PHI985. We want to exclude both right-mouse and middle-mouse
      // clicks from continuing.
      if (!e.isLeftButton()) {
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
      // Don't update the range if this target doesn't correspond to a line
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
      var tree = this._getTreeView();
      var formation = this.getFormationView();

      if (!changeset) {
        this._bannerChangeset = null;
        JX.DOM.remove(node);
        tree.setFocusedPath(null);

        if (formation) {
          formation.repaint();
        }

        return;
      }

      // Don't do anything if nothing has changed. This seems to avoid some
      // flickering issues in Safari, at least.
      if (this._bannerChangeset === changeset) {
        return;
      }
      this._bannerChangeset = changeset;

      var paths = tree.getPaths();
      for (var ii = 0; ii < paths.length; ii++) {
        var path = paths[ii];
        if (path.getChangeset() === changeset) {
          tree.setFocusedPath(path);
        }
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

      if (formation) {
        formation.repaint();
      }
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
        var pht = this.getTranslations();

        var button = new JX.PHUIXButtonView()
          .setIcon('fa-bars')
          .setButtonType(JX.PHUIXButtonView.BUTTONTYPE_SIMPLE)
          .setAuralLabel(pht('Display Options'));

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
    },

    _getTreeView: function() {
      if (!this._treeView) {
        var tree = new JX.DiffTreeView();

        for (var ii = 0; ii < this._changesets.length; ii++) {
          var changeset = this._changesets[ii];
          tree.addPath(changeset.getPathView());
        }

        this._treeView = tree;
      }
      return this._treeView;
    },

    _redrawFiletree : function() {
      var formation = this.getFormationView();

      if (!formation) {
        return;
      }

      var filetree = formation.getColumn(0);
      var flank = filetree.getFlank();

      var flank_body = flank.getBodyNode();

      var tree = this._getTreeView();
      JX.DOM.setContent(flank_body, tree.getNode());
    },

    _setupInlineCommentListeners: function() {
      var onsave = JX.bind(this, this._onInlineEvent, 'save');
      JX.Stratcom.listen(
        ['submit', 'didSyntheticSubmit'],
        'inline-edit-form',
        onsave);

      var oncancel = JX.bind(this, this._onInlineEvent, 'cancel');
      JX.Stratcom.listen(
        'click',
        'inline-edit-cancel',
        oncancel);

      var onundo = JX.bind(this, this._onInlineEvent, 'undo');
      JX.Stratcom.listen(
        'click',
        'differential-inline-comment-undo',
        onundo);

      var ondone = JX.bind(this, this._onInlineEvent, 'done');
      JX.Stratcom.listen(
        'click',
        ['differential-inline-comment', 'differential-inline-done'],
        ondone);

      var ondelete = JX.bind(this, this._onInlineEvent, 'delete');
      JX.Stratcom.listen(
        'click',
        ['differential-inline-comment', 'differential-inline-delete'],
        ondelete);

      var onmenu = JX.bind(this, this._onInlineEvent, 'menu');
      JX.Stratcom.listen(
        'click',
        ['differential-inline-comment', 'inline-action-dropdown'],
        onmenu);

      var ondraft = JX.bind(this, this._onInlineEvent, 'draft');
      JX.Stratcom.listen(
        'keydown',
        ['differential-inline-comment', 'tag:textarea'],
        ondraft);

      var on_preview_view = JX.bind(this, this._onPreviewEvent, 'view');
      JX.Stratcom.listen(
        'click',
        'differential-inline-preview-jump',
        on_preview_view);
    },

    _onPreviewEvent: function(action, e) {
      if (this.isAsleep()) {
        return;
      }

      var data = e.getNodeData('differential-inline-preview-jump');
      var inline = this.getInlineByID(data.inlineCommentID);
      if (!inline) {
        return;
      }

      e.kill();

      switch (action) {
        case 'view':
          this.selectInline(inline, true, true);
          break;
      }
    },

    _onInlineEvent: function(action, e) {
      if (this.isAsleep()) {
        return;
      }

      if (action !== 'draft' && action !== 'menu') {
        e.kill();
      }

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
        case 'save':
          inline.save();
          break;
        case 'cancel':
          inline.cancel();
          break;
        case 'undo':
          inline.undo();
          break;
        case 'done':
          inline.toggleDone();
          break;
        case 'delete':
          inline.delete(is_ref);
          break;
        case 'draft':
          inline.triggerDraft();
          break;
        case 'menu':
          var node = e.getNode('inline-action-dropdown');
          inline.activateMenu(node, e);
          break;
      }
    },

    _onSelectRange: function(e) {
      this._updateSourceSelection();
    },

    _updateSourceSelection: function() {
      var ranges = this._getSelectedRanges();

      // In Firefox, selecting multiple rows gives us multiple ranges. In
      // Safari and Chrome, we get a single range.
      if (!ranges.length) {
        this._setSourceSelection(null, null);
        return;
      }

      var min = 0;
      var max = ranges.length - 1;

      var head = ranges[min].startContainer;
      var last = ranges[max].endContainer;

      var head_loc = this._getFragmentLocation(head);
      var last_loc = this._getFragmentLocation(last);

      if (head_loc === null || last_loc === null) {
        this._setSourceSelection(null, null);
        return;
      }

      if (head_loc.changesetID !== last_loc.changesetID) {
        this._setSourceSelection(null, null);
        return;
      }

      head_loc.offset += ranges[min].startOffset;
      last_loc.offset += ranges[max].endOffset;

      this._setSourceSelection(head_loc, last_loc);
    },

    _setSourceSelection: function(start, end) {
      var start_updated =
        !this._isSameSourceSelection(this._sourceSelectionStart, start);

      var end_updated =
        !this._isSameSourceSelection(this._sourceSelectionEnd, end);

      if (!start_updated && !end_updated) {
        return;
      }

      this._sourceSelectionStart = start;
      this._sourceSelectionEnd = end;

      if (!start) {
        this._closeSourceSelectionMenu();
        return;
      }

      var menu;
      if (this._sourceSelectionMenu) {
        menu = this._sourceSelectionMenu;
      } else {
        menu = this._newSourceSelectionMenu();
        this._sourceSelectionMenu = menu;
      }

      var pos = JX.$V(start.node)
        .add(0, -menu.getMenuNodeDimensions().y)
        .add(0, -24);

      menu.setPosition(pos);
      menu.open();
    },

    _newSourceSelectionMenu: function() {
      var pht = this.getTranslations();

      var menu = new JX.PHUIXDropdownMenu(null)
        .setWidth(240);

      // We need to disable autofocus for this menu, since it operates on the
      // text selection in the document. If we leave this enabled, opening the
      // menu immediately discards the selection.
      menu.setDisableAutofocus(true);

      var list = new JX.PHUIXActionListView();
      menu.setContent(list.getNode());

      var oncreate = JX.bind(this, this._onSourceSelectionMenuAction, 'create');

      var comment_item = new JX.PHUIXActionView()
        .setIcon('fa-comment-o')
        .setName(pht('New Inline Comment'))
        .setKeyCommand('c')
        .setHandler(oncreate);

      list.addItem(comment_item);

      return menu;
    },

    _onSourceSelectionMenuAction: function(action, e) {
      e.kill();
      this._closeSourceSelectionMenu();

      switch (action) {
        case 'create':
          this._onKeyCreate();
          break;
      }
    },

    _closeSourceSelectionMenu: function() {
      if (this._sourceSelectionMenu) {
        this._sourceSelectionMenu.close();
      }
    },

    _isSameSourceSelection: function(u, v) {
      if (u === null && v === null) {
        return true;
      }

      if (u === null && v !== null) {
        return false;
      }

      if (u !== null && v === null) {
        return false;
      }

      return (
        (u.changesetID === v.changesetID) &&
        (u.line === v.line) &&
        (u.displayColumn === v.displayColumn) &&
        (u.offset === v.offset)
      );
    },

    _getFragmentLocation: function(fragment) {
      // Find the changeset containing the fragment.
      var changeset = null;
      try {
        var node = JX.DOM.findAbove(
          fragment,
          'div',
          'differential-changeset');

        changeset = this.getChangesetForNode(node);
        if (!changeset) {
          return null;
        }
      } catch (ex) {
        return null;
      }

      // Find the line number and display column for the fragment.
      var line = null;
      var column_count = -1;
      var has_new = false;
      var has_old = false;
      var offset = null;
      var target_node = null;
      var td;
      try {

        // NOTE: In Safari, you can carefully select an entire line and then
        // move your mouse down slightly, causing selection of an empty
        // document fragment which is an immediate child of the next "<tr />".

        // If the fragment is a direct child of a "<tr />" parent, assume the
        // user has done this and select the last child of the previous row
        // instead. It's possible there are other ways to do this, so this may
        // not always be the right rule.

        // Otherwise, select the containing "<td />".

        var is_end;
        if (JX.DOM.isType(fragment.parentNode, 'tr')) {
          // Assume this is Safari, and that the user has carefully selected a
          // row and then moved their mouse down a few pixels to select the
          // invisible fragment at the beginning of the next row.
          var cells = fragment.parentNode.previousSibling.childNodes;
          td = cells[cells.length - 1];
          is_end = true;
        } else {
          td = this._findContentCell(fragment);
          is_end = false;
        }

        var cursor = td;
        while (cursor) {
          if (cursor.getAttribute('data-copy-mode')) {
            column_count++;
          } else {
            // In unified mode, the content column isn't currently marked
            // with an attribute, and we can't count content columns anyway.
            // Keep track of whether or not we see a "NL" (New Line) column
            // and/or an "OL" (Old Line) column to try to puzzle out which
            // side of the display change we're on.

            if (cursor.id.match(/NL/)) {
              has_new = true;
            } else if (cursor.id.match(/OL/)) {
              has_old = true;
            }
          }

          var n = parseInt(cursor.getAttribute('data-n'));

          if (n) {
            if (line === null) {
              target_node = cursor;
              line = n;
            }
          }

          cursor = cursor.previousSibling;
        }

        if (!line) {
          return null;
        }

        if (column_count < 0) {
          if (has_new || has_old) {
            if (has_new) {
              column_count = 1;
            } else {
              column_count = 0;
            }
          } else {
            return null;
          }
        }

        var info = this._getSelectionOffset(td, fragment);

        if (info.found) {
          offset = info.offset;
        } else {
          if (is_end) {
            offset = info.offset;
          } else {
            offset = 0;
          }
        }
      } catch (ex) {
        return null;
      }

      var changeset_id;
      if (column_count > 0) {
        changeset_id = changeset.getRightChangesetID();
      } else {
        changeset_id = changeset.getLeftChangesetID();
      }

      return {
        node: td,
        changeset: changeset,
        changesetID: changeset_id,
        line: line,
        displayColumn: column_count,
        offset: offset,
        targetNode: target_node
      };
    },

    _getSelectionOffset: function(node, target) {
      // If this is an aural hint node in a unified diff, ignore it when
      // calculating the selection offset.
      if (node.getAttribute && node.getAttribute('data-aural')) {
        return {
          offset: 0,
          content: '',
          found: false
        };
      }

      if (!node.childNodes || !node.childNodes.length) {
        return {
          offset: node.textContent.length,
          content: node.textContent,
          found: false
        };
      }

      var found = false;
      var offset = 0;
      var content = '';
      for (var ii = 0; ii < node.childNodes.length; ii++) {
        var child = node.childNodes[ii];

        if (child === target) {
          found = true;
        }

        var spec = this._getSelectionOffset(child, target);

        content += spec.content;
        if (!found) {
          offset += spec.offset;
        }

        found = found || spec.found;
      }

      return {
        offset: offset,
        content: content,
        found: found
      };
    },

    _getSelectedRanges: function() {
      var ranges = [];

      if (!window.getSelection) {
        return ranges;
      }

      var selection = window.getSelection();
      for (var ii = 0; ii < selection.rangeCount; ii++) {
        var range = selection.getRangeAt(ii);
        if (range.collapsed) {
          continue;
        }

        ranges.push(range);
      }

      return ranges;
    },

    _isContentCell: function(node) {
      return !!node.getAttribute('data-copy-mode');
    },

    _findContentCell: function(node) {
      var cursor = node;
      while (true) {
        cursor = JX.DOM.findAbove(cursor, 'td');
        if (this._isContentCell(cursor)) {
          return cursor;
        }
      }
    }

  }

});
