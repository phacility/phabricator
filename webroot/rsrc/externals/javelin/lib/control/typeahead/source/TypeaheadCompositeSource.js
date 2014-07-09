/**
 * @requires javelin-install
 *           javelin-typeahead-source
 *           javelin-util
 * @provides javelin-typeahead-composite-source
 * @javelin
 */

JX.install('TypeaheadCompositeSource', {

  extend : 'TypeaheadSource',

  construct : function(sources) {
    JX.TypeaheadSource.call(this);
    this.sources = sources;

    for (var ii = 0; ii < this.sources.length; ++ii) {
      var child = this.sources[ii];
      child.listen('waiting', JX.bind(this, this.childWaiting));
      child.listen('resultsready', JX.bind(this, this.childResultsReady));
      child.listen('complete', JX.bind(this, this.childComplete));
    }
  },

  members : {
    sources : null,
    results : null,
    completeCount : 0,

    didChange : function(value) {
      this.results = [];
      this.completeCount = 0;
      for (var ii = 0; ii < this.sources.length; ++ii) {
        this.sources[ii].didChange(value);
      }
    },

    didStart : function() {
      for (var ii = 0; ii < this.sources.length; ++ii) {
        this.sources[ii].didStart();
      }
    },

    childWaiting : function() {
      if (!this.results || !this.results.length) {
        this.invoke('waiting');
      }
    },

    childResultsReady : function(nodes, value) {
      this.results = this.mergeResults(this.results || [], nodes);
      this.invoke('resultsready', this.results, value);
    },

    childComplete : function() {
      this.completeCount++;
      if (this.completeCount == this.sources.length) {
        this.invoke('complete');
      }
    },

    /**
     * Overrideable strategy for combining results.
     * By default, appends results as they come in
     * so that results don't jump around.
     */
    mergeResults : function(oldResults, newResults) {
      for (var ii = 0; ii < newResults.length; ++ii) {
        oldResults.push(newResults[ii]);
      }
      return oldResults;
    }
  }
});
