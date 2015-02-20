/**
 * @provides javelin-resource
 * @requires javelin-util
 *           javelin-uri
 *           javelin-install
 *
 * @javelin
 */

JX.install('Resource', {

  statics: {

    _loading: {},
    _loaded: {},
    _links: [],
    _callbacks: [],

    /**
     * Loads one or many static resources (JavaScript or CSS) and executes a
     * callback once these resources have finished loading.
     *
     * @param string|array  static resource or list of resources to be loaded
     * @param function      callback when resources have finished loading
     */
    load: function(list, callback) {
      var resources = {},
        uri, resource, path;

      list = JX.$AX(list);

      // In the event there are no resources to wait on, call the callback and
      // exit. NOTE: it's better to do this check outside this function and not
      // call through JX.Resource, but it's not always easy/possible to do so
      if (!list.length) {
        setTimeout(callback, 0);
        return;
      }

      for (var ii = 0; ii < list.length; ii++) {
        uri = new JX.URI(list[ii]);
        resource = uri.toString();
        path = uri.getPath();
        resources[resource] = true;

        if (JX.Resource._loaded[resource]) {
          setTimeout(JX.bind(JX.Resource, JX.Resource._complete, resource), 0);
        } else if (!JX.Resource._loading[resource]) {
          JX.Resource._loading[resource] = true;
          if (path.indexOf('.css') == path.length - 4) {
            JX.Resource._loadCSS(resource);
          } else {
            JX.Resource._loadJS(resource);
          }
        }
      }

      JX.Resource._callbacks.push({
        resources: resources,
        callback: callback
      });
    },

    _loadJS: function(uri) {
      var script = document.createElement('script');
      var load_callback = function() {
        JX.Resource._complete(uri);
      };
      var error_callback = function() {
        JX.$E('Resource: JS file download failure: ' + uri);
      };

      JX.copy(script, {
        type: 'text/javascript',
        src: uri
      });

      script.onload = load_callback;
      script.onerror = error_callback;
      script.onreadystatechange = function() {
        var state = this.readyState;
        if (state == 'complete' || state == 'loaded') {
          load_callback();
        }
      };
      document.getElementsByTagName('head')[0].appendChild(script);
    },

    _loadCSS: function(uri) {
      var link = JX.copy(document.createElement('link'), {
        type: 'text/css',
        rel: 'stylesheet',
        href: uri,
        'data-href': uri // don't trust href
      });
      document.getElementsByTagName('head')[0].appendChild(link);

      JX.Resource._links.push(link);
      if (!JX.Resource._timer) {
        JX.Resource._timer = setInterval(JX.Resource._poll, 20);
      }
    },

    _poll: function() {
      var sheets = document.styleSheets,
        ii = sheets.length,
        links = JX.Resource._links;

      // Cross Origin CSS loading
      // http://yearofmoo.com/2011/03/cross-browser-stylesheet-preloading/
      while (ii--) {
        var link = sheets[ii],
          owner = link.ownerNode || link.owningElement,
          jj = links.length;
        if (owner) {
          while (jj--) {
            if (owner == links[jj]) {
              JX.Resource._complete(links[jj]['data-href']);
              links.splice(jj, 1);
            }
          }
        }
      }

      if (!links.length) {
        clearInterval(JX.Resource._timer);
        JX.Resource._timer = null;
      }
    },

    _complete: function(uri) {
      var list = JX.Resource._callbacks,
        current, ii;

      delete JX.Resource._loading[uri];
      JX.Resource._loaded[uri] = true;

      var errors = [];
      for (ii = 0; ii < list.length; ii++) {
        current = list[ii];
        delete current.resources[uri];
        if (!JX.Resource._hasResources(current.resources)) {
          try {
            current.callback();
          } catch (error) {
            errors.push(error);
          }
          list.splice(ii--, 1);
        }
      }

      if (errors.length) {
        throw errors[0];
      }
    },

    _hasResources: function(resources) {
      for (var hasResources in resources) {
        return true;
      }
      return false;
    }

  },

  initialize: function() {
    var list = JX.$A(document.getElementsByTagName('link')),
      ii = list.length,
      node;
    while ((node = list[--ii])) {
      if (node.type == 'text/css' && node.href) {
        JX.Resource._loaded[(new JX.URI(node.href)).toString()] = true;
      }
    }

    list = JX.$A(document.getElementsByTagName('script'));
    ii = list.length;
    while ((node = list[--ii])) {
      if (node.type == 'text/javascript' && node.src) {
        JX.Resource._loaded[(new JX.URI(node.src)).toString()] = true;
      }
    }
  }

});
