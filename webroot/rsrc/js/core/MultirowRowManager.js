/**
 * @requires javelin-install
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-util
 * @provides multirow-row-manager
 * @javelin
 */


/**
 * Give a MultirowRowManager a table DOM elem to manage.
 * You can add rows, and provide a given ID if you like.
 * You can update rows by ID.
 * Rows are automatically equipped with a removal button.
 * You can listen to the 'row-removed' event on the Manager to get
 * notifications of these row removals, with the DOM id of the removed
 * row as event data.
 */
JX.install('MultirowRowManager', {
  /**
   * @param DOM element <table> root Container for rows
   */
  construct : function(root, minRows) {
    this._root = root;
    this._rows = [];

    if (typeof minRows !== 'undefined') {
      this._minRows = minRows;
    } else {
      this._minRows = 1;
    }

    JX.DOM.listen(
      this._root,
      'click',
      JX.MultirowRowManager._removeSigil,
      JX.bind(this, this._onrowremoved));
  },

  members : {
    _count : 0,
    _nextID : 0,
    _root : null,
    _rows : null,

    _generateRowID : function() {
      return '' + this._nextID++;
    },

    _wrapRowContents : function(row_id, row_contents) {
      var row = JX.$N('tr',
                      { sigil : JX.MultirowRowManager.getRowSigil(),
                        meta : { multirow_row_manager_row_id : row_id }
                      },
                      row_contents);

      var removeButton = JX.$N(
        'td',
        { className: 'remove-column' },
        JX.$N(
          'a',
          { className: 'button simple',
            sigil: JX.MultirowRowManager._removeSigil
          },
          'Remove'));

      JX.DOM.appendContent(row, removeButton);
      return row;
    },

    getRowID : function(row) {
      return JX.Stratcom.getData(row).multirow_row_manager_row_id;
    },
    /**
       * @param row_contents [DOM elements] New contents of row
       * @param row_id row ID to update, will throw if this row has been removed
       */
    updateRow : function(row_id, row_contents) {
      if (__DEV__) {
        if (typeof this._rows[row_id] === 'undefined') {
          throw new Error('JX.MultirowRowManager.updateRow(row_id, ' +
                          'row_contents): provided row id does not exist.' +
                          ' Use addRow to create a new row and make sure ' +
                          'not to update rows that have been deleted.');
        }
      }
      var old_row = this._rows[row_id];
      var new_row = this._wrapRowContents(row_id, row_contents);
      JX.copy(JX.Stratcom.getData(new_row), JX.Stratcom.getData(old_row));

      JX.DOM.replace(old_row, new_row);
      this._rows[row_id] = new_row;

      this._oncountchanged(); // Fix the new button.
      return new_row;
    },

    addRow : function(row_contents) {
      var row_id = this._generateRowID();
      var row = this._wrapRowContents(row_id, row_contents);
      JX.DOM.appendContent(this._root, row);

      this._count++;
      this._oncountchanged();

      this._rows[row_id] = row;
      return row;
    },
    _onrowremoved : function(e) {
      if (!JX.Stratcom.getData(e.getTarget()).enabled) {
        return;
      }
      var row = e.getNode(JX.MultirowRowManager.getRowSigil());
      var row_id = this.getRowID(row);
      delete this._rows[row_id];
      JX.DOM.remove(row);

      this._count--;
      this._oncountchanged();
      this.invoke('row-removed', row_id);
    },

    _oncountchanged : function() {
      var buttons = JX.DOM.scry(
        this._root,
        'a',
        JX.MultirowRowManager._removeSigil);

      var disable = (this._minRows >= 0 && this._count <= this._minRows);
      for (var i = 0; i < buttons.length; i++) {
        var button = buttons[i];
        JX.DOM.alterClass(button, 'disabled', disable);
        JX.Stratcom.getData(button).enabled = !disable;
      }
    }
  },
  events : ['row-removed'],
  statics : {
    getRowSigil : function() {
      return 'tools-multirow-row-manager-row';
    },
    _removeSigil : 'tools-multirow-row-manager-row-remove'
  }
});
