/**
 * @provides javelin-workboard-card-template
 * @requires javelin-install
 * @javelin
 */

JX.install('WorkboardCardTemplate', {

  construct: function(phid) {
    this._phid = phid;
    this._vectors = {};

    this.setObjectProperties({});
  },

  properties: {
    objectProperties: null
  },

  members: {
    _phid: null,
    _vectors: null,

    getPHID: function() {
      return this._phid;
    },

    setNodeHTMLTemplate: function(html) {
      this._html = html;
      return this;
    },

    setSortVector: function(order, vector) {
      this._vectors[order] = vector;
      return this;
    },

    getSortVector: function(order) {
      return this._vectors[order];
    },

    newNode: function() {
      return JX.$H(this._html).getFragment().firstChild;
    }
  }

});
