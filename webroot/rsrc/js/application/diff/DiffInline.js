/**
 * @provides phabricator-diff-inline
 * @requires javelin-dom
 * @javelin
 */

JX.install('DiffInline', {

  construct : function() {
  },

  members: {
    _id: null,
    _phid: null,
    _changesetID: null,
    _row: null,
    _number: null,
    _length: null,
    _displaySide: null,
    _isNewFile: null,
    _undoRow: null,
    _replyToCommentPHID: null,
    _originalText: null,
    _snippet: null,

    _isDeleted: false,
    _isInvisible: false,
    _isLoading: false,

    _changeset: null,

    _isCollapsed: false,
    _isDraft: null,
    _isDraftDone: null,
    _isFixed: null,
    _isEditing: false,
    _isNew: false,
    _isSynthetic: false,
    _isHidden: false,

    bindToRow: function(row) {
      this._row = row;

      var row_data = JX.Stratcom.getData(row);
      row_data.inline = this;
      this._isCollapsed = row_data.hidden || false;

      // TODO: Get smarter about this once we do more editing, this is pretty
      // hacky.
      var comment = JX.DOM.find(row, 'div', 'differential-inline-comment');
      var data = JX.Stratcom.getData(comment);

      this._id = data.id;
      this._phid = data.phid;

      // TODO: This is very, very, very, very, very, very, very hacky.
      var td = comment.parentNode;
      var th = td.previousSibling;
      if (th.parentNode.firstChild != th) {
        this._displaySide = 'right';
      } else {
        this._displaySide = 'left';
      }

      this._number = parseInt(data.number, 10);
      this._length = parseInt(data.length, 10);
      this._originalText = data.original;
      this._isNewFile =
        (this.getDisplaySide() == 'right') ||
        (data.left != data.right);

      this._replyToCommentPHID = data.replyToCommentPHID;

      this._isDraft = data.isDraft;
      this._isFixed = data.isFixed;
      this._isGhost = data.isGhost;
      this._isSynthetic = data.isSynthetic;
      this._isDraftDone = data.isDraftDone;

      this._changesetID = data.changesetID;
      this._isNew = false;
      this._snippet = data.snippet;

      this.setInvisible(false);

      return this;
    },

    isDraft: function() {
      return this._isDraft;
    },

    isDone: function() {
      return this._isFixed;
    },

    isEditing: function() {
      return this._isEditing;
    },

    isDeleted: function() {
      return this._isDeleted;
    },

    isSynthetic: function() {
      return this._isSynthetic;
    },

    isDraftDone: function() {
      return this._isDraftDone;
    },

    isHidden: function() {
      return this._isHidden;
    },

    isGhost: function() {
      return this._isGhost;
    },

    bindToRange: function(data) {
      this._displaySide = data.displaySide;
      this._number = parseInt(data.number, 10);
      this._length = parseInt(data.length, 10);
      this._isNewFile = data.isNewFile;
      this._changesetID = data.changesetID;
      this._isNew = true;

      // Insert the comment after any other comments which already appear on
      // the same row.
      var parent_row = JX.DOM.findAbove(data.target, 'tr');
      var target_row = parent_row.nextSibling;
      while (target_row && JX.Stratcom.hasSigil(target_row, 'inline-row')) {
        target_row = target_row.nextSibling;
      }

      var row = this._newRow();
      parent_row.parentNode.insertBefore(row, target_row);

      this.setInvisible(true);

      return this;
    },

    bindToReply: function(inline) {
      this._displaySide = inline._displaySide;
      this._number = inline._number;
      this._length = inline._length;
      this._isNewFile = inline._isNewFile;
      this._changesetID = inline._changesetID;
      this._isNew = true;

      this._replyToCommentPHID = inline._phid;

      var changeset = this.getChangeset();

      // We're going to figure out where in the document to position the new
      // inline. Normally, it goes after any existing inline rows (so if
      // several inlines reply to the same line, they appear in chronological
      // order).

      // However: if inlines are threaded, we want to put the new inline in
      // the right place in the thread. This might be somewhere in the middle,
      // so we need to do a bit more work to figure it out.

      // To find the right place in the thread, we're going to look for any
      // inline which is at or above the level of the comment we're replying
      // to. This means we've reached a new fork of the thread, and should
      // put our new inline before the comment we found.
      var ancestor_map = {};
      var ancestor = inline;
      var reply_phid;
      while (ancestor) {
        reply_phid = ancestor.getReplyToCommentPHID();
        if (!reply_phid) {
          break;
        }
        ancestor_map[reply_phid] = true;
        ancestor = changeset.getInlineByPHID(reply_phid);
      }

      var parent_row = inline._row;
      var target_row = parent_row.nextSibling;
      while (target_row && JX.Stratcom.hasSigil(target_row, 'inline-row')) {
        var target = changeset.getInlineForRow(target_row);
        reply_phid = target.getReplyToCommentPHID();

        // If we found an inline which is replying directly to some ancestor
        // of this new comment, this is where the new rows go.
        if (ancestor_map.hasOwnProperty(reply_phid)) {
          break;
        }

        target_row = target_row.nextSibling;
      }

      var row = this._newRow();
      parent_row.parentNode.insertBefore(row, target_row);

      this.setInvisible(true);

      return this;
    },

    setChangeset: function(changeset) {
      this._changeset = changeset;
      return this;
    },

    getChangeset: function() {
      return this._changeset;
    },

    setEditing: function(editing) {
      this._isEditing = editing;
      return this;
    },

    setHidden: function(hidden) {
      this._isHidden = hidden;
      this._redraw();
      return this;
    },

    canReply: function() {
      if (!this._hasAction('reply')) {
        return false;
      }

      return true;
    },

    canEdit: function() {
      if (!this._hasAction('edit')) {
        return false;
      }

      return true;
    },

    canDone: function() {
      if (!JX.DOM.scry(this._row, 'input', 'differential-inline-done').length) {
        return false;
      }

      return true;
    },

    canCollapse: function() {
      if (!JX.DOM.scry(this._row, 'a', 'hide-inline').length) {
        return false;
      }

      return true;
    },

    getRawText: function() {
      return this._originalText;
    },

    _hasAction: function(action) {
      var nodes = JX.DOM.scry(this._row, 'a', 'differential-inline-' + action);
      return (nodes.length > 0);
    },

    _newRow: function() {
      var attributes = {
        sigil: 'inline-row'
      };

      var row = JX.$N('tr', attributes);

      JX.Stratcom.getData(row).inline = this;
      this._row = row;

      this._id = null;
      this._phid = null;
      this._isCollapsed = false;

      this._originalText = null;

      return row;
    },

    setCollapsed: function(collapsed) {
      this._isCollapsed = collapsed;

      var op;
      if (collapsed) {
        op = 'hide';
      } else {
        op = 'show';
      }

      var inline_uri = this._getInlineURI();
      var comment_id = this._id;

      new JX.Workflow(inline_uri, {op: op, ids: comment_id})
        .setHandler(JX.bag)
        .start();

      this._redraw();
      this._didUpdate(true);
    },

    isCollapsed: function() {
      return this._isCollapsed;
    },

    toggleDone: function() {
      var uri = this._getInlineURI();
      var data = {
        op: 'done',
        id: this._id
      };

      var ondone = JX.bind(this, this._ondone);

      new JX.Workflow(uri, data)
        .setHandler(ondone)
        .start();
    },

    _ondone: function(response) {
      var checkbox = JX.DOM.find(
        this._row,
        'input',
        'differential-inline-done');

      checkbox.checked = (response.isChecked ? 'checked' : null);

      var comment = JX.DOM.findAbove(
        checkbox,
        'div',
        'differential-inline-comment');

      JX.DOM.alterClass(comment, 'inline-is-done', response.isChecked);

      // NOTE: This is marking the inline as having an unsubmitted checkmark,
      // as opposed to a submitted checkmark. This is different from the
      // top-level "draft" state of unsubmitted comments.
      JX.DOM.alterClass(comment, 'inline-state-is-draft', response.draftState);

      this._isFixed = response.isChecked;
      this._isDraftDone = !!response.draftState;

      this._didUpdate();
    },

    create: function(text) {
      var uri = this._getInlineURI();
      var handler = JX.bind(this, this._oncreateresponse);
      var data = this._newRequestData('new', text);

      this.setLoading(true);

      new JX.Request(uri, handler)
        .setData(data)
        .send();
    },

    reply: function(text) {
      var changeset = this.getChangeset();
      return changeset.newInlineReply(this, text);
    },

    edit: function(text) {
      var uri = this._getInlineURI();
      var handler = JX.bind(this, this._oneditresponse);
      var data = this._newRequestData('edit', text || null);

      this.setLoading(true);

      new JX.Request(uri, handler)
        .setData(data)
        .send();
    },

    delete: function(is_ref) {
      var uri = this._getInlineURI();
      var handler = JX.bind(this, this._ondeleteresponse);

      // NOTE: This may be a direct delete (the user clicked on the inline
      // itself) or a "refdelete" (the user clicked somewhere else, like the
      // preview, but the inline is present on the page).

      // For a "refdelete", we prompt the user to confirm that they want to
      // delete the comment, because they can not undo deletions from the
      // preview. We could jump the user to the inline instead, but this would
      // be somewhat disruptive and make deleting several comments more
      // difficult.

      var op;
      if (is_ref) {
        op = 'refdelete';
      } else {
        op = 'delete';
      }

      var data = this._newRequestData(op);

      this.setLoading(true);

      new JX.Workflow(uri, data)
        .setHandler(handler)
        .start();
    },

    getDisplaySide: function() {
      return this._displaySide;
    },

    getLineNumber: function() {
      return this._number;
    },

    getLineLength: function() {
      return this._length;
    },

    isNewFile: function() {
      return this._isNewFile;
    },

    getID: function() {
      return this._id;
    },

    getPHID: function() {
      return this._phid;
    },

    getChangesetID: function() {
      return this._changesetID;
    },

    getReplyToCommentPHID: function() {
      return this._replyToCommentPHID;
    },

    setDeleted: function(deleted) {
      this._isDeleted = deleted;
      this._redraw();
      return this;
    },

    setInvisible: function(invisible) {
      this._isInvisible = invisible;
      this._redraw();
      return this;
    },

    setLoading: function(loading) {
      this._isLoading = loading;
      this._redraw();
      return this;
    },

    _newRequestData: function(operation, text) {
      return {
        op: operation,
        id: this._id,
        on_right: ((this.getDisplaySide() == 'right') ? 1 : 0),
        renderer: this.getChangeset().getRenderer(),
        number: this.getLineNumber(),
        length: this.getLineLength(),
        is_new: this.isNewFile(),
        changesetID: this.getChangesetID(),
        replyToCommentPHID: this.getReplyToCommentPHID() || '',
        text: text || ''
      };
    },

    _oneditresponse: function(response) {
      var rows = JX.$H(response).getNode();

      this._drawEditRows(rows);

      this.setLoading(false);
      this.setInvisible(true);
    },

    _oncreateresponse: function(response) {
      var rows = JX.$H(response).getNode();

      this._drawEditRows(rows);
    },

    _ondeleteresponse: function() {
      this._drawUndeleteRows();

      this.setLoading(false);
      this.setDeleted(true);

      this._didUpdate();
    },

    _drawUndeleteRows: function() {
      return this._drawUndoRows('undelete', this._row);
    },

    _drawUneditRows: function(text) {
      return this._drawUndoRows('unedit', null, text);
    },

    _drawUndoRows: function(mode, cursor, text) {
      var templates = this.getChangeset().getUndoTemplates();

      var template;
      if (this.getDisplaySide() == 'right') {
        template = templates.r;
      } else {
        template = templates.l;
      }
      template = JX.$H(template).getNode();

      this._undoRow = this._drawRows(template, cursor, mode, text);
    },

    _drawContentRows: function(rows) {
      return this._drawRows(rows, null, 'content');
    },

    _drawEditRows: function(rows) {
      this.setEditing(true);
      return this._drawRows(rows, null, 'edit');
    },

    _drawRows: function(rows, cursor, type, text) {
      var first_row = JX.DOM.scry(rows, 'tr')[0];
      var first_meta;
      var row = first_row;
      var anchor = cursor || this._row;
      cursor = cursor || this._row.nextSibling;

      var next_row;
      while (row) {
        // Grab this first, since it's going to change once we insert the row
        // into the document.
        next_row = row.nextSibling;

        // Bind edit and undo rows to this DiffInline object so that
        // interactions like hovering work properly.
        JX.Stratcom.getData(row).inline = this;

        anchor.parentNode.insertBefore(row, cursor);
        cursor = row;

        var row_meta = {
          node: row,
          type: type,
          text: text || null,
          listeners: []
        };

        if (!first_meta) {
          first_meta = row_meta;
        }

        if (type == 'edit') {
          row_meta.listeners.push(
            JX.DOM.listen(
              row,
              ['submit', 'didSyntheticSubmit'],
              'inline-edit-form',
              JX.bind(this, this._onsubmit, row_meta)));

          row_meta.listeners.push(
            JX.DOM.listen(
              row,
              'click',
              'inline-edit-cancel',
              JX.bind(this, this._oncancel, row_meta)));
        } else if (type == 'content') {
          // No special listeners for these rows.
        } else {
          row_meta.listeners.push(
            JX.DOM.listen(
              row,
              'click',
              'differential-inline-comment-undo',
              JX.bind(this, this._onundo, row_meta)));
        }

        // If the row has a textarea, focus it. This allows the user to start
        // typing a comment immediately after a "new", "edit", or "reply"
        // action.
        var textareas = JX.DOM.scry(
          row,
          'textarea',
          'differential-inline-comment-edit-textarea');
        if (textareas.length) {
          var area = textareas[0];
          area.focus();

          var length = area.value.length;
          JX.TextAreaUtils.setSelectionRange(area, length, length);
        }

        row = next_row;
      }

      JX.Stratcom.invoke('resize');

      return first_meta;
    },

    _onsubmit: function(row, e) {
      e.kill();

      var handler = JX.bind(this, this._onsubmitresponse, row);

      this.setLoading(true);

      JX.Workflow.newFromForm(e.getTarget())
        .setHandler(handler)
        .start();
    },

    _onundo: function(row, e) {
      e.kill();

      this._removeRow(row);

      if (row.type == 'undelete') {
        var uri = this._getInlineURI();
        var data = this._newRequestData('undelete');
        var handler = JX.bind(this, this._onundelete);

        this.setDeleted(false);
        this.setLoading(true);

        new JX.Request(uri, handler)
          .setData(data)
          .send();
      }

      if (row.type == 'unedit') {
        if (this.getID()) {
          this.edit(row.text);
        } else {
          this.create(row.text);
        }
      }
    },

    _onundelete: function() {
      this.setLoading(false);
      this._didUpdate();
    },

    _oncancel: function(row, e) {
      e.kill();

      var text = this._readText(row.node);
      if (text && text.length && (text != this._originalText)) {
        this._drawUneditRows(text);
      }

      this._removeRow(row);
      this.setEditing(false);

      this.setInvisible(false);

      this._didUpdate(true);
    },

    _readText: function(row) {
      var textarea;
      try {
        textarea = JX.DOM.find(
          row,
          'textarea',
          'differential-inline-comment-edit-textarea');
      } catch (ex) {
        return null;
      }

      return textarea.value;
    },

    _onsubmitresponse: function(row, response) {
      this._removeRow(row);

      this.setLoading(false);
      this.setInvisible(false);
      this.setEditing(false);

      this._onupdate(response);
    },

    _onupdate: function(response) {
      var new_row;
      if (response.markup) {
        new_row = this._drawContentRows(JX.$H(response.markup).getNode()).node;
      }

      // TODO: Save the old row so the action it's undo-able if it was a
      // delete.
      var remove_old = true;
      if (remove_old) {
        JX.DOM.remove(this._row);
      }

      // If you delete the content on a comment and save it, it acts like a
      // delete: the server does not return a new row.
      if (new_row) {
        this.bindToRow(new_row);
      } else {
        this.setDeleted(true);
        this._row = null;
      }

      this._didUpdate();
    },

    _didUpdate: function(local_only) {
      // After making changes to inline comments, refresh the transaction
      // preview at the bottom of the page.
      if (!local_only) {
        this.getChangeset().getChangesetList().redrawPreview();
      }

      this.getChangeset().getChangesetList().redrawCursor();
      this.getChangeset().getChangesetList().resetHover();

      // Emit a resize event so that UI elements like the keyboard focus
      // reticle can redraw properly.
      JX.Stratcom.invoke('resize');
    },

    _redraw: function() {
      var is_invisible =
        (this._isInvisible || this._isDeleted || this._isHidden);
      var is_loading = this._isLoading;
      var is_collapsed = (this._isCollapsed && !this._isHidden);

      var row = this._row;
      JX.DOM.alterClass(row, 'differential-inline-hidden', is_invisible);
      JX.DOM.alterClass(row, 'differential-inline-loading', is_loading);
      JX.DOM.alterClass(row, 'inline-hidden', is_collapsed);
    },

    _removeRow: function(row) {
      JX.DOM.remove(row.node);
      for (var ii = 0; ii < row.listeners.length; ii++) {
        row.listeners[ii].remove();
      }
    },

    _getInlineURI: function() {
      var changeset = this.getChangeset();
      var list = changeset.getChangesetList();
      return list.getInlineURI();
    }
  }

});
