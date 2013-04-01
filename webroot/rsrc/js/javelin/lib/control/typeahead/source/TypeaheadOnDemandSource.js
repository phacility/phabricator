/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-stratcom
 *           javelin-request
 *           javelin-typeahead-source
 * @provides javelin-typeahead-ondemand-source
 * @javelin
 */

/**
 * @group control
 */
JX.install('TypeaheadOnDemandSource', {

  extend : 'TypeaheadSource',

  construct : function(uri) {
    JX.TypeaheadSource.call(this);
    this.uri = uri;
    this.haveData = {
      '' : true
    };
  },

  properties : {
    /**
     * Configures how many milliseconds we wait after the user stops typing to
     * send a request to the server. Setting a value of 250 means "wait 250
     * milliseconds after the user stops typing to request typeahead data".
     * Higher values reduce server load but make the typeahead less responsive.
     */
    queryDelay : 125,
    /**
     * Auxiliary data to pass along when sending the query for server results.
     */
    auxiliaryData : {}
  },

  members : {
    uri : null,
    lastChange : null,
    haveData : null,

    didChange : function(value) {
      this.lastChange = JX.now();
      value = this.normalize(value);

      if (this.haveData[value]) {
        this.matchResults(value);
      } else {
        this.waitForResults();
        setTimeout(
          JX.bind(this, this.sendRequest, this.lastChange, value),
          this.getQueryDelay()
        );
      }
    },

    sendRequest : function(when, value) {
      if (when != this.lastChange) {
        return;
      }
      var r = new JX.Request(
        this.uri,
        JX.bind(this, this.ondata, this.lastChange, value));
      r.setMethod('GET');
      r.setData(JX.copy(this.getAuxiliaryData(), {q : value}));
      r.send();
    },

    ondata : function(when, value, results) {
      if (results) {
        for (var ii = 0; ii < results.length; ii++) {
          this.addResult(results[ii]);
        }
      }
      this.haveData[value] = true;
      if (when != this.lastChange) {
        return;
      }
      this.matchResults(value);
    }
  }
});


