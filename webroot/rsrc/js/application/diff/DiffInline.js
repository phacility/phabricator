/**
 * @provides phabricator-diff-inline
 * @requires javelin-dom
 * @javelin
 */

JX.install('DiffInline', {

  construct : function() {
  },

  properties: {
    changeset: null
  },

  members: {
    _id: null,
    _phid: null,
    _changesetID: null,
    _row: null,
    _hidden: false,
    _number: null,
    _length: null,
    _displaySide: null,
    _isNewFile: null,
    _undoRow: null,
    _replyToCommentPHID: null,

    _isDeleted: false,
    _isInvisible: false,
    _isLoading: false,

    bindToRow: function(row) {
      this._row = row;

      var row_data = JX.Stratcom.getData(row);
      row_data.inline = this;

      this._hidden = row_data.hidden || false;

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

      this._number = data.number;
      this._length = data.length;
      this._isNewFile =
        (this.getDisplaySide() == 'right') ||
        (data.left != data.right);

      this.setInvisible(false);

      return this;
    },

    bindToRange: function(data) {
      this._displaySide = data.displaySide;
      this._number = data.number;
      this._length = data.length;
      this._isNewFile = data.isNewFile;
      this._changesetID = data.changesetID;

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

      this._replyToCommentPHID = inline._phid;

      // TODO: This should insert correctly into the thread, not just at the
      // bottom.
      var parent_row = inline._row;
      var target_row = parent_row.nextSibling;
      while (target_row && JX.Stratcom.hasSigil(target_row, 'inline-row')) {
        target_row = target_row.nextSibling;
      }

      var row = this._newRow();
      parent_row.parentNode.insertBefore(row, target_row);

      this.setInvisible(true);

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
      this._hidden = false;

      return row;
    },

    setHidden: function(hidden) {
      this._hidden = hidden;

      JX.DOM.alterClass(this._row, 'inline-hidden', this._hidden);

      var op;
      if (hidden) {
        op = 'hide';
      } else {
        op = 'show';
      }

      var inline_uri = this._getInlineURI();
      var comment_id = this._id;

      new JX.Workflow(inline_uri, {op: op, ids: comment_id})
        .setHandler(JX.bag)
        .start();
    },

    isHidden: function() {
      return this._hidden;
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

      this._didUpdate();
    },

    create: function() {
      var uri = this._getInlineURI();
      var handler = JX.bind(this, this._oncreateresponse);
      var data = this._newRequestData('new');

      this.setLoading(true);

      new JX.Request(uri, handler)
        .setData(data)
        .send();
    },

    reply: function() {
      var changeset = this.getChangeset();
      return changeset.newInlineReply(this);
    },

    edit: function() {
      var uri = this._getInlineURI();
      var handler = JX.bind(this, this._oneditresponse);
      var data = this._newRequestData('edit');

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

    _newRequestData: function(operation) {
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
      this._drawUndoRows();

      this.setLoading(false);
      this.setDeleted(true);

      this._didUpdate();
    },

    _drawUndoRows: function() {
      var templates = this.getChangeset().getUndoTemplates();

      var template;
      if (this.getDisplaySide() == 'right') {
        template = templates.r;
      } else {
        template = templates.l;
      }
      template = JX.$H(template).getNode();

      this._undoRow = this._drawRows(template, this._row, 'undo');
    },

    _drawEditRows: function(rows) {
      return this._drawRows(rows, null, 'edit');
    },

    _drawRows: function(rows, cursor, type) {
      var first_row = JX.DOM.scry(rows, 'tr')[0];
      var first_meta;
      var row = first_row;
      cursor = cursor || this._row.nextSibling;

      var next_row;
      while (row) {
        // Grab this first, since it's going to change once we insert the row
        // into the document.
        next_row = row.nextSibling;

        cursor.parentNode.insertBefore(row, cursor);
        cursor = row;

        var row_meta = {
          node: row,
          type: type,
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
          textareas[0].focus();
        }

        row = next_row;
      }

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

      var uri = this._getInlineURI();
      var data = this._newRequestData('undelete');
      var handler = JX.bind(this, this._onundelete);

      this.setDeleted(false);
      this.setLoading(true);

      new JX.Request(uri, handler)
        .setData(data)
        .send();
    },

    _onundelete: function() {
      this.setLoading(false);
      this._didUpdate();
    },

    _oncancel: function(row, e) {
      e.kill();

      // TODO: Capture edited text and offer "undo".

      this._removeRow(row);

      this.setInvisible(false);
    },

    _onsubmitresponse: function(row, response) {
      this._removeRow(row);

      this.setLoading(false);
      this.setInvisible(false);

      this._onupdate(response);
    },

    _onupdate: function(response) {
      var new_row;
      if (response.markup) {
        new_row = this._drawEditRows(JX.$H(response.markup).getNode()).node;
      }

      // TODO: Save the old row so the action it's undo-able if it was a
      // delete.
      var remove_old = true;
      if (remove_old) {
        JX.DOM.remove(this._row);
      }

      this.bindToRow(new_row);

      this._didUpdate();
    },

    _didUpdate: function() {
      // After making changes to inline comments, refresh the transaction
      // preview at the bottom of the page.
      this.getChangeset().getChangesetList().redrawPreview();
    },

    _redraw: function() {
      var is_invisible = (this._isInvisible || this._isDeleted);
      var is_loading = (this._isLoading);

      var row = this._row;
      JX.DOM.alterClass(row, 'differential-inline-hidden', is_invisible);
      JX.DOM.alterClass(row, 'differential-inline-loading', is_loading);
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
