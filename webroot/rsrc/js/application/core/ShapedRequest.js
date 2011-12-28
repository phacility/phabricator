/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-request
 * @provides phabricator-shaped-request
 * @javelin
 */

/**
 * Send requests with rate limiting and retries, in response to some application
 * trigger. This is used to implement comment previews in Differential and
 * Maniphest.
 */
JX.install('PhabricatorShapedRequest', {

  construct : function(uri, callback, data_callback) {
    this._uri = uri;
    this._callback = callback;
    this._dataCallback = data_callback;
  },

  members : {
    _callback : null,
    _dataCallback : null,
    _request : null,
    _min : null,
    _defer : null,
    _last : null,
    start : function() {
      this.trigger();
    },

    trigger : function() {

      clearTimeout(this._defer);
      var data = this._dataCallback();

      // Waiting on a request, rate-limit.
      var waiting = (this._request);

      // Just got a request back, rate-limit.
      var recent = (this._min && (new Date().getTime() < this._min));

      if (!waiting && !recent && this.shouldSendRequest(this._last, data)) {
        this._last = data;
        var request = new JX.Request(this._uri, JX.bind(this, function(r) {
          this._callback(r);

          this._min = new Date().getTime() + this.getRateLimit();
          clearTimeout(this._defer);
          this._defer = setTimeout(
            JX.bind(this, this.trigger),
            this.getRateLimit()
          );
        }));
        request.listen('finally', JX.bind(this, function() {
          this._request = null;
        }));
        request.setData(data);
        request.setTimeout(this.getRequestTimeout());
        request.send();
      } else {
        this._defer = setTimeout(
          JX.bind(this, this.trigger),
          this.getFrequency()
        );
      }
    },

    shouldSendRequest : function(last, data) {
      if (last === null) {
        return true;
      }

      for (var k in last) {
        if (data[k] !== last[k]) {
          return true;
        }
      }
      return false;
    }

  },

  properties : {
    rateLimit : 500,
    frequency : 1000,
    requestTimeout : 20000
  }
});
