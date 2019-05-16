/**
 * @requires javelin-install
 *           javelin-stratcom
 *           javelin-util
 *           javelin-behavior
 *           javelin-json
 *           javelin-dom
 *           javelin-resource
 *           javelin-routable
 * @provides javelin-request
 * @javelin
 */

/**
 * Make basic AJAX XMLHTTPRequests.
 */
JX.install('Request', {
  construct : function(uri, handler) {
    this.setURI(uri);
    if (handler) {
      this.listen('done', handler);
    }
  },

  events : ['start', 'open', 'send', 'statechange', 'done', 'error', 'finally',
            'uploadprogress'],

  members : {

    _xhrkey : null,
    _transport : null,
    _sent : false,
    _finished : false,
    _block : null,
    _data : null,

    _getSameOriginTransport : function() {
      try {
        try {
          return new XMLHttpRequest();
        } catch (x) {
          return new ActiveXObject('Msxml2.XMLHTTP');
        }
      } catch (x) {
        return new ActiveXObject('Microsoft.XMLHTTP');
      }
    },

    _getCORSTransport : function() {
      try {
        var xport = new XMLHttpRequest();
        if ('withCredentials' in xport) {
          // XHR supports CORS
        } else if (typeof XDomainRequest != 'undefined') {
          xport = new XDomainRequest();
        }
        return xport;
      } catch (x) {
        return new XDomainRequest();
      }
    },

    getTransport : function() {
      if (!this._transport) {
        this._transport = this.getCORS() ? this._getCORSTransport() :
                                           this._getSameOriginTransport();
      }
      return this._transport;
    },

    getRoutable: function() {
      var routable = new JX.Routable();
      routable.listen('start', JX.bind(this, function() {
        // Pass the event to allow other listeners to "start" to configure this
        // request before it fires.
        JX.Stratcom.pass(JX.Stratcom.context());
        this.send();
      }));
      this.listen('finally', JX.bind(routable, routable.done));
      return routable;
    },

    send : function() {
      if (this._sent || this._finished) {
        if (__DEV__) {
          if (this._sent) {
            JX.$E(
              'JX.Request.send(): ' +
              'attempting to send a Request that has already been sent.');
          }
          if (this._finished) {
            JX.$E(
              'JX.Request.send(): ' +
              'attempting to send a Request that has finished or aborted.');
          }
        }
        return;
      }

      // Fire the "start" event before doing anything. A listener may
      // perform pre-processing or validation on this request
      this.invoke('start', this);
      if (this._finished) {
        return;
      }

      var xport = this.getTransport();
      xport.onreadystatechange = JX.bind(this, this._onreadystatechange);
      if (xport.upload) {
        xport.upload.onprogress = JX.bind(this, this._onuploadprogress);
      }

      var method = this.getMethod().toUpperCase();

      if (__DEV__) {
        if (this.getRawData()) {
          if (method != 'POST') {
            JX.$E(
              'JX.Request.send(): ' +
              'attempting to send post data over GET. You must use POST.');
          }
        }
      }

      var list_of_pairs = this._data || [];
      list_of_pairs.push(['__ajax__', true]);

      this._block = JX.Stratcom.allocateMetadataBlock();
      list_of_pairs.push(['__metablock__', this._block]);

      var q = (this.getDataSerializer() ||
               JX.Request.defaultDataSerializer)(list_of_pairs);
      var uri = this.getURI();

      // If we're sending a file, submit the metadata via the URI instead of
      // via the request body, because the entire post body will be consumed by
      // the file content.
      if (method == 'GET' || this.getRawData()) {
        uri += ((uri.indexOf('?') === -1) ? '?' : '&') + q;
      }

      if (this.getTimeout()) {
        this._timer = setTimeout(
          JX.bind(
            this,
            this._fail,
            JX.Request.ERROR_TIMEOUT),
          this.getTimeout());
      }

      xport.open(method, uri, true);

      // Must happen after xport.open so that listeners can modify the transport
      // Some transport properties can only be set after the transport is open
      this.invoke('open', this);
      if (this._finished) {
        return;
      }

      this.invoke('send', this);
      if (this._finished) {
        return;
      }

      if (method == 'POST') {
        if (this.getRawData()) {
          xport.send(this.getRawData());
        } else {
          xport.setRequestHeader(
            'Content-Type',
            'application/x-www-form-urlencoded');
          xport.send(q);
        }
      } else {
        xport.send(null);
      }

      this._sent = true;
    },

    abort : function() {
      this._cleanup();
    },

    _onuploadprogress : function(progress) {
      this.invoke('uploadprogress', progress);
    },

    _onreadystatechange : function() {
      var xport = this.getTransport();
      var response;
      try {
        this.invoke('statechange', this);
        if (this._finished) {
          return;
        }
        if (xport.readyState != 4) {
          return;
        }
        // XHR requests to 'file:///' domains return 0 for success, which is why
        // we treat it as a good result in addition to HTTP 2XX responses.
        if (xport.status !== 0 && (xport.status < 200 || xport.status >= 300)) {
          this._fail();
          return;
        }

        if (__DEV__) {
          var expect_guard = this.getExpectCSRFGuard();

          if (!xport.responseText.length) {
            JX.$E(
              'JX.Request("'+this.getURI()+'", ...): '+
              'server returned an empty response.');
          }
          if (expect_guard && xport.responseText.indexOf('for (;;);') !== 0) {
            JX.$E(
              'JX.Request("'+this.getURI()+'", ...): '+
              'server returned an invalid response.');
          }
          if (expect_guard && xport.responseText == 'for (;;);') {
            JX.$E(
              'JX.Request("'+this.getURI()+'", ...): '+
              'server returned an empty response.');
          }
        }

        response = this._extractResponse(xport);
        if (!response) {
          JX.$E(
            'JX.Request("'+this.getURI()+'", ...): '+
            'server returned an invalid response.');
        }
      } catch (exception) {

        if (__DEV__) {
          JX.log(
            'JX.Request("'+this.getURI()+'", ...): '+
            'caught exception processing response: '+exception);
        }
        this._fail();
        return;
      }

      try {
        this._handleResponse(response);
        this._cleanup();
      } catch (exception) {
        //  In Firefox+Firebug, at least, something eats these. :/
        setTimeout(function() {
          throw exception;
        }, 0);
      }
    },

    _extractResponse : function(xport) {
      var text = xport.responseText;

      if (this.getExpectCSRFGuard()) {
        text = text.substring('for (;;);'.length);
      }

      var type = this.getResponseType().toUpperCase();
      if (type == 'TEXT') {
        return text;
      } else if (type == 'JSON' || type == 'JAVELIN') {
        return JX.JSON.parse(text);
      } else if (type == 'XML') {
        var doc;
        try {
          if (typeof DOMParser != 'undefined') {
            var parser = new DOMParser();
            doc = parser.parseFromString(text, 'text/xml');
          } else {  // IE
            // an XDomainRequest
            doc = new ActiveXObject('Microsoft.XMLDOM');
            doc.async = false;
            doc.loadXML(xport.responseText);
          }

          return doc.documentElement;
        } catch (exception) {
          if (__DEV__) {
            JX.log(
              'JX.Request("'+this.getURI()+'", ...): '+
              'caught exception extracting response: '+exception);
          }
          this._fail();
          return null;
        }
      }

      if (__DEV__) {
        JX.$E(
          'JX.Request("'+this.getURI()+'", ...): '+
          'unrecognized response type.');
      }
      return null;
    },

    _fail : function(error) {
      this._cleanup();

      this.invoke('error', error, this);
      this.invoke('finally');
    },

    _done : function(response) {
      this._cleanup();

      if (response.onload) {
        for (var ii = 0; ii < response.onload.length; ii++) {
          (new Function(response.onload[ii]))();
        }
      }

      var payload;
      if (this.getRaw()) {
        payload = response;
      } else {
        payload = response.payload;
        JX.Request._parseResponsePayload(payload);
      }

      this.invoke('done', payload, this);
      this.invoke('finally');
    },

    _cleanup : function() {
      this._finished = true;
      clearTimeout(this._timer);
      this._timer = null;

      // Should not abort the transport request if it has already completed
      // Otherwise, we may see an "HTTP request aborted" error in the console
      // despite it possibly having succeeded.
      if (this._transport && this._transport.readyState != 4) {
        this._transport.abort();
      }
    },

    setData : function(dictionary) {
      this._data = null;
      this.addData(dictionary);
      return this;
    },

    addData : function(dictionary) {
      if (!this._data) {
        this._data = [];
      }
      for (var k in dictionary) {
        this._data.push([k, dictionary[k]]);
      }
      return this;
    },

    setDataWithListOfPairs : function(list_of_pairs) {
      this._data = list_of_pairs;
      return this;
    },

    _handleResponse : function(response) {
      if (this.getResponseType().toUpperCase() == 'JAVELIN') {
        if (response.error) {
          this._fail(response.error);
        } else {
          JX.Stratcom.mergeData(
            this._block,
            response.javelin_metadata || {});

          var when_complete = JX.bind(this, function() {
            this._done(response);
            JX.initBehaviors(response.javelin_behaviors || {});
          });

          if (response.javelin_resources) {
            JX.Resource.load(response.javelin_resources, when_complete);
          } else {
            when_complete();
          }
        }
      } else {
        this._cleanup();
        this.invoke('done', response, this);
        this.invoke('finally');
      }
    }
  },

  statics : {
    ERROR_TIMEOUT : -9000,
    defaultDataSerializer : function(list_of_pairs) {
      var uri = [];
      for (var ii = 0; ii < list_of_pairs.length; ii++) {
        var pair = list_of_pairs[ii];

        if (pair[1] === null) {
          continue;
        }

        var name = encodeURIComponent(pair[0]);
        var value = encodeURIComponent(pair[1]);
        uri.push(name + '=' + value);
      }
      return uri.join('&');
    },

    /**
     * When we receive a JSON blob, parse it to introduce meaningful objects
     * where there are magic keys for placeholders.
     *
     * Objects with the magic key '__html' are translated into JX.HTML objects.
     *
     * This function destructively modifies its input.
     */
    _parseResponsePayload: function(parent, index) {
      var recurse = JX.Request._parseResponsePayload;
      var obj = (typeof index !== 'undefined') ? parent[index] : parent;
      if (JX.isArray(obj)) {
        for (var ii = 0; ii < obj.length; ii++) {
          recurse(obj, ii);
        }
      } else if (obj && typeof obj == 'object') {
        if (('__html' in obj) && (obj.__html !== null)) {
          parent[index] = JX.$H(obj.__html);
        } else {
          for (var key in obj) {
            recurse(obj, key);
          }
        }
      }
    }
  },

  properties : {
    URI : null,
    dataSerializer : null,
    /**
     * Configure which HTTP method to use for the request. Permissible values
     * are "POST" (default) or "GET".
     *
     * @param string HTTP method, one of "POST" or "GET".
     */
    method : 'POST',
    /**
     * Set the data parameter of transport.send. Useful if you want to send a
     * file or FormData. Not that you cannot send raw data and data at the same
     * time.
     *
     * @param Data, argument to transport.send
     */
    rawData: null,
    raw : false,

    /**
     * Configure a timeout, in milliseconds. If the request has not resolved
     * (either with success or with an error) within the provided timeframe,
     * it will automatically fail with error JX.Request.ERROR_TIMEOUT.
     *
     * @param int Timeout, in milliseconds (e.g. 3000 = 3 seconds).
     */
    timeout : null,

    /**
     * Whether or not we should expect the CSRF guard in the response.
     *
     * @param bool
     */
    expectCSRFGuard : true,

    /**
     * Whether it should be a CORS (Cross-Origin Resource Sharing) request to
     * a third party domain other than the current site.
     *
     * @param bool
     */
    CORS : false,

    /**
     * Type of the response.
     *
     * @param enum 'JAVELIN', 'JSON', 'XML', 'TEXT'
     */
    responseType : 'JAVELIN'
  }

});
