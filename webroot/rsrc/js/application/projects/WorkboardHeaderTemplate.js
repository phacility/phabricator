/**
 * @provides javelin-workboard-header-template
 * @requires javelin-install
 * @javelin
 */

JX.install('WorkboardHeaderTemplate', {

  construct: function(header_key) {
    this._headerKey = header_key;
  },

  properties: {
    template: null,
    order: null,
    vector: null,
    editProperties: null,
    dropEffects: []
  },

  members: {
    _headerKey: null,
    _html: null,

    getHeaderKey: function() {
      return this._headerKey;
    },

    setNodeHTMLTemplate: function(html) {
      this._html = html;
      return this;
    },

    newNode: function() {
      return JX.$H(this._html).getFragment().firstChild;
    }

  }

});
