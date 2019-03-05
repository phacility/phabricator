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
        var board = this.getColumn().getBoard();
        var template = board.getHeaderTemplate(header_key).getTemplate();
        this._root = JX.$H(template).getFragment().firstChild;

        JX.Stratcom.getData(this._root).headerKey = header_key;
      }
      return this._root;
    },

    isWorkboardHeader: function() {
      return true;
    }
  }

});
