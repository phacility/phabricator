/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-vector
 *           javelin-request
 *           javelin-uri
 * @provides phabricator-hovercard
 * @javelin
 */

JX.install('Hovercard', {

  statics : {
    _node : null,
    _activeRoot : null,
    _visiblePHID : null,

    fetchUrl : '/search/hovercard/retrieve/',

    /**
     * Hovercard storage. {"PHID-XXXX-YYYY":"<...>", ...}
     */
    _cards : {},

    getAnchor : function() {
      return this._activeRoot;
    },

    getCard : function() {
      return this._node;
    },

    show : function(root, phid) {
      var self = JX.Hovercard;
      self.hide();

      self._visiblePHID = phid;
      self._activeRoot = root;

      // Hovercards are all loaded by now, but when somebody previews a comment
      // for example it may not be loaded yet.
      if (!(phid in self._cards)) {
        self._load([phid]);
      } else {
        self._drawCard(phid);
      }
    },

    _drawCard : function(phid) {
      var self = JX.Hovercard;
      // Already displaying
      if (self.getCard() && phid == self._visiblePHID) {
        return;
      }
      // Not the current requested card
      if (phid != self._visiblePHID) {
        return;
      }
      // Not loaded
      if (!(phid in self._cards)) {
        return;
      }

      var root = self._activeRoot;
      var node = JX.$N('div',
        { className: 'jx-hovercard-container' },
        JX.$H(self._cards[phid]));

      self._node = node;

      // Append the card to the document, but offscreen, so we can measure it.
      node.style.left = '-10000px';
      document.body.appendChild(node);

      // Retrieve size from child (wrapper), since node gives wrong dimensions?
      var child = node.firstChild;

      var p = JX.$V(root);
      var d = JX.Vector.getDim(root);
      var n = JX.Vector.getDim(child);

      // Move the tip so it's nicely aligned.
      // I'm just doing north/south alignment for now
      // TODO: Fix southern graceful align
      var margin = 20;
      // We can't shift left by ~$margin or more here due to Pholio, Phriction
      var x = parseInt(p.x) - margin / 2;
      var y = parseInt(p.y - n.y) - margin;

      // If more in the center, we can safely center
      if (x > (n.x / 2) + margin) {
        x = parseInt(p.x - (n.x / 2) + d.x);
      }

      // Temporarily disabled, since it gives weird results (you can only see
      // a hovercard once, as soon as it's hidden, it cannot be shown again)
      // if (y < n.y) {
      //   // Place it southern, since northern is not enough space
      //   y = p.y + d.y + margin;
      // }

      node.style.left = x + 'px';
      node.style.top  = y + 'px';
    },

    hide : function() {
      var self = JX.Hovercard;
      self._visiblePHID = null;
      self._activeRoot = null;
      if (self._node) {
        JX.DOM.remove(self._node);
        self._node = null;
      }
    },

    /**
     * Pass it an array of phids to load them into storage
     *
     * @param list phids
     */
    _load : function(phids) {
      var self = JX.Hovercard;
      var uri = JX.$U(self.fetchUrl);

      for (var ii = 0; ii < phids.length; ii++) {
        uri.setQueryParam("phids["+ii+"]", phids[ii]);
      }

      new JX.Request(uri, function(r) {
        for (var phid in r.cards) {
          self._cards[phid] = r.cards[phid];

          // Don't draw if the user is faster than the browser
          // Only draw if the user is still requesting the original card
          if (self.getCard() && phid != self._visiblePHID) {
            continue;
          }

          self._drawCard(phid);
        }
      }).send();
    }
  }
});
