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

    this._cards = {};
    this._naturalOrder = [];
  },

  members: {
    _phid: null,
    _root: null,
    _board: null,
    _cards: null,
    _naturalOrder: null,

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

    redraw: function() {
      var order = this.getBoard().getOrder();

      var list;
      if (order == 'natural') {
        list = this._getCardsSortedNaturally();
      } else {
        list = this._getCardsSortedByKey(order);
      }

      var content = [];
      for (var ii = 0; ii < list.length; ii++) {
        var node = list[ii].getNode();
        content.push(node);
      }

      JX.DOM.setContent(this.getRoot(), content);
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
    }

  }

});
