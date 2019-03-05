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
    vector: null
  },

  members: {
    _headerKey: null,

    getHeaderKey: function() {
      return this._headerKey;
    }

  }

});
