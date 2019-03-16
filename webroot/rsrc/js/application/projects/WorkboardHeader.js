/**
 * @provides javelin-workboard-header
 * @requires javelin-install
 * @javelin
 */

JX.install('WorkboardHeader', {

  construct: function(column, header_key) {
    this._column = column;
    this._headerKey = header_key;
  },

  members: {
    _root: null,
    _column: null,
    _headerKey: null,

    getColumn: function() {
      return this._column;
    },

    getHeaderKey: function() {
      return this._headerKey;
    },

    getNode: function() {
      if (!this._root) {
        var header_key = this.getHeaderKey();

        var root = this.getColumn().getBoard()
          .getHeaderTemplate(header_key)
          .newNode();

        JX.Stratcom.getData(root).headerKey = header_key;

        this._root = root;
      }

      return this._root;
    },

    isWorkboardHeader: function() {
      return true;
    }
  }

});
