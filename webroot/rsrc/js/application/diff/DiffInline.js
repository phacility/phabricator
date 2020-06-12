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
    _replyToCommentPHID: null,
    _originalState: null,
    _snippet: null,
    _menuItems: null,
    _documentEngineKey: null,

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

    _editRow: null,
    _undoRow: null,
    _undoType: null,
    _undoState: null,

    _draftRequest: null,
    _skipFocus: false,
    _menu: null,

    _startOffset: null,
    _endOffset: null,
    _isSelected: false,
    _canSuggestEdit: false,

    bindToRow: function(row) {
      this._row = row;

      var row_data = JX.Stratcom.getData(row);
      row_data.inline = this;
      this._isCollapsed = row_data.hidden || false;

      // TODO: Get smarter about this once we do more editing, this is pretty
      // hacky.
      var comment = JX.DOM.find(row, 'div', 'differential-inline-comment');
      var data = JX.Stratcom.getData(comment);

      this._readInlineState(data);
      this._phid = data.phid;

      if (data.on_right) {
        this._displaySide = 'right';
      } else {
        this._displaySide = 'left';
      }

      this._number = parseInt(data.number, 10);
      this._length = parseInt(data.length, 10);

      this._isNewFile = data.isNewFile;

      this._replyToCommentPHID = data.replyToCommentPHID;

      this._isDraft = data.isDraft;
      this._isFixed = data.isFixed;
      this._isGhost = data.isGhost;
      this._isSynthetic = data.isSynthetic;
      this._isDraftDone = data.isDraftDone;

      this._changesetID = data.changesetID;
      this._isNew = false;
      this._snippet = data.snippet;
      this._menuItems = data.menuItems;
      this._documentEngineKey = data.documentEngineKey;

      this._startOffset = data.startOffset;
      this._endOffset = data.endOffset;

      this._isEditing = data.isEditing;

      if (this._isEditing) {
        // NOTE: The "original" shipped down in the DOM may reflect a draft
        // which we're currently editing. This flow is a little clumsy, but
        // reasonable until some future change moves away from "send down
        // the inline, then immediately click edit".
        this.edit(null, true);
      } else {
        this.setInvisible(false);
      }

      this._startDrafts();

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

    isUndo: function() {
      return !!this._undoRow;
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

    getStartOffset: function() {
      return this._startOffset;
    },

    getEndOffset: function() {
      return this._endOffset;
    },

    setIsSelected: function(is_selected) {
      this._isSelected = is_selected;

      if (this._row) {
        JX.DOM.alterClass(
          this._row,
          'inline-comment-selected',
          this._isSelected);
      }

      return this;
    },

    bindToRange: function(data) {
      this._displaySide = data.displaySide;
      this._number = parseInt(data.number, 10);
      this._length = parseInt(data.length, 10);
      this._isNewFile = data.isNewFile;
      this._changesetID = data.changesetID;
      this._isNew = true;

      if (data.hasOwnProperty('startOffset')) {
        this._startOffset = data.startOffset;
      } else {
        this._startOffset = null;
      }

      if (data.hasOwnProperty('endOffset')) {
        this._endOffset = data.endOffset;
      } else {
        this._endOffset = null;
      }

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
      this._startDrafts();

      return this;
    },

    bindToReply: function(inline) {
      this._displaySide = inline._displaySide;
      this._number = inline._number;
      this._length = inline._length;
      this._isNewFile = inline._isNewFile;
      this._changesetID = inline._changesetID;
      this._isNew = true;
      this._documentEngineKey = inline._documentEngineKey;

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
      this._startDrafts();

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
      return this._hasMenuAction('reply');
    },

    canEdit: function() {
      return this._hasMenuAction('edit');
    },

    canDone: function() {
      if (!JX.DOM.scry(this._row, 'input', 'differential-inline-done').length) {
        return false;
      }

      return true;
    },

    canCollapse: function() {
      return this._hasMenuAction('collapse');
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

      this._originalState = null;

      return row;
    },

    setCollapsed: function(collapsed) {
      this._closeMenu();

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

    create: function(content_state) {
      var changeset = this.getChangeset();
      if (!this._documentEngineKey) {
        this._documentEngineKey = changeset.getResponseDocumentEngineKey();
      }

      var uri = this._getInlineURI();
      var handler = JX.bind(this, this._oncreateresponse);
      var data = this._newRequestData('new', content_state);

      this.setLoading(true);

      new JX.Request(uri, handler)
        .setData(data)
        .send();
    },

    _getContentState: function() {
      var state;

      if (this._editRow) {
        state = this._readFormState(this._editRow);
      } else {
        state = this._originalState;
      }

      return state;
    },

    reply: function(with_quote) {
      this._closeMenu();

      var content_state = this._newContentState();
      if (with_quote) {
        var text = this._getContentState().text;
        text = '> ' + text.replace(/\n/g, '\n> ') + '\n\n';
        content_state.text = text;
      }

      var changeset = this.getChangeset();
      return changeset.newInlineReply(this, content_state);
    },

    edit: function(content_state, skip_focus) {
      this._closeMenu();

      this._skipFocus = !!skip_focus;

      // If you edit an inline ("A"), modify the text ("AB"), cancel, and then
      // edit it again: discard the undo state ("AB"). Otherwise we end up
      // with an open editor and an active "Undo" link, which is weird.

      if (this._undoRow) {
        JX.DOM.remove(this._undoRow);
        this._undoRow = null;

        this._undoType = null;
        this._undoText = null;
      }

      var uri = this._getInlineURI();
      var handler = JX.bind(this, this._oneditresponse);

      var data = this._newRequestData('edit', content_state);

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

    _newRequestData: function(operation, content_state) {
      var data = {
        op: operation,
        is_new: this.isNewFile(),
        on_right: ((this.getDisplaySide() == 'right') ? 1 : 0),
        renderer: this.getChangeset().getRendererKey()
      };

      if (operation === 'new') {
        var create_data = {
          changesetID: this.getChangesetID(),
          documentEngineKey: this._documentEngineKey,
          replyToCommentPHID: this.getReplyToCommentPHID(),
          startOffset: this._startOffset,
          endOffset: this._endOffset,
          number: this.getLineNumber(),
          length: this.getLineLength()
        };

        JX.copy(data, create_data);
      } else {
        var edit_data = {
          id: this._id
        };

        JX.copy(data, edit_data);
      }

      if (content_state) {
        data.hasContentState = 1;
        JX.copy(data, content_state);
      }

      return data;
    },

    _oneditresponse: function(response) {
      var rows = JX.$H(response.view).getNode();

      this._readInlineState(response.inline);
      this._drawEditRows(rows);

      this.setLoading(false);
      this.setInvisible(true);
    },

    _oncreateresponse: function(response) {
      var rows = JX.$H(response.view).getNode();

      this._readInlineState(response.inline);
      this._drawEditRows(rows);
    },

    _readInlineState: function(state) {
      this._id = state.id;
      this._originalState = state.contentState;
      this._canSuggestEdit = state.canSuggestEdit;
    },

    _ondeleteresponse: function() {
      // If there's an existing "unedit" undo element, remove it.
      if (this._undoRow) {
        JX.DOM.remove(this._undoRow);
        this._undoRow = null;
      }

      // If there's an existing editor, remove it. This happens when you
      // delete a comment from the comment preview area. In this case, we
      // read and preserve the text so "Undo" restores it.
      var state = null;
      if (this._editRow) {
        state = this._readFormState(this._editRow);
        JX.DOM.remove(this._editRow);
        this._editRow = null;
      }

      this._drawUndeleteRows(state);

      this.setLoading(false);
      this.setDeleted(true);

      this._didUpdate();
    },

    _drawUndeleteRows: function(content_state) {
      this._undoType = 'undelete';
      this._undoState = content_state || null;

      return this._drawUndoRows('undelete', this._row);
    },

    _drawUneditRows: function(content_state) {
      this._undoType = 'unedit';
      this._undoState = content_state;

      return this._drawUndoRows('unedit', null);
    },

    _drawUndoRows: function(mode, cursor) {
      var templates = this.getChangeset().getUndoTemplates();

      var template;
      if (this.getDisplaySide() == 'right') {
        template = templates.r;
      } else {
        template = templates.l;
      }
      template = JX.$H(template).getNode();

      this._undoRow = this._drawRows(template, cursor, mode);
    },

    _drawContentRows: function(rows) {
      return this._drawRows(rows, null, 'content');
    },

    _drawEditRows: function(rows) {
      this.setEditing(true);
      this._editRow = this._drawRows(rows, null, 'edit');

      this._drawSuggestionState(this._editRow);
      this.setHasSuggestion(this._originalState.hasSuggestion);
    },

    _drawRows: function(rows, cursor, type) {
      var first_row = JX.DOM.scry(rows, 'tr')[0];
      var row = first_row;
      var anchor = cursor || this._row;
      cursor = cursor || this._row.nextSibling;


      var result_row;
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

        if (!result_row) {
          result_row = row;
        }

        if (!this._skipFocus) {
          // If the row has a textarea, focus it. This allows the user to start
          // typing a comment immediately after a "new", "edit", or "reply"
          // action.

          // (When simulating an "edit" on page load, we don't do this.)

          var textareas = JX.DOM.scry(
            row,
            'textarea',
            'inline-content-text');
          if (textareas.length) {
            var area = textareas[0];
            area.focus();

            var length = area.value.length;
            JX.TextAreaUtils.setSelectionRange(area, length, length);
          }
        }

        row = next_row;
      }

      JX.Stratcom.invoke('resize');

      return result_row;
    },

    _drawSuggestionState: function(row) {
      if (this._canSuggestEdit) {
        var button = this._getSuggestionButton();
        var node = button.getNode();

        // As a side effect of form submission, the button may become
        // visually disabled. Re-enable it. This is a bit hacky.
        JX.DOM.alterClass(node, 'disabled', false);
        node.disabled = false;

        var container = JX.DOM.find(row, 'div', 'inline-edit-buttons');
        container.appendChild(node);
      }
    },

    _getSuggestionButton: function() {
      if (!this._suggestionButton) {
        var button = new JX.PHUIXButtonView()
          .setIcon('fa-pencil-square-o')
          .setColor('grey');

        var node = button.getNode();
        JX.DOM.alterClass(node, 'inline-button-left', true);

        var onclick = JX.bind(this, this._onSuggestEdit);
        JX.DOM.listen(node, 'click', null, onclick);

        this._suggestionButton = button;
      }

      return this._suggestionButton;
    },

    _onSuggestEdit: function(e) {
      e.kill();

      this.setHasSuggestion(!this.getHasSuggestion());

      // The first time the user actually clicks the button and enables
      // suggestions for a given editor state, fill the input with the
      // underlying text if there isn't any text yet.
      if (this.getHasSuggestion()) {
        if (this._editRow) {
          var node = this._getSuggestionNode(this._editRow);
          if (node) {
            if (!node.value.length) {
              var data = JX.Stratcom.getData(node);
              if (!data.hasSetDefault) {
                data.hasSetDefault = true;
                node.value = data.defaultText;
                node.rows = Math.max(3, node.value.split('\n').length);
              }
            }
          }
        }
      }

      // Save the "hasSuggestion" part of the content state.
      this.triggerDraft();
    },

    setHasSuggestion: function(has_suggestion) {
      this._hasSuggestion = has_suggestion;

      var button = this._getSuggestionButton();
      var pht = this.getChangeset().getChangesetList().getTranslations();
      if (has_suggestion) {
        button
          .setIcon('fa-times')
          .setText(pht('Discard Edit'));
      } else {
        button
          .setIcon('fa-plus')
          .setText(pht('Suggest Edit'));
      }

      if (this._editRow) {
        JX.DOM.alterClass(this._editRow, 'has-suggestion', has_suggestion);
      }
    },

    getHasSuggestion: function() {
      return this._hasSuggestion;
    },

    save: function() {
      var handler = JX.bind(this, this._onsubmitresponse);

      this.setLoading(true);

      var uri = this._getInlineURI();
      var data = this._newRequestData('save', this._getContentState());

      new JX.Request(uri, handler)
        .setData(data)
        .send();
    },

    undo: function() {
      JX.DOM.remove(this._undoRow);
      this._undoRow = null;

      if (this._undoType === 'undelete') {
        var uri = this._getInlineURI();
        var data = this._newRequestData('undelete');
        var handler = JX.bind(this, this._onundelete);

        this.setDeleted(false);
        this.setLoading(true);

        new JX.Request(uri, handler)
          .setData(data)
          .send();
      }

      if (this._undoState !== null) {
        this.edit(this._undoState);
      }
    },

    _onundelete: function() {
      this.setLoading(false);
      this._didUpdate();
    },

    cancel: function() {
      var state = this._readFormState(this._editRow);

      JX.DOM.remove(this._editRow);
      this._editRow = null;

      var is_empty = this._isVoidContentState(state);
      var is_same = this._isSameContentState(state, this._originalState);
      if (!is_empty && !is_same) {
        this._drawUneditRows(state);
      }

      // If this was an empty box and we typed some text and then hit cancel,
      // don't show the empty concrete inline.
      if (this._isVoidContentState(this._originalState)) {
        this.setInvisible(true);
      } else {
        this.setInvisible(false);
      }

      // If you "undo" to restore text ("AB") and then "Cancel", we put you
      // back in the original text state ("A"). We also send the original
      // text ("A") to the server as the current persistent state.

      var uri = this._getInlineURI();
      var data = this._newRequestData('cancel', this._originalState);
      var handler = JX.bind(this, this._onCancelResponse);

      this.setLoading(true);

      new JX.Request(uri, handler)
        .setData(data)
        .send();

      this._didUpdate(true);
    },

    _onCancelResponse: function(response) {
      this.setEditing(false);
      this.setLoading(false);

      // If the comment was empty when we started editing it (there's no
      // original text) and empty when we finished editing it (there's no
      // undo row), just delete the comment.
      if (this._isVoidContentState(this._originalState) && !this.isUndo()) {
        this.setDeleted(true);

        JX.DOM.remove(this._row);
        this._row = null;

        this._didUpdate();
      }
    },

    _readFormState: function(row) {
      var state = this._newContentState();

      var node;

      try {
        node = JX.DOM.find(row, 'textarea', 'inline-content-text');
        state.text = node.value;
      } catch (ex) {
        // Ignore.
      }

      node = this._getSuggestionNode(row);
      if (node) {
        state.suggestionText = node.value;
      }

      state.hasSuggestion = this.getHasSuggestion();

      return state;
    },

    _getSuggestionNode: function(row) {
      try {
        return JX.DOM.find(row, 'textarea', 'inline-content-suggestion');
      } catch (ex) {
        return null;
      }
    },

    _onsubmitresponse: function(response) {
      if (this._editRow) {
        JX.DOM.remove(this._editRow);
        this._editRow = null;
      }

      this.setLoading(false);
      this.setInvisible(false);
      this.setEditing(false);

      this._onupdate(response);
    },

    _onupdate: function(response) {
      var new_row;
      if (response.view) {
        new_row = this._drawContentRows(JX.$H(response.view).getNode());
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

    _getInlineURI: function() {
      var changeset = this.getChangeset();
      var list = changeset.getChangesetList();
      return list.getInlineURI();
    },

    _startDrafts: function() {
      if (this._draftRequest) {
        return;
      }

      var onresponse = JX.bind(this, this._onDraftResponse);
      var draft = JX.bind(this, this._getDraftState);

      var uri = this._getInlineURI();
      var request = new JX.PhabricatorShapedRequest(uri, onresponse, draft);

      // The main transaction code uses a 500ms delay on desktop and a
      // 10s delay on mobile. Perhaps this should be standardized.
      request.setRateLimit(2000);

      this._draftRequest = request;

      request.start();
    },

    _onDraftResponse: function() {
      // For now, do nothing.
    },

    _getDraftState: function() {
      if (this.isDeleted()) {
        return null;
      }

      if (!this.isEditing()) {
        return null;
      }

      var state = this._readFormState(this._editRow);
      if (this._isVoidContentState(state)) {
        return null;
      }

      var draft_data = {
        op: 'draft',
        id: this.getID(),
      };

      JX.copy(draft_data, state);

      return draft_data;
    },

    triggerDraft: function() {
      if (this._draftRequest) {
        this._draftRequest.trigger();
      }
    },

    activateMenu: function(button, e) {
      // If we already have a menu for this button, let the menu handle the
      // event.
      var data = JX.Stratcom.getData(button);
      if (data.menu) {
        return;
      }

      e.prevent();

      var menu = new JX.PHUIXDropdownMenu(button)
        .setWidth(240);

      var list = new JX.PHUIXActionListView();
      var items = this._newMenuItems(menu);
      for (var ii = 0; ii < items.length; ii++) {
        list.addItem(items[ii]);
      }

      menu.setContent(list.getNode());

      data.menu = menu;
      this._menu = menu;

      menu.listen('open', JX.bind(this, function() {
        var changeset_list = this.getChangeset().getChangesetList();
        changeset_list.selectInline(this, true);
      }));

      menu.open();
    },

    _newMenuItems: function(menu) {
      var items = [];

      for (var ii = 0; ii < this._menuItems.length; ii++) {
        var spec = this._menuItems[ii];

        var onmenu = JX.bind(this, this._onMenuItem, menu, spec.action, spec);

        var item = new JX.PHUIXActionView()
          .setIcon(spec.icon)
          .setName(spec.label)
          .setHandler(onmenu);

        if (spec.key) {
          item.setKeyCommand(spec.key);
        }

        items.push(item);
      }

      return items;
    },

    _onMenuItem: function(menu, action, spec, e) {
      e.prevent();
      menu.close();

      switch (action) {
        case 'reply':
          this.reply();
          break;
        case 'quote':
          this.reply(true);
          break;
        case 'collapse':
          this.setCollapsed(true);
          break;
        case 'delete':
          this.delete();
          break;
        case 'edit':
          this.edit();
          break;
        case 'raw':
          new JX.Workflow(spec.uri)
            .start();
          break;
      }

    },

    _hasMenuAction: function(action) {
      for (var ii = 0; ii < this._menuItems.length; ii++) {
        var spec = this._menuItems[ii];
        if (spec.action === action) {
          return true;
        }
      }
      return false;
    },

    _closeMenu: function() {
      if (this._menu) {
        this._menu.close();
      }
    },

    _newContentState: function() {
      return {
        text: '',
        suggestionText: '',
        hasSuggestion: false
      };
    },

    _isVoidContentState: function(state) {
      return (!state.text.length && !state.suggestionText.length);
    },

    _isSameContentState: function(u, v) {
      return (
        ((u === null) === (v === null)) &&
        (u.text === v.text) &&
        (u.suggestionText === v.suggestionText) &&
        (u.hasSuggestion === v.hasSuggestion));
    }
  }

});
