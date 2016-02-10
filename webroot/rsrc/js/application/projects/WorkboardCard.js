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
      return this.getColumn().getBoard().getObjectProperties(this.getPHID());
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
        var template = this.getColumn().getBoard().getCardTemplate(phid);
        this._root = JX.$H(template).getFragment().firstChild;

        JX.Stratcom.getData(this._root).objectPHID = this.getPHID();
      }
      return this._root;
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
