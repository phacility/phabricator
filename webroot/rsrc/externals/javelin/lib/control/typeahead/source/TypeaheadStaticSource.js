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
 */
JX.install('TypeaheadStaticSource', {

  extend : 'TypeaheadSource',

  construct : function(data) {
    JX.TypeaheadSource.call(this);
    this.data = data;
  },

  members : {
    data : null,

    didChange : function(value) {
      this.matchResults(value);
    },

    didStart : function() {
      for (var ii = 0; ii < this.data.length; ii++) {
        this.addResult(this.data[ii]);
      }
    }
  }
});
