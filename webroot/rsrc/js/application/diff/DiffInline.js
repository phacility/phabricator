/**
 * @provides phabricator-diff-inline
 * @requires javelin-dom
 * @javelin
 */

JX.install('DiffInline', {

  construct : function(row) {
    this._row = row;

    var row_data = JX.Stratcom.getData(row);
    this._hidden = row_data.hidden || false;

    // TODO: Get smarter about this once we do more editing, this is pretty
    // hacky.
    var comment = JX.DOM.find(row, 'div', 'differential-inline-comment');
    var data = JX.Stratcom.getData(comment);

    this._id = data.id;

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
  },

  properties: {
    changeset: null
  },

  members: {
    _id: null,
    _row: null,
    _hidden: false,
    _number: null,
    _length: null,
    _displaySide: null,
    _isNewFile: null,

    setHidden: function(hidden) {
      this._hidden = hidden;

      JX.DOM.alterClass(this._row, 'inline-hidden', this._hidden);

      var op;
      if (hidden) {
        op = 'hide';
      } else {
        op = 'show';
      }

      var inline_uri = this._getChangesetList().getInlineURI();
      var comment_id = this._id;

      new JX.Workflow(inline_uri, {op: op, ids: comment_id})
        .setHandler(JX.bag)
        .start();
    },

    edit: function() {
      var handler = JX.bind(this, this._oneditresponse);
      var uri = this.getChangeset().getChangesetList().getInlineURI();
      var data = this._newRequestData();

      // TODO: Set state to "loading".

      new JX.Request(uri, handler)
        .setData(data)
        .send();
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

    _newRequestData: function() {
      return {
        op: 'edit',
        id: this._id,
        on_right: ((this.getDisplaySide() == 'right') ? 1 : 0),
        renderer: this.getChangeset().getRenderer(),
        number: this.getLineNumber(),
        length: this.getLineLength(),
        is_new: this.isNewFile(),
        replyToCommentPHID: ''
      };
    },

    _oneditresponse: function(response) {
      var rows = JX.$H(response).getNode();

      this._drawEditRows(rows);

      // TODO: Set the row state to "hidden".
    },

    _drawEditRows: function(rows) {
      var first_row = JX.DOM.scry(rows, 'tr')[0];
      var row = first_row;
      var cursor = this._row;

      while (row) {
        cursor.parentNode.insertBefore(row, cursor.nextSibling);
        cursor = row;

        var row_meta = {
          node: row,
          type: 'edit',
          listeners: []
        };

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

        row = row.nextSibling;
      }

      return first_row;
    },

    _onsubmit: function(row, e) {
      e.kill();

      var handler = JX.bind(this, this._onsubmitresponse, row);

      JX.Workflow.newFromForm(e.getTarget())
        .setHandler(handler)
        .start();

      // TODO: Set state to "loading".
    },

    _oncancel: function(row, e) {
      e.kill();

      // TODO: Capture edited text and offer "undo".

      JX.DOM.remove(row.node);
      this._removeListeners(row.listeners);

      // TODO: Restore state to "normal".
    },

    _onsubmitresponse: function(row, response) {

     JX.DOM.remove(row.node);
     this._removeListeners(row.listeners);

     // TODO: Restore state to "normal".

      this._onupdate(response);
    },

    _onupdate: function(response) {
      var new_row;
      if (response.markup) {
        new_row = this._drawEditRows(JX.$H(response.markup).getNode());
      }

      // TODO: Save the old row so the action it's undo-able if it was a
      // delete.
      var remove_old = true;
      if (remove_old) {
        JX.DOM.remove(this._row);
      }

      this._row = new_row;

      this._didUpdate();
    },

    _didUpdate: function() {
      // After making changes to inline comments, refresh the transaction
      // preview at the bottom of the page.

      // TODO: This isn't the cleanest way to find the preview form, but
      // rendering no longer has direct access to it.
      var forms = JX.DOM.scry(document.body, 'form', 'transaction-append');
      if (forms.length) {
        JX.DOM.invoke(forms[0], 'shouldRefresh');
      }
    },

    _removeListeners: function(listeners) {
      for (var ii = 0; ii < listeners.length; ii++) {
        listeners[ii].remove();
      }
    },

    _getChangesetList: function() {
      var changeset = this.getChangeset();
      return changeset.getChangesetList();
    }
  }

});
