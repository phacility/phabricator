/**
 * @provides javelin-workboard-column
 * @requires javelin-install
 *           javelin-workboard-card
 *           javelin-workboard-header
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
    this._headers = {};
    this._objects = [];
    this._naturalOrder = [];
  },

  members: {
    _phid: null,
    _root: null,
    _board: null,
    _cards: null,
    _headers: null,
    _naturalOrder: null,
    _panel: null,
    _pointsNode: null,
    _pointsContentNode: null,
    _dirty: true,
    _objects: null,

    getPHID: function() {
      return this._phid;
    },

    getRoot: function() {
      return this._root;
    },

    getCards: function() {
      return this._cards;
    },

    _getObjects: function() {
      return this._objects;
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

    getDropTargetNodes: function() {
      var objects = this._getObjects();

      var nodes = [];
      for (var ii = 0; ii < objects.length; ii++) {
        var object = objects[ii];
        nodes.push(object.getNode());
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

    getHeader: function(key) {
      if (!this._headers[key]) {
        this._headers[key] = new JX.WorkboardHeader(this, key);
      }
      return this._headers[key];
    },

    handleDragGhost: function(default_handler, ghost, node) {
      // If the column has headers, don't let the user drag a card above
      // the topmost header: for example, you can't change a task to have
      // a priority higher than the highest possible priority.

      if (this._hasColumnHeaders()) {
        if (!node) {
          return false;
        }
      }

      return default_handler(ghost, node);
    },

    _hasColumnHeaders: function() {
      var board = this.getBoard();
      var order = board.getOrder();

      switch (order) {
        case 'natural':
          return false;
      }

      return true;
    },

    _getCardHeaderKey: function(card, order) {
      switch (order) {
        case 'priority':
          return 'priority(' + card.getPriority() + ')';
        default:
          return null;
      }
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

      var ii;
      var objects = [];

      var has_headers = this._hasColumnHeaders();
      var header_keys = [];
      var seen_headers = {};
      if (has_headers) {
        var header_templates = board.getHeaderTemplatesForOrder(order);
        for (var k in header_templates) {
          header_keys.push(header_templates[k].getHeaderKey());
        }
        header_keys.reverse();
      }

      for (ii = 0; ii < list.length; ii++) {
        var card = list[ii];

        // If a column has a "High" priority card and a "Low" priority card,
        // we need to add the "Normal" header in between them. This allows
        // you to change priority to "Normal" even if there are no "Normal"
        // cards in a column.

        if (has_headers) {
          var header_key = this._getCardHeaderKey(card, order);
          if (!seen_headers[header_key]) {
            while (header_keys.length) {
              var next = header_keys.pop();

              var header = this.getHeader(next);
              objects.push(header);
              seen_headers[header_key] = true;

              if (next === header_key) {
                break;
              }
            }
          }
        }

        objects.push(card);
      }

      this._objects = objects;

      var content = [];
      for (ii = 0; ii < this._objects.length; ii++) {
        var object = this._objects[ii];

        var node = object.getNode();
        content.push(node);
      }

      JX.DOM.setContent(this.getRoot(), content);

      this._redrawFrame();

      this._dirty = false;
    },

    compareHandler: function(src_list, src_node, dst_list, dst_node) {
      var board = this.getBoard();
      var order = board.getOrder();

      var u_vec = this._getNodeOrderVector(src_node, order);
      var v_vec = this._getNodeOrderVector(dst_node, order);

      return board.compareVectors(u_vec, v_vec);
    },

    _getNodeOrderVector: function(node, order) {
      var board = this.getBoard();
      var data = JX.Stratcom.getData(node);

      if (data.objectPHID) {
        return board.getOrderVector(data.objectPHID, order);
      }

      return board.getHeaderTemplate(data.headerKey).getVector();
    },

    setIsDropTarget: function(is_target) {
      var node = this.getWorkpanelNode();
      JX.DOM.alterClass(node, 'workboard-column-drop-target', is_target);
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
      var board = this.getBoard();
      var u_vec = board.getOrderVector(u.getPHID(), order);
      var v_vec = board.getOrderVector(v.getPHID(), order);

      return board.compareVectors(u_vec, v_vec);
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
