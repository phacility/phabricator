/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-request
 *           javelin-typeahead-source
 * @provides javelin-typeahead-preloaded-source
 * @javelin
 */

/**
 * Simple datasource that loads all possible results from a single call to a
 * URI. This is appropriate if the total data size is small (up to perhaps a
 * few thousand items). If you have more items so you can't ship them down to
 * the client in one repsonse, use @{JX.TypeaheadOnDemandSource}.
 */
JX.install('TypeaheadPreloadedSource', {

  extend : 'TypeaheadSource',

  construct : function(uri) {
    JX.TypeaheadSource.call(this);
    this.uri = uri;
  },

  members : {

    ready : false,
    uri : null,
    lastValue : null,

    didChange : function(value) {
      if (this.ready) {
        this.matchResults(value);
      } else {
        this.lastValue = value;
        this.waitForResults();
      }
    },

    didStart : function() {
      var r = new JX.Request(this.uri, JX.bind(this, this.ondata));
      r.setMethod('GET');
      r.send();
    },

    ondata : function(results) {
      for (var ii = 0; ii < results.length; ++ii) {
        this.addResult(results[ii]);
      }
      if (this.lastValue !== null) {
        this.matchResults(this.lastValue);
      }
      this.ready = true;
    },

    setReady: function(ready) {
      this.ready = ready;
    }
  }
});
