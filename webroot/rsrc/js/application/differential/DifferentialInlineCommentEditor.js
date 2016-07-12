/**
 * @provides differential-inline-comment-editor
 * @requires javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-install
 *           javelin-request
 *           javelin-workflow
 */

JX.install('DifferentialInlineCommentEditor', {

  construct : function(uri) {
    this._uri = uri;
  },

  events : ['done'],

  members : {
    _uri : null,
    _undoText : null,
    _completed: false,
    _skipOverInlineCommentRows : function(node) {
      // TODO: Move this semantic information out of class names.
      while (node && node.className.indexOf('inline') !== -1) {
        node = node.nextSibling;
      }
      return node;
    },
    _buildRequestData : function() {
      return {
        op : this.getOperation(),
        on_right : this.getOnRight(),
        id : this.getID(),
        number : this.getLineNumber(),
        is_new : (this.getIsNew() ? 1 : 0),
        length : this.getLength(),
        changesetID : this.getChangesetID(),
        text : this.getText() || '',
        renderer: this.getRenderer(),
        replyToCommentPHID: this.getReplyToCommentPHID() || '',
      };
    },
    _draw : function(content, exact_row) {
      var row = this.getRow();
      var table = this.getTable();
      var target = exact_row ? row : this._skipOverInlineCommentRows(row);

      function copyRows(dst, src, before) {
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
        return rows;
      }

      return copyRows(table, content, target);
    },
    _removeUndoLink : function() {
      var rows = JX.DifferentialInlineCommentEditor._undoRows;
      if (rows) {
        for (var ii = 0; ii < rows.length; ii++) {
          JX.DOM.remove(rows[ii]);
        }
      }
      JX.DifferentialInlineCommentEditor._undoRows = [];
    },
    _undo : function() {
      this._removeUndoLink();

      if (this._undoText) {
        this.setText(this._undoText);
      } else {
        this.setOperation('undelete');
      }

      this.start();
    },
    _registerUndoListener : function() {
      if (!JX.DifferentialInlineCommentEditor._activeEditor) {
        JX.Stratcom.listen(
          'click',
          'differential-inline-comment-undo',
          function(e) {
            JX.DifferentialInlineCommentEditor._activeEditor._undo();
            e.kill();
          });
      }
      JX.DifferentialInlineCommentEditor._activeEditor = this;
    },
    _setRowState : function(state) {
      var is_hidden   = (state == 'hidden');
      var is_loading  = (state == 'loading');
      var row = this.getRow();
      JX.DOM.alterClass(row, 'differential-inline-hidden', is_hidden);
      JX.DOM.alterClass(row, 'differential-inline-loading', is_loading);
    },
    _didContinueWorkflow : function(response) {
      var drawn = this._draw(JX.$H(response).getNode());

      var op = this.getOperation();
      if (op == 'edit') {
        this._setRowState('hidden');
      }

      JX.DOM.find(
        drawn[0],
        'textarea',
        'differential-inline-comment-edit-textarea').focus();

      var oncancel = JX.bind(this, function(e) {
        e.kill();

        this._didCancelWorkflow();

        if (op == 'edit') {
          this._setRowState('visible');
        }

        JX.DOM.remove(drawn[0]);
      });
      JX.DOM.listen(drawn[0], 'click', 'inline-edit-cancel', oncancel);

      var onsubmit = JX.bind(this, function(e) {
        e.kill();

        JX.Workflow.newFromForm(e.getTarget())
          .setHandler(JX.bind(this, function(response) {
            JX.DOM.remove(drawn[0]);
            if (op == 'edit') {
              this._setRowState('visible');
            }
            this._didCompleteWorkflow(response);
          }))
          .start();

        JX.DOM.alterClass(drawn[0], 'differential-inline-loading', true);
      });
      JX.DOM.listen(
        drawn[0],
        ['submit', 'didSyntheticSubmit'],
        'inline-edit-form',
        onsubmit);
    },


    _didCompleteWorkflow : function(response) {
      var op = this.getOperation();

      // We don't get any markup back if the user deletes a comment, or saves
      // an empty comment (which effects a delete).
      if (response.markup) {
        this._draw(JX.$H(response.markup).getNode());
      }

      if (op == 'delete' || op == 'refdelete') {
        this._undoText = null;
        this._drawUndo();
      } else {
        this._removeUndoLink();
      }

      // These operations remove the old row (edit adds a new row first).
      var remove_old = (op == 'edit' || op == 'delete' || op == 'refdelete');
      if (remove_old) {
        this._setRowState('hidden');
      }

      if (op == 'undelete') {
        this._setRowState('visible');
      }

      this._completed = true;

      JX.Stratcom.invoke('differential-inline-comment-update');
      this.invoke('done');
    },


    _didCancelWorkflow : function() {
      this.invoke('done');

      switch (this.getOperation()) {
        case 'delete':
        case 'refdelete':
          if (!this._completed) {
            this._setRowState('visible');
          }
          return;
        case 'undelete':
          return;
      }

      var textarea;
      try {
        textarea = JX.DOM.find(
          document.body, // TODO: use getDialogRootNode() when available
          'textarea',
          'differential-inline-comment-edit-textarea');
      } catch (ex) {
        // The close handler is called whenever the dialog closes, even if the
        // user closed it by completing the workflow with "Save". The
        // JX.Workflow API should probably be refined to allow programmatic
        // distinction of close caused by 'cancel' vs 'submit'. Testing for
        // presence of the textarea serves as a proxy for detecting a 'cancel'.
        return;
      }

      var text = textarea.value;

      // If the user hasn't edited the text (i.e., no change from original for
      // 'edit' or no text at all), don't offer them an undo.
      if (text == this.getOriginalText() || text === '') {
        return;
      }

      // Save the text so we can 'undo' back to it.
      this._undoText = text;

      this._drawUndo();
    },

    _drawUndo: function() {
      var templates = this.getTemplates();
      var template = this.getOnRight() ? templates.r : templates.l;
      template = JX.$H(template).getNode();

      // NOTE: Operation order matters here; we can't remove anything until
      // after we draw the new rows because _draw uses the old rows to figure
      // out where to place the comment.

      // We use 'exact_row' to put the "undo" text directly above the affected
      // comment.
      var exact_row = true;
      var rows = this._draw(template, exact_row);

      this._removeUndoLink();

      JX.DifferentialInlineCommentEditor._undoRows = rows;
    },

    _onBusyWorkflow: function() {
      // If the user clicks the "Jump to Inline" button, scroll to the row
      // being edited.
      JX.DOM.scrollTo(this.getRow());
    },

    start : function() {
      var op = this.getOperation();

      // The user is already editing a comment, we're going to give them an
      // error message.
      if (op == 'busy') {
        var onbusy = JX.bind(this, this._onBusyWorkflow);

        new JX.Workflow(this._uri, {op: op})
          .setHandler(onbusy)
          .start();

        return this;
      }

      this._registerUndoListener();
      var data = this._buildRequestData();

      if (op == 'delete' || op == 'refdelete' || op == 'undelete') {
        this._setRowState('loading');

        var oncomplete = JX.bind(this, this._didCompleteWorkflow);
        var oncancel = JX.bind(this, this._didCancelWorkflow);

        new JX.Workflow(this._uri, data)
          .setHandler(oncomplete)
          .setCloseHandler(oncancel)
          .start();
      } else {
        var handler = JX.bind(this, this._didContinueWorkflow);

        if (op == 'edit') {
          this._setRowState('loading');
        }

        new JX.Request(this._uri, handler)
          .setData(data)
          .send();
      }

      return this;
    },

    deleteByID: function(id) {
      var data = {
        op: 'refdelete',
        id: id
      };

      new JX.Workflow(this._uri, data)
        .setHandler(JX.bind(this, function() {
          this._didUpdate();
        }))
        .start();
    },

    toggleCheckbox: function(id, checkbox) {
      var data = {
        op: 'done',
        id: id
      };

      new JX.Workflow(this._uri, data)
        .setHandler(JX.bind(this, function(r) {
          checkbox.checked = !checkbox.checked;

          var comment = JX.DOM.findAbove(
            checkbox,
            'div',
            'differential-inline-comment');
          JX.DOM.alterClass(comment, 'inline-is-done', !!checkbox.checked);
          JX.DOM.alterClass(comment, 'inline-state-is-draft', r.draftState);

          this._didUpdate();
        }))
        .start();
    },

    _didUpdate: function() {
      JX.Stratcom.invoke('differential-inline-comment-update');
    }

  },

  statics : {
    /**
     * Global refernece to the 'undo' rows currently rendered in the document.
     */
    _undoRows : null,

    /**
     * Global listener for the 'undo' click associated with the currently
     * displayed 'undo' link. When an editor is start()ed, it becomes the active
     * editor.
     */
    _activeEditor : null
  },

  properties : {
    operation : null,
    row : null,
    table : null,
    onRight : null,
    ID : null,
    lineNumber : null,
    changesetID : null,
    length : null,
    isNew : null,
    text : null,
    templates : null,
    originalText : null,
    renderer: null,
    replyToCommentPHID: null
  }

});
