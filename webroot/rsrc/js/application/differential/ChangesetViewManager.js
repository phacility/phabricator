/**
 * @provides changeset-view-manager
 * @requires javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-install
 *           javelin-workflow
 *           javelin-router
 *           javelin-behavior-device
 *           javelin-vector
 */


JX.install('ChangesetViewManager', {

  construct : function(node) {
    this._node = node;

    var data = this._getNodeData();
    this._renderURI = data.renderURI;
    this._ref = data.ref;
    this._whitespace = data.whitespace;
    this._renderer = data.renderer;
    this._highlight = data.highlight;
  },

  members: {
    _node: null,
    _loaded: false,
    _sequence: 0,
    _stabilize: false,

    _renderURI: null,
    _ref: null,
    _whitespace: null,
    _renderer: null,
    _highlight: null,


    /**
     * Has the content of this changeset been loaded?
     *
     * This method returns `true` if a request has been fired, even if the
     * response has not returned yet.
     *
     * @return bool True if the content has been loaded.
     */
    isLoaded: function() {
      return this._loaded;
    },


    /**
     * Configure stabilization of the document position on content load.
     *
     * When we dump the changeset into the document, we can try to stabilize
     * the document scroll position so that the user doesn't feel like they
     * are jumping around as things load in. This is generally useful when
     * populating initial changes.
     *
     * However, if a user explicitly requests a content load by clicking a
     * "Load" link or using the dropdown menu, this stabilization generally
     * feels unnatural, so we don't use it in response to explicit user action.
     *
     * @param bool  True to stabilize the next content fill.
     * @return this
     */
    setStabilize: function(stabilize) {
      this._stabilize = stabilize;
      return this;
    },


    /**
     * Should this changeset load immediately when the page loads?
     *
     * Normally, changes load immediately, but if a diff or commit is very
     * large we stop doing this and have the user load files explicitly, or
     * choose to load everything.
     *
     * @return bool True if the changeset should load automatically when the
     *   page loads.
     */
    shouldAutoload: function() {
      return this._getNodeData().autoload;
    },


    /**
     * Load this changeset, if it isn't already loading.
     *
     * This fires a request to fill the content of this changeset, provided
     * there isn't already a request in flight. To force a reload, use
     * @{method:reload}.
     *
     * @return this
     */
    load: function() {
      if (this._loaded) {
        return this;
      }

      return this.reload();
    },


    /**
     * Reload the changeset content.
     *
     * This method always issues a request, even if the content is already
     * loading. To load conditionally, use @{method:load}.
     *
     * @return this
     */
    reload: function() {
      this._loaded = true;
      this._sequence++;

      var data = this._getNodeData();

      var params = {
        ref: this._ref,
        whitespace: this._whitespace,
        renderer: this._getRenderer()
      };

      var workflow = new JX.Workflow(this._renderURI, params)
        .setHandler(JX.bind(this, this._onresponse, this._sequence));

      var routable = workflow.getRoutable();

      routable
        .setPriority(500)
        .setType('content')
        .setKey(this._getRoutableKey());

      JX.Router.getInstance().queue(routable);

      JX.DOM.setContent(
        this._getContentFrame(),
        JX.$N(
          'div',
          {className: 'differential-loading'},
          'Loading...'));

      return this;
    },


    /**
     * Get the active @{class:JX.Routable} for this changeset.
     *
     * After issuing a request with @{method:load} or @{method:reload}, you
     * can adjust routable settings (like priority) by querying the routable
     * with this method. Note that there may not be a current routable.
     *
     * @return JX.Routable|null Active routable, if one exists.
     */
    getRoutable: function() {
      return JX.Router.getInstance().getRoutableByKey(this._getRoutableKey());
    },


    _getRenderer: function() {
      // TODO: This is a big pile of TODOs.

      // NOTE: If you load the page at one device resolution and then resize to
      // a different one we don't re-render the diffs, because it's a
      // complicated mess and you could lose inline comments, cursor positions,
      // etc.
      var renderer = (JX.Device.getDevice() == 'desktop') ? '2up' : '1up';

      // TODO: Once 1up works better, figure out when to show it.
      renderer = '2up';

      return renderer;
    },


    _getNodeData: function() {
      return JX.Stratcom.getData(this._node);
    },


    _onresponse: function(sequence, response) {
      if (sequence != this._sequence) {
        // If this isn't the most recent request, ignore it. This normally
        // means the user changed view settings between the time the page loaded
        // and the content filled.
        return;
      }

      // As we populate the changeset list, we try to hold the document scroll
      // position steady, so that, e.g., users who want to leave a comment on a
      // diff with a large number of changes don't constantly have the text
      // area scrolled off the bottom of the screen until the entire diff loads.
      //
      // There are two three major cases here:
      //
      //  - If we're near the top of the document, never scroll.
      //  - If we're near the bottom of the document, always scroll.
      //  - Otherwise, scroll if the changes were above the midline of the
      //    viewport.

      var target = this._node;

      var old_pos = JX.Vector.getScroll();
      var old_view = JX.Vector.getViewport();
      var old_dim = JX.Vector.getDocument();

      // Number of pixels away from the top or bottom of the document which
      // count as "nearby".
      var sticky = 480;

      var near_top = (old_pos.y <= sticky);
      var near_bot = ((old_pos.y + old_view.y) >= (old_dim.y - sticky));

      var target_pos = JX.Vector.getPos(target);
      var target_dim = JX.Vector.getDim(target);
      var target_mid = (target_pos.y + (target_dim.y / 2));

      var view_mid = (old_pos.y + (old_view.y / 2));
      var above_mid = (target_mid < view_mid);

      var frame = this._getContentFrame();
      JX.DOM.setContent(frame, JX.$H(response.changeset));

      if (this._stabilize) {
        if (!near_top) {
          if (near_bot || above_mid) {
            // Figure out how much taller the document got.
            var delta = (JX.Vector.getDocument().y - old_dim.y);
            window.scrollTo(old_pos.x, old_pos.y + delta);
          }
        }
        this._stabilize = false;
      }

      if (response.coverage) {
        for (var k in response.coverage) {
          try {
            JX.DOM.replace(JX.$(k), JX.$H(response.coverage[k]));
          } catch (ignored) {
            // Not terribly important.
          }
        }
      }
    },

    _getContentFrame: function() {
      return JX.DOM.find(this._node, 'div', 'changeset-view-content');
    },

    _getRoutableKey: function() {
      return 'changeset-view.' + this._ref + '.' + this._sequence;
    }

  },

  statics: {
    getForNode: function(node) {
      var data = JX.Stratcom.getData(node);
      if (!data.changesetViewManager) {
        data.changesetViewManager = new JX.ChangesetViewManager(node);
      }
      return data.changesetViewManager;
    }
  }
});
