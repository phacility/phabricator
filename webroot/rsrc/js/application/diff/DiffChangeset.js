/**
 * @provides phabricator-diff-changeset
 * @requires javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-install
 *           javelin-workflow
 *           javelin-router
 *           javelin-behavior-device
 *           javelin-vector
 *           phabricator-diff-inline
 * @javelin
 */


JX.install('DiffChangeset', {

  construct : function(node) {
    this._node = node;

    var data = this._getNodeData();

    this._renderURI = data.renderURI;
    this._ref = data.ref;
    this._whitespace = data.whitespace;
    this._renderer = data.renderer;
    this._highlight = data.highlight;
    this._encoding = data.encoding;
    this._loaded = data.loaded;

    this._leftID = data.left;
    this._rightID = data.right;

    this._displayPath = JX.$H(data.displayPath);
    this._icon = data.icon;

    this._inlines = [];
  },

  members: {
    _node: null,
    _loaded: false,
    _sequence: 0,
    _stabilize: false,

    _renderURI: null,
    _ref: null,
    _whitespace: null,
    _renderer: null,
    _highlight: null,
    _encoding: null,
    _undoTemplates: null,

    _leftID: null,
    _rightID: null,

    _inlines: null,
    _visible: true,

    _undoNode: null,
    _displayPath: null,

    _changesetList: null,
    _icon: null,

    getLeftChangesetID: function() {
      return this._leftID;
    },

    getRightChangesetID: function() {
      return this._rightID;
    },

    setChangesetList: function(list) {
      this._changesetList = list;
      return this;
    },

    getIcon: function() {
      if (!this._visible) {
        return 'fa-file-o';
      }

      return this._icon;
    },

    getColor: function() {
      if (!this._visible) {
        return 'grey';
      }

      return 'blue';
    },

    getChangesetList: function() {
      return this._changesetList;
    },

    /**
     * Has the content of this changeset been loaded?
     *
     * This method returns `true` if a request has been fired, even if the
     * response has not returned yet.
     *
     * @return bool True if the content has been loaded.
     */
    isLoaded: function() {
      return this._loaded;
    },


    /**
     * Configure stabilization of the document position on content load.
     *
     * When we dump the changeset into the document, we can try to stabilize
     * the document scroll position so that the user doesn't feel like they
     * are jumping around as things load in. This is generally useful when
     * populating initial changes.
     *
     * However, if a user explicitly requests a content load by clicking a
     * "Load" link or using the dropdown menu, this stabilization generally
     * feels unnatural, so we don't use it in response to explicit user action.
     *
     * @param bool  True to stabilize the next content fill.
     * @return this
     */
    setStabilize: function(stabilize) {
      this._stabilize = stabilize;
      return this;
    },


    /**
     * Should this changeset load immediately when the page loads?
     *
     * Normally, changes load immediately, but if a diff or commit is very
     * large we stop doing this and have the user load files explicitly, or
     * choose to load everything.
     *
     * @return bool True if the changeset should load automatically when the
     *   page loads.
     */
    shouldAutoload: function() {
      return this._getNodeData().autoload;
    },


    /**
     * Load this changeset, if it isn't already loading.
     *
     * This fires a request to fill the content of this changeset, provided
     * there isn't already a request in flight. To force a reload, use
     * @{method:reload}.
     *
     * @return this
     */
    load: function() {
      if (this._loaded) {
        return this;
      }

      return this.reload();
    },


    /**
     * Reload the changeset content.
     *
     * This method always issues a request, even if the content is already
     * loading. To load conditionally, use @{method:load}.
     *
     * @return this
     */
    reload: function() {
      this._loaded = true;
      this._sequence++;

      var params = this._getViewParameters();
      var pht = this.getChangesetList().getTranslations();

      var workflow = new JX.Workflow(this._renderURI, params)
        .setHandler(JX.bind(this, this._onresponse, this._sequence));

      this._startContentWorkflow(workflow);

      JX.DOM.setContent(
        this._getContentFrame(),
        JX.$N(
          'div',
          {className: 'differential-loading'},
          pht('Loading...')));

      return this;
    },

    /**
     * Load missing context in a changeset.
     *
     * We do this when the user clicks "Show X Lines". We also expand all of
     * the missing context when they "Show All Context".
     *
     * @param string Line range specification, like "0-40/0-20".
     * @param node Row where the context should be rendered after loading.
     * @param bool True if this is a bulk load of multiple context blocks.
     * @return this
     */
    loadContext: function(range, target, bulk) {
      var params = this._getViewParameters();
      params.range = range;

      var pht = this.getChangesetList().getTranslations();

      var container = JX.DOM.scry(target, 'td')[0];
      JX.DOM.setContent(container, pht('Loading...'));
      JX.DOM.alterClass(target, 'differential-show-more-loading', true);

      var workflow = new JX.Workflow(this._renderURI, params)
        .setHandler(JX.bind(this, this._oncontext, target));

      if (bulk) {
        // If we're loading a bunch of these because the viewer clicked
        // "Show All Context" or similar, use lower-priority requests
        // and draw a progress bar.
        this._startContentWorkflow(workflow);
      } else {
        // If this is a single click on a context link, use a higher priority
        // load without a chrome change.
        workflow.start();
      }

      return this;
    },

    loadAllContext: function() {
      var nodes = JX.DOM.scry(this._node, 'tr', 'context-target');
      for (var ii = 0; ii < nodes.length; ii++) {
        var show = JX.DOM.scry(nodes[ii], 'a', 'show-more');
        for (var jj = 0; jj < show.length; jj++) {
          var data = JX.Stratcom.getData(show[jj]);
          if (data.type != 'all') {
            continue;
          }
          this.loadContext(data.range, nodes[ii], true);
        }
      }
    },

    _startContentWorkflow: function(workflow) {
      var routable = workflow.getRoutable();

      routable
        .setPriority(500)
        .setType('content')
        .setKey(this._getRoutableKey());

      JX.Router.getInstance().queue(routable);
    },

    getDisplayPath: function() {
      return this._displayPath;
    },

    /**
     * Receive a response to a context request.
     */
    _oncontext: function(target, response) {
      // TODO: This should be better structured.
      // If the response comes back with several top-level nodes, the last one
      // is the actual context; the others are headers. Add any headers first,
      // then copy the new rows into the document.
      var markup = JX.$H(response.changeset).getFragment();
      var len = markup.childNodes.length;
      var diff = JX.DOM.findAbove(target, 'table', 'differential-diff');

      for (var ii = 0; ii < len - 1; ii++) {
        diff.parentNode.insertBefore(markup.firstChild, diff);
      }

      var table = markup.firstChild;
      var root = target.parentNode;
      this._moveRows(table, root, target);
      root.removeChild(target);

      this._onchangesetresponse(response);
    },

    _moveRows: function(src, dst, before) {
      var rows = JX.DOM.scry(src, 'tr');
      for (var ii = 0; ii < rows.length; ii++) {

        // Find the table this <tr /> belongs to. If it's a sub-table, like a
        // table in an inline comment, don't copy it.
        if (JX.DOM.findAbove(rows[ii], 'table') !== src) {
          continue;
        }

        if (before) {
          dst.insertBefore(rows[ii], before);
        } else {
          dst.appendChild(rows[ii]);
        }
      }
    },

    /**
     * Get parameters which define the current rendering options.
     */
    _getViewParameters: function() {
      return {
        ref: this._ref,
        whitespace: this._whitespace || '',
        renderer: this.getRenderer() || '',
        highlight: this._highlight || '',
        encoding: this._encoding || ''
      };
    },

    /**
     * Get the active @{class:JX.Routable} for this changeset.
     *
     * After issuing a request with @{method:load} or @{method:reload}, you
     * can adjust routable settings (like priority) by querying the routable
     * with this method. Note that there may not be a current routable.
     *
     * @return JX.Routable|null Active routable, if one exists.
     */
    getRoutable: function() {
      return JX.Router.getInstance().getRoutableByKey(this._getRoutableKey());
    },

    setRenderer: function(renderer) {
      this._renderer = renderer;
      return this;
    },

    getRenderer: function() {
      if (this._renderer !== null) {
        return this._renderer;
      }

      // NOTE: If you load the page at one device resolution and then resize to
      // a different one we don't re-render the diffs, because it's a
      // complicated mess and you could lose inline comments, cursor positions,
      // etc.
      return (JX.Device.getDevice() == 'desktop') ? '2up' : '1up';
    },

    getUndoTemplates: function() {
      return this._undoTemplates;
    },

    setEncoding: function(encoding) {
      this._encoding = encoding;
      return this;
    },

    getEncoding: function() {
      return this._encoding;
    },

    setHighlight: function(highlight) {
      this._highlight = highlight;
      return this;
    },

    getHighlight: function() {
      return this._highlight;
    },

    getSelectableItems: function() {
      var items = [];

      items.push({
        type: 'file',
        changeset: this,
        target: this,
        nodes: {
          begin: this._node,
          end: null
        }
      });

      if (!this._visible) {
        return items;
      }

      var rows = JX.DOM.scry(this._node, 'tr');

      var blocks = [];
      var block;
      var ii;
      for (ii = 0; ii < rows.length; ii++) {
        var type = this._getRowType(rows[ii]);

        if (!block || (block.type !== type)) {
          block = {
            type: type,
            items: []
          };
          blocks.push(block);
        }

        block.items.push(rows[ii]);
      }

      var last_inline = null;
      var last_inline_item = null;
      for (ii = 0; ii < blocks.length; ii++) {
        block = blocks[ii];

        if (block.type == 'change') {
          items.push({
            type: block.type,
            changeset: this,
            target: block.items[0],
            nodes: {
              begin: block.items[0],
              end: block.items[block.items.length - 1]
            }
          });
        }

        if (block.type == 'comment') {
          for (var jj = 0; jj < block.items.length; jj++) {
            var inline = this.getInlineForRow(block.items[jj]);

            // When comments are being edited, they have a hidden row with
            // the actual comment and then a visible row with the editor.

            // In this case, we only want to generate one item, but it should
            // use the editor as a scroll target. To accomplish this, check if
            // this row has the same inline as the previous row. If so, update
            // the last item to use this row's nodes.

            if (inline === last_inline) {
              last_inline_item.nodes.begin = block.items[jj];
              last_inline_item.nodes.end = block.items[jj];
              continue;
            } else {
              last_inline = inline;
            }

            var is_saved = (!inline.isDraft() && !inline.isEditing());

            last_inline_item = {
              type: block.type,
              changeset: this,
              target: inline,
              hidden: inline.isHidden(),
              collapsed: inline.isCollapsed(),
              deleted: !inline.getID() && !inline.isEditing(),
              nodes: {
                begin: block.items[jj],
                end: block.items[jj]
              },
              attributes: {
                unsaved: inline.isEditing(),
                anyDraft: inline.isDraft() || inline.isDraftDone(),
                undone: (is_saved && !inline.isDone()),
                done: (is_saved && inline.isDone())
              }
            };

            items.push(last_inline_item);
          }
        }
      }

      return items;
    },

    _getRowType: function(row) {
      // NOTE: Don't do "className.indexOf()" elsewhere. This is evil legacy
      // magic.

      if (row.className.indexOf('inline') !== -1) {
        return 'comment';
      }

      var cells = JX.DOM.scry(row, 'td');
      for (var ii = 0; ii < cells.length; ii++) {
        if (cells[ii].className.indexOf('old') !== -1 ||
            cells[ii].className.indexOf('new') !== -1) {
          return 'change';
        }
      }
    },

    _getNodeData: function() {
      return JX.Stratcom.getData(this._node);
    },

    getVectors: function() {
      return {
        pos: JX.$V(this._node),
        dim: JX.Vector.getDim(this._node)
      };
    },

    _onresponse: function(sequence, response) {
      if (sequence != this._sequence) {
        // If this isn't the most recent request, ignore it. This normally
        // means the user changed view settings between the time the page loaded
        // and the content filled.
        return;
      }

      // As we populate the changeset list, we try to hold the document scroll
      // position steady, so that, e.g., users who want to leave a comment on a
      // diff with a large number of changes don't constantly have the text
      // area scrolled off the bottom of the screen until the entire diff loads.
      //
      // There are several major cases here:
      //
      //  - If we're near the top of the document, never scroll.
      //  - If we're near the bottom of the document, always scroll, unless
      //    we have an anchor.
      //  - Otherwise, scroll if the changes were above (or, at least,
      //    almost entirely above) the viewport.
      //
      // We don't scroll if the changes were just near the top of the viewport
      // because this makes us scroll incorrectly when an anchored change is
      // visible. See T12779.

      var target = this._node;

      var old_pos = JX.Vector.getScroll();
      var old_view = JX.Vector.getViewport();
      var old_dim = JX.Vector.getDocument();

      // Number of pixels away from the top or bottom of the document which
      // count as "nearby".
      var sticky = 480;

      var near_top = (old_pos.y <= sticky);
      var near_bot = ((old_pos.y + old_view.y) >= (old_dim.y - sticky));

      // If we have an anchor in the URL, never stick to the bottom of the
      // page. See T11784 for discussion.
      if (window.location.hash) {
        near_bot = false;
      }

      var target_pos = JX.Vector.getPos(target);
      var target_dim = JX.Vector.getDim(target);
      var target_bot = (target_pos.y + target_dim.y);

      // Detect if the changeset is entirely (or, at least, almost entirely)
      // above us. The height here is roughly the height of the persistent
      // banner.
      var above_screen = (target_bot < old_pos.y + 64);

      // If we have a URL anchor and are currently nearby, stick to it
      // no matter what.
      var on_target = null;
      if (window.location.hash) {
        try {
          var anchor = JX.$(window.location.hash.replace('#', ''));
          if (anchor) {
            var anchor_pos = JX.$V(anchor);
            if ((anchor_pos.y > old_pos.y) &&
                (anchor_pos.y < old_pos.y + 96)) {
              on_target = anchor;
            }
          }
        } catch (ignored) {
          // If we have a bogus anchor, just ignore it.
        }
      }

      var frame = this._getContentFrame();
      JX.DOM.setContent(frame, JX.$H(response.changeset));

      if (this._stabilize) {
        if (on_target) {
          JX.DOM.scrollToPosition(old_pos.x, JX.$V(on_target).y - 60);
        } else if (!near_top) {
          if (near_bot || above_screen) {
            // Figure out how much taller the document got.
            var delta = (JX.Vector.getDocument().y - old_dim.y);
            JX.DOM.scrollToPosition(old_pos.x, old_pos.y + delta);
          }
        }
        this._stabilize = false;
      }

      this._onchangesetresponse(response);
    },

    _onchangesetresponse: function(response) {
      // Code shared by autoload and context responses.

      if (response.coverage) {
        for (var k in response.coverage) {
          try {
            JX.DOM.replace(JX.$(k), JX.$H(response.coverage[k]));
          } catch (ignored) {
            // Not terribly important.
          }
        }
      }

      if (response.undoTemplates) {
        this._undoTemplates = response.undoTemplates;
      }

      JX.Stratcom.invoke('differential-inline-comment-refresh');

      this._rebuildAllInlines();

      JX.Stratcom.invoke('resize');
    },

    _getContentFrame: function() {
      return JX.DOM.find(this._node, 'div', 'changeset-view-content');
    },

    _getRoutableKey: function() {
      return 'changeset-view.' + this._ref + '.' + this._sequence;
    },

    getInlineForRow: function(node) {
      var data = JX.Stratcom.getData(node);

      if (!data.inline) {
        var inline = new JX.DiffInline()
          .setChangeset(this)
          .bindToRow(node);

        this._inlines.push(inline);
      }

      return data.inline;
    },

    newInlineForRange: function(origin, target) {
      var list = this.getChangesetList();

      var src = list.getLineNumberFromHeader(origin);
      var dst = list.getLineNumberFromHeader(target);

      var changeset_id = null;
      var side = list.getDisplaySideFromHeader(origin);
      if (side == 'right') {
        changeset_id = this.getRightChangesetID();
      } else {
        changeset_id = this.getLeftChangesetID();
      }

      var is_new = false;
      if (side == 'right') {
        is_new = true;
      } else if (this.getRightChangesetID() != this.getLeftChangesetID()) {
        is_new = true;
      }

      var data = {
        origin: origin,
        target: target,
        number: src,
        length: dst - src,
        changesetID: changeset_id,
        displaySide: side,
        isNewFile: is_new
      };

      var inline = new JX.DiffInline()
        .setChangeset(this)
        .bindToRange(data);

      this._inlines.push(inline);

      inline.create();

      return inline;
    },

    newInlineReply: function(original, text) {
      var inline = new JX.DiffInline()
        .setChangeset(this)
        .bindToReply(original);

      this._inlines.push(inline);

      inline.create(text);

      return inline;
    },

    getInlineByID: function(id) {
      return this._queryInline('id', id);
    },

    getInlineByPHID: function(phid) {
      return this._queryInline('phid', phid);
    },

    _queryInline: function(field, value) {
      // First, look for the inline in the objects we've already built.
      var inline = this._findInline(field, value);
      if (inline) {
        return inline;
      }

      // If we haven't found a matching inline yet, rebuild all the inlines
      // present in the document, then look again.
      this._rebuildAllInlines();
      return this._findInline(field, value);
    },

    _findInline: function(field, value) {
      for (var ii = 0; ii < this._inlines.length; ii++) {
        var inline = this._inlines[ii];

        var target;
        switch (field) {
          case 'id':
            target = inline.getID();
            break;
          case 'phid':
            target = inline.getPHID();
            break;
        }

        if (target == value) {
          return inline;
        }
      }

      return null;
    },

    getInlines: function() {
      this._rebuildAllInlines();
      return this._inlines;
    },

    _rebuildAllInlines: function() {
      var rows = JX.DOM.scry(this._node, 'tr');
      for (var ii = 0; ii < rows.length; ii++) {
        var row = rows[ii];
        if (this._getRowType(row) != 'comment') {
          continue;
        }

        // As a side effect, this builds any missing inline objects and adds
        // them to this Changeset's list of inlines.
        this.getInlineForRow(row);
      }
    },

    toggleVisibility: function() {
      this._visible = !this._visible;

      var diff = JX.DOM.find(this._node, 'table', 'differential-diff');
      var undo = this._getUndoNode();

      if (this._visible) {
        JX.DOM.show(diff);
        JX.DOM.remove(undo);
      } else {
        JX.DOM.hide(diff);
        JX.DOM.appendContent(diff.parentNode, undo);
      }

      JX.Stratcom.invoke('resize');
    },

    isVisible: function() {
      return this._visible;
    },

    _getUndoNode: function() {
      if (!this._undoNode) {
        var pht = this.getChangesetList().getTranslations();

        var link_attributes = {
          href: '#'
        };

        var undo_link = JX.$N('a', link_attributes, pht('Show Content'));

        var onundo = JX.bind(this, this._onundo);
        JX.DOM.listen(undo_link, 'click', null, onundo);

        var node_attributes = {
          className: 'differential-collapse-undo'
        };

        var node_content = [
          pht('This file content has been collapsed.'),
          ' ',
          undo_link
        ];

        var undo_node = JX.$N('div', node_attributes, node_content);

        this._undoNode = undo_node;
      }

      return this._undoNode;
    },

    _onundo: function(e) {
      e.kill();
      this.toggleVisibility();
    }
  },

  statics: {
    getForNode: function(node) {
      var data = JX.Stratcom.getData(node);
      if (!data.changesetViewManager) {
        data.changesetViewManager = new JX.DiffChangeset(node);
      }
      return data.changesetViewManager;
    }
  }
});
