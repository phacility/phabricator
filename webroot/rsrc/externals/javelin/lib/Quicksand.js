/**
 * @requires javelin-install
 * @provides javelin-quicksand
 * @javelin
 */

/**
 * Sink into a hopeless, cold mire of limitless depth from which there is
 * no escape.
 *
 * Captures navigation events (like clicking links and using the back button)
 * and expresses them in Javascript instead, emulating complex native browser
 * behaviors in a language and context ill-suited to the task.
 *
 * By doing this, you abandon all hope and retreat to a world devoid of light
 * or goodness. However, it allows you to have persistent UI elements which are
 * not disrupted by navigation. A tempting trade, surely?
 *
 * To cast your soul into the darkness, use:
 *
 *   JX.Quicksand
 *     .setFrame(node)
 *     .start();
 */
JX.install('Quicksand', {

  statics: {
    _id: null,
    _onpage: 0,
    _cursor: 0,
    _current: 0,
    _content: {},
    _responses: {},
    _history: [],
    _started: false,
    _frameNode: null,
    _contentNode: null,
    _uriPatternBlacklist: [],

    /**
     * Start Quicksand, accepting a fate of eternal torment.
     */
    start: function(first_response) {
      var self = JX.Quicksand;
      if (self._started) {
        return;
      }

      JX.Stratcom.listen('click', 'tag:a', self._onclick);
      JX.Stratcom.listen('history:change', null, self._onchange);

      self._started = true;
      var path = JX.$U(window.location).getRelativeURI();
      self._id = window.history.state || 0;
      var id = self._id;
      self._onpage = id;
      self._history.push({path: path, id: id});

      self._responses[id] = first_response;
    },


    /**
     * Set the frame node which Quicksand controls content for.
     */
    setFrame: function(frame) {
      var self = JX.Quicksand;
      self._frameNode = frame;
      return self;
    },


    getCurrentPageID: function() {
      var self = JX.Quicksand;
      if (self._id === null) {
        self._id = window.history.state || 0;
      }
      return self._id;
    },

    /**
     * Respond to the user clicking a link.
     *
     * After a long list of checks, we may capture and simulate the resulting
     * navigation.
     */
    _onclick: function(e) {
      var self = JX.Quicksand;

      if (!self._frameNode) {
        // If Quicksand has no frame, bail.
        return;
      }

      if (JX.Stratcom.pass()) {
        // If something else handled the event, bail.
        return;
      }

      if (!e.isNormalClick()) {
        // If this is a right-click, control click, etc., bail.
        return;
      }

      if (e.getNode('workflow')) {
        // Because JX.Workflow also passes these events, it might still want
        // the event. Don't trigger if there's a workflow node in the stack.
        return;
      }

      var a = e.getNode('tag:a');
      var href = a.href;
      if (!href || !href.length) {
        // If the <a /> the user clicked has no href, or the href is empty,
        // bail.
        return;
      }

      if (href[0] == '#') {
        // If this is an anchor on the current page, bail.
        return;
      }

      var uri = new JX.$U(href);
      var here = new JX.$U(window.location);
      if (uri.getDomain() != here.getDomain()) {
        // If the link is off-domain, bail.
        return;
      }

      if (uri.getFragment() && uri.getPath() == here.getPath()) {
        // If the link has an anchor but points at the current path, bail.
        // This is presumably a long-form anchor on the current page.

        // TODO: This technically gets links which change query parameters
        // wrong: they are navigation events but we won't Quicksand them.
        return;
      }

      if (self._isURIOnBlacklist(uri)) {
        // This URI is blacklisted as not navigable via Quicksand.
        return;
      }

      // The fate of this action is sealed. Suck it into the depths.
      e.kill();

      // If we're somewhere in history (that is, the user has pressed the
      // back button one or more times, putting us in a state where pressing
      // the forward button would do something) and we're navigating forward,
      // all the stuff ahead of us is about to become unreachable when we
      // navigate. Throw it away.
      var discard = (self._history.length - self._cursor) - 1;
      for (var ii = 0; ii < discard; ii++) {
        var obsolete = self._history.pop();
        self._responses[obsolete.id] = false;
      }

      // Set up the new state and fire a request to fetch the page data.
      var path = JX.$U(uri).getRelativeURI();
      var id = ++self._id;

      self._history.push({path: path, id: id});
      JX.History.push(path, id);

      self._cursor = (self._history.length - 1);
      self._responses[id] = null;
      self._current = id;

      new JX.Workflow(href, {__quicksand__: true})
        .setHandler(JX.bind(null, self._onresponse, id))
        .start();
    },


    /**
     * Receive a response from the server with page data e.g. content.
     *
     * Usually we'll dump it into the page, but if the user clicked very fast
     * it might already be out of date.
     */
    _onresponse: function(id, r) {
      var self = JX.Quicksand;

      // Before possibly updating the document, check if this response is still
      // relevant.

      // We don't save the new response if the user has already destroyed
      // the navigation. They can do this by pressing back, then clicking
      // another link before the response can load.
      if (self._responses[id] === false) {
        return;
      }

      // Otherwise, this data is still relevant (either data on the current
      // page, or data for a page that's still somewhere in history), so we
      // save it.
      var new_content = JX.$H(r.content).getFragment();
      self._content[id] = new_content;
      self._responses[id] = r;

      // If it's the current page, draw it into the browser. It might not be
      // the current page if the user already clicked another link.
      if (self._current == id) {
        self._draw(true);
      }
    },


    /**
     * Draw the current page.
     *
     * After a navigation event or the arrival of page content, we paint it
     * onto the page.
     */
    _draw: function(from_server) {
      var self = JX.Quicksand;

      if (self._onpage == self._current) {
        // Don't bother redrawing if we're already on the current page.
        return;
      }

      if (!self._responses[self._current]) {
        // If we don't have this page yet, we can't draw it. We'll draw it
        // when it arrives.
        return;
      }

      // Otherwise, we're going to replace the page content. First, save the
      // current page content. Modern computers have lots and lots of RAM, so
      // there is no way this could ever create a problem.
      var old = window.document.createDocumentFragment();
      while (self._frameNode.firstChild) {
        JX.DOM.appendContent(old, self._frameNode.firstChild);
      }
      self._content[self._onpage] = old;

      // Now, replace it with the new content.
      JX.DOM.setContent(self._frameNode, self._content[self._current]);
      // Let other things redraw, etc as necessary
      JX.Stratcom.invoke(
        'quicksand-redraw',
        null,
        {
          newResponse: self._responses[self._current],
          newResponseID: self._current,
          oldResponse: self._responses[self._onpage],
          oldResponseID: self._onpage,
          fromServer: from_server
        });
      self._onpage = self._current;

      // Scroll to the top of the page and trigger any layout adjustments.
      // TODO: Maybe store the scroll position?
      JX.DOM.scrollToPosition(0, 0);
      JX.Stratcom.invoke('resize');
    },


    /**
     * Handle navigation events.
     *
     * In general, we're going to pull the content out of our history and dump
     * it into the document.
     */
    _onchange: function(e) {
      var self = JX.Quicksand;

      var data = e.getData();
      data.state = data.state || null;

      // Check if we're going back to the first page we started Quicksand on.
      // We don't have a state value, but can look at the path.
      if (data.state === null) {
        if (JX.$U(window.location).getPath() == self._history[0].path) {
          data.state = 0;
        }
      }

      // Figure out where in history the user jumped to.
      if (data.state !== null) {
        self._current = data.state;

        // Point the cursor at the right place in history.
        for (var ii = 0; ii < self._history.length; ii++) {
          if (self._history[ii].id == self._current) {
            self._cursor = ii;
            break;
          }
        }

        // Redraw the page.
        self._draw(false);
      }
    },


    /**
     * Set a list of regular expressions which blacklist URIs as not navigable
     * via Quicksand.
     *
     * If a user clicks a link to one of these URIs, a normal page navigation
     * event will occur instead of a Quicksand navigation.
     *
     * @param list<string> List of regular expressions.
     * @return self
     */
    setURIPatternBlacklist: function(items) {
      var self = JX.Quicksand;

      var list = [];
      for (var ii = 0; ii < items.length; ii++) {
        list.push(new RegExp('^' + items[ii] + '$'));
      }

      self._uriPatternBlacklist = list;

      return self;
    },


    /**
     * Test if a @{class:JX.URI} is on the URI pattern blacklist.
     *
     * @param JX.URI URI to test.
     * @return bool True if the URI is on the blacklist.
     */
    _isURIOnBlacklist: function(uri) {
      var self = JX.Quicksand;
      var list = self._uriPatternBlacklist;

      var path = uri.getPath();
      for (var ii = 0; ii < list.length; ii++) {
        if (list[ii].test(path)) {
          return true;
        }
      }

      return false;
    }

  }

});
