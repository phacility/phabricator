/**
 * @provides javelin-workboard-card-template
 * @requires javelin-install
 * @javelin
 */

JX.install('WorkboardCardTemplate', {

  construct: function(phid) {
    this._phid = phid;
    this._vectors = {};
    this._headerKeys = {};

    this.setObjectProperties({});
  },

  properties: {
    objectProperties: null
  },

  members: {
    _phid: null,
    _html: null,
    _vectors: null,
    _headerKeys: null,

    getPHID: function() {
      return this._phid;
    },

    getVersion: function() {
      // TODO: For now, just return a constant version number.
      return 1;
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

    setHeaderKey: function(order, key) {
      this._headerKeys[order] = key;
      return this;
    },

    getHeaderKey: function(order) {
      return this._headerKeys[order];
    },

    newNode: function() {
      return JX.$H(this._html).getFragment().firstChild;
    },

    setObjectProperty: function(key, value) {
      this.getObjectProperties()[key] = value;
      return this;
    }
  }

});
