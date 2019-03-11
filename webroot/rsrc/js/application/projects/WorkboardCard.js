/**
 * @provides javelin-workboard-card
 * @requires javelin-install
 * @javelin
 */

JX.install('WorkboardCard', {

  construct: function(column, phid) {
    this._column = column;
    this._phid = phid;
  },

  members: {
    _column: null,
    _phid: null,
    _root: null,

    getPHID: function() {
      return this._phid;
    },

    getColumn: function() {
      return this._column;
    },

    setColumn: function(column) {
      this._column = column;
    },

    getProperties: function() {
      return this.getColumn().getBoard()
        .getCardTemplate(this.getPHID())
        .getObjectProperties();
    },

    getPoints: function() {
      return this.getProperties().points;
    },

    getStatus: function() {
      return this.getProperties().status;
    },

    getNode: function() {
      if (!this._root) {
        var phid = this.getPHID();

        var root = this.getColumn().getBoard()
          .getCardTemplate(phid)
          .newNode();

        JX.Stratcom.getData(root).objectPHID = phid;

        this._root = root;
      }

      return this._root;
    },

    isWorkboardHeader: function() {
      return false;
    },

    redraw: function() {
      var old_node = this._root;
      this._root = null;
      var new_node = this.getNode();

      if (old_node && old_node.parentNode) {
        JX.DOM.replace(old_node, new_node);
      }

      return this;
    }

  }

});
