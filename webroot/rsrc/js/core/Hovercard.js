/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-vector
 *           javelin-request
 *           javelin-uri
 * @provides phui-hovercard
 * @javelin
 */

JX.install('Hovercard', {

  statics : {
    _node : null,
    _activeRoot : null,
    _visiblePHID : null,
    _alignment: null,

    fetchUrl : '/search/hovercard/',

    /**
     * Hovercard storage. {"PHID-XXXX-YYYY":"<...>", ...}
     */
    _cards : {},

    getAnchor : function() {
      return this._activeRoot;
    },

    getCard : function() {
      var self = JX.Hovercard;
      return self._node;
    },

    getAlignment: function() {
      var self = JX.Hovercard;
      return self._alignment;
    },

    show : function(root, phid) {
      var self = JX.Hovercard;

      if (root === this._activeRoot) {
        return;
      }

      self.hide();

      self._visiblePHID = phid;
      self._activeRoot = root;

      if (!(phid in self._cards)) {
        self._load([phid]);
      } else {
        self._drawCard(phid);
      }
    },

    _drawCard : function(phid) {
      var self = JX.Hovercard;
      // card is loading...
      if (self._cards[phid] === true) {
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
      var v = JX.Vector.getViewport();
      var s = JX.Vector.getScroll();

      // Move the tip so it's nicely aligned.
      var margin = 20;


      // Try to align the card directly above the link, with left borders
      // touching.
      var x = p.x;

      // If this would push us off the right side of the viewport, push things
      // back to the left.
      if ((x + n.x + margin) > (s.x + v.x)) {
        x = (s.x + v.x) - n.x - margin;
      }

      // Try to put the card above the link.
      var y = p.y - n.y - margin;
      self._alignment = 'north';

      // If the card is near the top of the window, show it beneath the
      // link we're hovering over instead.
      if ((y - margin) < s.y) {
        y = p.y + d.y + margin;
        self._alignment = 'south';
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

      var send = false;
      for (var ii = 0; ii < phids.length; ii++) {
        var phid = phids[ii];
        if (phid in self._cards) {
          continue;
        }
        self._cards[phid] = true; // means "loading"
        uri.setQueryParam('phids['+ii+']', phids[ii]);
        send = true;
      }

      if (!send) {
        // already loaded / loading everything!
        return;
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
