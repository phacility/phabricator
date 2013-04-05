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
      if (phid != self._visiblePHID) {
        return;
      }
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
      // I'm just doing north alignment for now
      // TODO: Gracefully align to the side in edge cases
      // I know, hardcoded paddings...
      var x = parseInt(p.x - ((n.x - d.x) / 2)) + 20;
      var y = parseInt(p.y - n.y) - 20;

      // Why use 4? Shouldn't it be just 2?
      if (x < (n.x / 4)) {
        x += (n.x / 4);
      }

      if (y < n.y) {
        // Place it at the bottom
        y += n.y + d.y + 50;
      }

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

          if (self.getCard()) {
            self.hide();
          }

          self._drawCard(phid);
        }
      }).send();
    }
  }
});
