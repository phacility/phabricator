/**
 * @provides javelin-workboard-column
 * @requires javelin-install
 *           javelin-workboard-card
 * @javelin
 */

JX.install('WorkboardColumn', {

  construct: function(board, phid, root) {
    this._board = board;
    this._phid = phid;
    this._root = root;

    this._panel = JX.DOM.findAbove(root, 'div', 'workpanel');
    this._pointsNode = JX.DOM.find(this._panel, 'span', 'column-points');

    this._pointsContentNode = JX.DOM.find(
      this._panel,
      'span',
      'column-points-content');

    this._cards = {};
    this._naturalOrder = [];
  },

  members: {
    _phid: null,
    _root: null,
    _board: null,
    _cards: null,
    _naturalOrder: null,
    _panel: null,
    _pointsNode: null,
    _pointsContentNode: null,
    _dirty: true,

    getPHID: function() {
      return this._phid;
    },

    getRoot: function() {
      return this._root;
    },

    getCards: function() {
      return this._cards;
    },

    getCard: function(phid) {
      return this._cards[phid];
    },

    getBoard: function() {
      return this._board;
    },

    setNaturalOrder: function(order) {
      this._naturalOrder = order;
      return this;
    },

    getPointsNode: function() {
      return this._pointsNode;
    },

    getPointsContentNode: function() {
      return this._pointsContentNode;
    },

    getWorkpanelNode: function() {
      return this._panel;
    },

    newCard: function(phid) {
      var card = new JX.WorkboardCard(this, phid);

      this._cards[phid] = card;
      this._naturalOrder.push(phid);

      return card;
    },

    removeCard: function(phid) {
      var card = this._cards[phid];
      delete this._cards[phid];

      for (var ii = 0; ii < this._naturalOrder.length; ii++) {
        if (this._naturalOrder[ii] == phid) {
          this._naturalOrder.splice(ii, 1);
          break;
        }
      }

      return card;
    },

    addCard: function(card, after) {
      var phid = card.getPHID();

      card.setColumn(this);
      this._cards[phid] = card;

      var index = 0;

      if (after) {
        for (var ii = 0; ii < this._naturalOrder.length; ii++) {
          if (this._naturalOrder[ii] == after) {
            index = ii + 1;
            break;
          }
        }
      }

      if (index > this._naturalOrder.length) {
        this._naturalOrder.push(phid);
      } else {
        this._naturalOrder.splice(index, 0, phid);
      }

      return this;
    },

    getCardNodes: function() {
      var cards = this.getCards();

      var nodes = [];
      for (var k in cards) {
        nodes.push(cards[k].getNode());
      }

      return nodes;
    },

    getCardPHIDs: function() {
      return JX.keys(this.getCards());
    },

    getPointLimit: function() {
      return JX.Stratcom.getData(this.getRoot()).pointLimit;
    },

    markForRedraw: function() {
      this._dirty = true;
    },

    isMarkedForRedraw: function() {
      return this._dirty;
    },

    redraw: function() {
      var board = this.getBoard();
      var order = board.getOrder();

      var list;
      if (order == 'natural') {
        list = this._getCardsSortedNaturally();
      } else {
        list = this._getCardsSortedByKey(order);
      }

      var content = [];
      for (var ii = 0; ii < list.length; ii++) {
        var card = list[ii];

        var node = card.getNode();
        content.push(node);

      }

      JX.DOM.setContent(this.getRoot(), content);

      this._redrawFrame();

      this._dirty = false;
    },

    _getCardsSortedNaturally: function() {
      var list = [];

      for (var ii = 0; ii < this._naturalOrder.length; ii++) {
        var phid = this._naturalOrder[ii];
        list.push(this.getCard(phid));
      }

      return list;
    },

    _getCardsSortedByKey: function(order) {
      var cards = this.getCards();

      var list = [];
      for (var k in cards) {
        list.push(cards[k]);
      }

      list.sort(JX.bind(this, this._sortCards, order));

      return list;
    },

    _sortCards: function(order, u, v) {
      var ud = this.getBoard().getOrderVector(u.getPHID(), order);
      var vd = this.getBoard().getOrderVector(v.getPHID(), order);

      for (var ii = 0; ii < ud.length; ii++) {
        if (ud[ii] > vd[ii]) {
          return 1;
        }

        if (ud[ii] < vd[ii]) {
          return -1;
        }
      }

      return 0;
    },

    _redrawFrame: function() {
      var cards = this.getCards();
      var board = this.getBoard();

      var points = {};
      var count = 0;
      var decimal_places = 0;
      for (var phid in cards) {
        var card = cards[phid];

        var card_points;
        if (board.getPointsEnabled()) {
          card_points = card.getPoints();
        } else {
          card_points = 1;
        }

        if (card_points !== null) {
          var status = card.getStatus();
          if (!points[status]) {
            points[status] = 0;
          }
          points[status] += card_points;

          // Count the number of decimal places in the point value with the
          // most decimal digits. We'll use the same precision when rendering
          // the point sum. This avoids rounding errors and makes the display
          // a little more consistent.
          var parts = card_points.toString().split('.');
          if (parts[1]) {
            decimal_places = Math.max(decimal_places, parts[1].length);
          }
        }

        count++;
      }

      var total_points = 0;
      for (var k in points) {
        total_points += points[k];
      }
      total_points = total_points.toFixed(decimal_places);

      var limit = this.getPointLimit();

      var display_value;
      if (limit !== null && limit !== 0) {
        display_value = total_points + ' / ' + limit;
      } else {
        display_value = total_points;
      }

      if (board.getPointsEnabled()) {
        display_value = count + ' | ' + display_value;
      }

      var over_limit = ((limit !== null) && (total_points > limit));

      var content_node = this.getPointsContentNode();
      var points_node = this.getPointsNode();

      JX.DOM.setContent(content_node, display_value);

      var is_empty = !this.getCardPHIDs().length;
      var panel = JX.DOM.findAbove(this.getRoot(), 'div', 'workpanel');
      JX.DOM.alterClass(panel, 'project-panel-empty', is_empty);
      JX.DOM.alterClass(panel, 'project-panel-over-limit', over_limit);

      var color_map = {
        'phui-tag-disabled': (total_points === 0),
        'phui-tag-blue': (total_points > 0 && !over_limit),
        'phui-tag-red': (over_limit)
      };

      for (var c in color_map) {
        JX.DOM.alterClass(points_node, c, !!color_map[c]);
      }

      JX.DOM.show(points_node);
    }

  }

});
