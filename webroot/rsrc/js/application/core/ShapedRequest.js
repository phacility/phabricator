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

      if (this._request) {
        // Waiting on a request, rate-limit.
        return;
      }

      if (this._min && (new Date().getTime() < this._min)) {
        // Just got a request back, rate-limit.
        return;
      }

      this._defer && this._defer.stop();

      var data = this._dataCallback();

      if (this.shouldSendRequest(this._last, data)) {
        this._last = data;
        var request = new JX.Request(this._uri, JX.bind(this, function(r) {
          this._callback(r);

          this._min = new Date().getTime() + this._rateLimit;
          this._defer && this._defer.stop();
          this._defer = JX.defer(JX.bind(this, this.trigger), this._rateLimit);
        }));
        request.listen('finally', JX.bind(this, function() {
          this._request = null;
        }));
        request.setData(data);
        request.setTimeout(this.getFrequency());
        request.send();
      } else {
        this._defer = JX.defer(JX.bind(this, this.trigger), this._frequency);
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
    rateLimit : 250,
    frequency : 750
  }
});
