/**
 * @requires javelin-stratcom
 *           javelin-install
 *           javelin-uri
 *           javelin-util
 * @provides javelin-history
 * @javelin
 */

/**
 * JX.History provides a stable interface for managing the browser's history
 * stack. Whenever the history stack mutates, the "history:change" event is
 * invoked via JX.Stratcom.
 *
 * Inspired by History Manager implemented by Christoph Pojer (@cpojer)
 * @see https://github.com/cpojer/mootools-history
 */
JX.install('History', {

  statics : {

    // Mechanisms to @{JX.History.install} with (in preferred support order).
    // The default behavior is to use the best supported mechanism.
    DEFAULT : Infinity,
    PUSHSTATE : 3,
    HASHCHANGE : 2,
    POLLING : 1,

    // Last path parsed from the URL fragment.
    _hash : null,

    // Some browsers fire an extra "popstate" on initial page load, so we keep
    // track of the initial path to normalize behavior (and not fire the extra
    // event).
    _initialPath : null,

    // Mechanism used to interface with the browser history stack.
    _mechanism : null,

    /**
     * Starts history management. This method must be invoked first before any
     * other JX.History method can be used.
     *
     * @param int An optional mechanism used to interface with the browser
     *            history stack. If it is not supported, the next supported
     *            mechanism will be used.
     */
    install : function(mechanism) {
      if (__DEV__) {
        if (JX.History._installed) {
          JX.$E('JX.History.install(): can only install once.');
        }
        JX.History._installed = true;
      }

      mechanism = mechanism || JX.History.DEFAULT;

      if (mechanism >= JX.History.PUSHSTATE && 'pushState' in history) {
        JX.History._mechanism = JX.History.PUSHSTATE;
        JX.History._initialPath = JX.History._getBasePath(location.href);
        JX.Stratcom.listen('popstate', null, JX.History._handleChange);
      } else if (mechanism >= JX.History.HASHCHANGE &&
                 'onhashchange' in window) {
        JX.History._mechanism = JX.History.HASHCHANGE;
        JX.Stratcom.listen('hashchange', null, JX.History._handleChange);
      } else {
        JX.History._mechanism = JX.History.POLLING;
        setInterval(JX.History._handleChange, 200);
      }
    },

    /**
     * Get the name of the mechanism used to interface with the browser
     * history stack.
     *
     * @return string Mechanism, either pushstate, hashchange, or polling.
     */
    getMechanism : function() {
      if (__DEV__) {
        if (!JX.History._installed) {
          JX.$E(
            'JX.History.getMechanism(): ' +
            'must call JX.History.install() first.');
        }
      }
      return JX.History._mechanism;
    },

    /**
     * Returns the path on top of the history stack.
     *
     * If the HTML5 History API is unavailable and an eligible path exists in
     * the current URL fragment, the fragment is parsed for a path. Otherwise,
     * the current URL path is returned.
     *
     * @return string Path on top of the history stack.
     */
    getPath : function() {
      if (__DEV__) {
        if (!JX.History._installed) {
          JX.$E(
            'JX.History.getPath(): ' +
            'must call JX.History.install() first.');
        }
      }
      if (JX.History.getMechanism() === JX.History.PUSHSTATE) {
        return JX.History._getBasePath(location.href);
      } else {
        var parsed = JX.History._parseFragment(location.hash);
        return parsed || JX.History._getBasePath(location.href);
      }
    },

    /**
     * Pushes a path onto the history stack.
     *
     * @param string Path.
     * @param wild State object for History API.
     * @return void
     */
    push : function(path, state) {
      if (__DEV__) {
        if (!JX.History._installed) {
          JX.$E(
            'JX.History.push(): ' +
            'must call JX.History.install() first.');
        }
      }
      if (JX.History.getMechanism() === JX.History.PUSHSTATE) {
        if (JX.History._initialPath && JX.History._initialPath !== path) {
          JX.History._initialPath = null;
        }
        history.pushState(state || null, null, path);
        JX.History._fire(path, state);
      } else {
        location.hash = JX.History._composeFragment(path);
      }
    },

    /**
     * Modifies the path on top of the history stack.
     *
     * @param string Path.
     * @return void
     */
    replace : function(path) {
      if (__DEV__) {
        if (!JX.History._installed) {
          JX.$E(
            'JX.History.replace(): ' +
            'must call JX.History.install() first.');
        }
      }
      if (JX.History.getMechanism() === JX.History.PUSHSTATE) {
        history.replaceState(null, null, path);
        JX.History._fire(path);
      } else {
        var uri = JX.$U(location.href);
        uri.setFragment(JX.History._composeFragment(path));
        // Safari bug: "location.replace" does not respect changes made via
        // setting "location.hash", so use "history.replaceState" if possible.
        if ('replaceState' in history) {
          history.replaceState(null, null, uri.toString());
          JX.History._handleChange();
        } else {
          location.replace(uri.toString());
        }
      }
    },

    _handleChange : function(e) {
      var path = JX.History.getPath();
      var state = (e && e.getRawEvent().state);

      if (JX.History.getMechanism() === JX.History.PUSHSTATE) {
        if (path === JX.History._initialPath) {
          JX.History._initialPath = null;
        } else {
          JX.History._fire(path, state);
        }
      } else {
        if (path !== JX.History._hash) {
          JX.History._hash = path;
          JX.History._fire(path);
        }
      }
    },

    _fire : function(path, state) {
      JX.Stratcom.invoke('history:change', null, {
        path: JX.History._getBasePath(path),
        state: state
      });
    },

    _getBasePath : function(href) {
      return JX.$U(href).setProtocol(null).setDomain(null).toString();
    },

    _composeFragment : function(path) {
      path = JX.History._getBasePath(path);
      // If the URL fragment does not change, the new path will not get pushed
      // onto the stack. So we alternate the hash prefix to force a new state.
      if (JX.History.getPath() === path) {
        var hash = location.hash;
        if (hash && hash.charAt(1) === '!') {
          return '~!' + path;
        }
      }
      return '!' + path;
    },

    _parseFragment : function(fragment) {
      if (fragment) {
        if (fragment.charAt(1) === '!') {
          return fragment.substr(2);
        } else if (fragment.substr(1, 2) === '~!') {
          return fragment.substr(3);
        }
      }
      return null;
    }

  }

});
