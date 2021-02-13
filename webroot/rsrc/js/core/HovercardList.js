/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-vector
 *           javelin-request
 *           javelin-uri
 *           phui-hovercard
 * @provides phui-hovercard-list
 * @javelin
 */

JX.install('HovercardList', {

  construct: function() {
    this._cards = {};
    this._drawRequest = {};
  },

  members: {
    _cardNode: null,
    _rootNode: null,
    _cards: null,
    _drawRequest: null,
    _visibleCard: null,

    _fetchURI : '/search/hovercard/',

    getCard: function(spec) {
      var hovercard_key = this._newHovercardKey(spec);

      if (!(hovercard_key in this._cards)) {
        var card = new JX.Hovercard()
          .setHovercardKey(hovercard_key)
          .setObjectPHID(spec.objectPHID)
          .setContextPHID(spec.contextPHID || null);

        this._cards[hovercard_key] = card;
      }

      return this._cards[hovercard_key];
    },

    drawCard: function(card, node) {
      this._drawRequest = {
        card: card,
        node: node
      };

      if (card.getIsLoaded()) {
        return this._paintCard(card);
      }

      if (card.getIsLoading()) {
        return;
      }

      var hovercard_key = card.getHovercardKey();

      var request = {};
      request[hovercard_key] = this._newCardRequest(card);
      request = JX.JSON.stringify(request);

      var uri = JX.$U(this._fetchURI)
        .setQueryParam('cards', request);

      var onresponse = JX.bind(this, function(r) {
        var card = this._cards[hovercard_key];

        this._fillCard(card, r.cards[hovercard_key]);
        this._paintCard(card);
      });

      card.setIsLoading(true);

      new JX.Request(uri, onresponse)
        .send();
    },

    _newHovercardKey: function(spec) {
      var parts = [
        spec.objectPHID,
        spec.contextPHID
      ];

      return parts.join('/');
    },

    _newCardRequest: function(card) {
      return {
        objectPHID: card.getObjectPHID(),
        contextPHID: card.getContextPHID()
      };
    },

    _getCardNode: function() {
      if (!this._cardNode) {
        var attributes = {
          className: 'jx-hovercard-container'
        };

        this._cardNode = JX.$N('div', attributes);
      }

      return this._cardNode;
    },

    _fillCard: function(card, response) {
      card.setContent(response);
      card.setIsLoaded(true);
    },

    _paintCard: function(card) {
      var request = this._drawRequest;

      if (request.card !== card) {
        // This paint request is no longer the most recent paint request.
        return;
      }

      this.hideCard();

      this._rootNode = request.node;
      var root = this._rootNode;
      var node = this._getCardNode();

      JX.DOM.setContent(node, card.newContentNode());

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

      var alignment = 'north';

      // If the card is near the top of the window, show it beneath the
      // link we're hovering over instead.
      if ((y - margin) < s.y) {
        y = p.y + d.y + margin;
        alignment = 'south';
      }

      this._alignment = alignment;
      node.style.left = x + 'px';
      node.style.top  = y + 'px';

      this._visibleCard = card;
    },

    hideCard: function() {
      var node = this._getCardNode();
      JX.DOM.remove(node);

      this._rootNode = null;
      this._alignment = null;
      this._visibleCard = null;
    },

    onMouseMove: function(e) {
      if (!this._visibleCard) {
        return;
      }

      var root = this._rootNode;
      var node = this._getCardNode();
      var alignment = this._alignment;

      var mouse = JX.$V(e);
      var node_pos = JX.$V(node);
      var node_dim = JX.Vector.getDim(node);
      var root_pos = JX.$V(root);
      var root_dim = JX.Vector.getDim(root);

      var margin = 20;

      if (alignment === 'south') {
        // Cursor is below the node.
        if (mouse.y > node_pos.y + node_dim.y + margin) {
          this.hideCard();
        }

        // Cursor is above the root.
        if (mouse.y < root_pos.y - margin) {
          this.hideCard();
        }
      } else {
        // Cursor is above the node.
        if (mouse.y < node_pos.y - margin) {
          this.hideCard();
        }

        // Cursor is below the root.
        if (mouse.y > root_pos.y + root_dim.y + margin) {
          this.hideCard();
        }
      }

      // Cursor is too far to the left.
      if (mouse.x < Math.min(root_pos.x, node_pos.x) - margin) {
        this.hideCard();
      }

       // Cursor is too far to the right.
      if (mouse.x >
        Math.max(root_pos.x + root_dim.x, node_pos.x + node_dim.x) + margin) {
        this.hideCard();
      }
    }
  }
});
