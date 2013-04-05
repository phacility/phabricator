/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-vector
 *           javelin-request
 *           phabricator-busy
 * @provides phabricator-hovercard
 * @javelin
 */

JX.install('Hovercard', {

  statics : {
    _node : null,
    _activeRoot : null,

    _didScrape : false,

    fetchUrl : '/search/hovercard/retrieve/',

    /**
     * Hovercard storage. {"PHID-XXXX-YYYY":"<...>", ...}
     */
    cards : {},

    show : function(root, phid) {

      // Hovercards are all loaded by now, but when somebody previews a comment
      // for example it may not be loaded yet.
      if (!JX.Hovercard.cards[phid]) {
        JX.Hovercard.load([phid]);
      }

      var node = JX.$N('div',
        { className: 'jx-hovercard-container' },
        JX.Hovercard.cards[phid]);

      JX.Hovercard.hide();
      this._node = node;
      this._activeRoot = root;

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
      if (this._node) {
        JX.DOM.remove(this._node);
        this._node = null;
      }
      if (this._activeRoot) {
        this._activeRoot = null;
      }
    },

    /**
     * Pass it an array of phids to load them into storage
     *
     * @param list phids
     */
    load : function(phids) {
      var uri = JX.$U(JX.Hovercard.fetchUrl);

      for (var ii = 0; ii < phids.length; ii++) {
        uri.setQueryParam("phids["+ii+"]", phids[ii]);
      }

      new JX.Request(uri, function(r) {
        for (var phid in r.cards) {
          JX.Hovercard.cards[phid] = JX.$H(r.cards[phid]);
        }
      }).send();
    },

    // For later probably
    // Currently unused
    scrapeAndLoad: function() {
      if (!JX.Hovercard._didScrape) {
        // I assume links only for now
        var cards = JX.DOM.scry(document, 'a', 'hovercard');
        var phids = [];
        var data;
        for (var i = 0; i < cards.length; i++) {
          data = JX.Stratcom.getData(cards[i]);
          phids.push(data.hoverPHID);
        }

        JX.Hovercard.load(phids);

        JX.Hovercard._didScrape = true;
      }
    }
  }
});
