/**
 * @requires javelin-install
 *           javelin-typeahead-source
 * @provides javelin-typeahead-static-source
 * @javelin
 */

/**
 * Typeahead source that uses static data passed to the constructor. For larger
 * datasets, use @{class:JX.TypeaheadPreloadedSource} or
 * @{class:JX.TypeaheadOnDemandSource} to improve performance.
 *
 * @group control
 */
JX.install('TypeaheadStaticSource', {

  extend : 'TypeaheadSource',

  construct : function(data) {
    JX.TypeaheadSource.call(this);
    this._data = data;
  },

  members : {
    _data : null,

    didChange : function(value) {
      this.matchResults(value);
    },

    didStart : function() {
      for (var ii = 0; ii < this._data.length; ii++) {
        this.addResult(this._data[ii]);
      }
    }
  }
});



