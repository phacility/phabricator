/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-request
 *           javelin-typeahead-source
 * @provides javelin-typeahead-ondemand-source
 * @javelin
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

    didChange : function(raw_value) {
      this.lastChange = JX.now();
      var value = this.normalize(raw_value);

      if (this.haveData[value]) {
        this.matchResults(raw_value);
      } else {
        // If we have data for any prefix of the query, send those results
        // back immediately. This allows "alinc" -> "alinco" to show partial
        // results without the UI flickering. We'll still show the loading
        // state, and then can show better results once we get everything
        // back.
        for (var ii = value.length - 1; ii > 0; ii--) {
          var substr = value.substring(0, ii);
          if (this.haveData[substr]) {
            this.matchResults(raw_value, true);
            break;
          }
        }

        this.waitForResults();
        setTimeout(
          JX.bind(this, this.sendRequest, this.lastChange, value, raw_value),
          this.getQueryDelay()
        );
      }
    },

    sendRequest : function(when, value, raw_value) {
      if (when != this.lastChange) {
        return;
      }
      var r = new JX.Request(
        this.uri,
        JX.bind(this, this.ondata, this.lastChange, raw_value));
      r.setMethod('GET');
      r.setData(JX.copy(this.getAuxiliaryData(), {q : value, raw: raw_value}));
      r.send();
    },

    ondata : function(when, raw_value, results) {
      if (results) {
        for (var ii = 0; ii < results.length; ii++) {
          this.addResult(results[ii]);
        }
      }

      var value = this.normalize(raw_value);
      this.haveData[value] = true;

      if (when != this.lastChange) {
        return;
      }

      this.matchResults(raw_value);
    }
  }
});
