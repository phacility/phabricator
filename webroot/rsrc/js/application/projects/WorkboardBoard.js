/**
 * @provides javelin-workboard-board
 * @requires javelin-install
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-draggable-list
 *           javelin-workboard-column
 *           javelin-workboard-header-template
 *           javelin-workboard-card-template
 *           javelin-workboard-order-template
 * @javelin
 */

JX.install('WorkboardBoard', {

  construct: function(controller, phid, root) {
    this._controller = controller;
    this._phid = phid;
    this._root = root;

    this._headers = {};
    this._cards = {};
    this._orders = {};

    this._buildColumns();
  },

  properties: {
    order: null,
    pointsEnabled: false
  },

  members: {
    _controller: null,
    _phid: null,
    _root: null,
    _columns: null,
    _headers: null,
    _cards: null,

    getRoot: function() {
      return this._root;
    },

    getColumns: function() {
      return this._columns;
    },

    getColumn: function(k) {
      return this._columns[k];
    },

    getPHID: function() {
      return this._phid;
    },

    getCardTemplate: function(phid) {
      if (!this._cards[phid]) {
        this._cards[phid] = new JX.WorkboardCardTemplate(phid);
      }

      return this._cards[phid];
    },

    getHeaderTemplate: function(header_key) {
      if (!this._headers[header_key]) {
        this._headers[header_key] = new JX.WorkboardHeaderTemplate(header_key);
      }

      return this._headers[header_key];
    },

    getOrderTemplate: function(order_key) {
      if (!this._orders[order_key]) {
        this._orders[order_key] = new JX.WorkboardOrderTemplate(order_key);
      }

      return this._orders[order_key];
    },

    getHeaderTemplatesForOrder: function(order) {
      var templates = [];

      for (var k in this._headers) {
        var header = this._headers[k];

        if (header.getOrder() !== order) {
          continue;
        }

        templates.push(header);
      }

      templates.sort(JX.bind(this, this._sortHeaderTemplates));

      return templates;
    },

    _sortHeaderTemplates: function(u, v) {
      return this.compareVectors(u.getVector(), v.getVector());
    },

    getController: function() {
      return this._controller;
    },

    compareVectors: function(u_vec, v_vec) {
      for (var ii = 0; ii < u_vec.length; ii++) {
        if (u_vec[ii] > v_vec[ii]) {
          return 1;
        }

        if (u_vec[ii] < v_vec[ii]) {
          return -1;
        }
      }

      return 0;
    },

    start: function() {
      this._setupDragHandlers();

      for (var k in this._columns) {
        this._columns[k].redraw();
      }
    },

    _buildColumns: function() {
      var nodes = JX.DOM.scry(this.getRoot(), 'ul', 'project-column');

      this._columns = {};
      for (var ii = 0; ii < nodes.length; ii++) {
        var node = nodes[ii];
        var data = JX.Stratcom.getData(node);
        var phid = data.columnPHID;

        this._columns[phid] = new JX.WorkboardColumn(this, phid, node);
      }
    },

    _setupDragHandlers: function() {
      var columns = this.getColumns();

      var order_template = this.getOrderTemplate(this.getOrder());
      var has_headers = order_template.getHasHeaders();
      var can_reorder = order_template.getCanReorder();

      var lists = [];
      for (var k in columns) {
        var column = columns[k];

        var list = new JX.DraggableList('draggable-card', column.getRoot())
          .setOuterContainer(this.getRoot())
          .setFindItemsHandler(JX.bind(column, column.getDropTargetNodes))
          .setCanDragX(true)
          .setHasInfiniteHeight(true)
          .setIsDropTargetHandler(JX.bind(column, column.setIsDropTarget));

        var default_handler = list.getGhostHandler();
        list.setGhostHandler(
          JX.bind(column, column.handleDragGhost, default_handler));

        // The "compare handler" locks cards into a specific position in the
        // column.
        list.setCompareHandler(JX.bind(column, column.compareHandler));

        // If the view has group headers, we lock cards into the right position
        // when moving them between columns, but not within a column.
        if (has_headers) {
          list.setCompareOnMove(true);
        }

        // If we can't reorder cards, we always lock them into their current
        // position.
        if (!can_reorder) {
          list.setCompareOnMove(true);
          list.setCompareOnReorder(true);
        }

        list.listen('didDrop', JX.bind(this, this._onmovecard, list));

        lists.push(list);
      }

      for (var ii = 0; ii < lists.length; ii++) {
        lists[ii].setGroup(lists);
      }
    },

    _findCardsInColumn: function(column_node) {
      return JX.DOM.scry(column_node, 'li', 'project-card');
    },

    _onmovecard: function(list, item, after_node, src_list) {
      list.lock();
      JX.DOM.alterClass(item, 'drag-sending', true);

      var src_phid = JX.Stratcom.getData(src_list.getRootNode()).columnPHID;
      var dst_phid = JX.Stratcom.getData(list.getRootNode()).columnPHID;

      var item_phid = JX.Stratcom.getData(item).objectPHID;
      var data = {
        objectPHID: item_phid,
        columnPHID: dst_phid,
        order: this.getOrder()
      };

      // We're going to send an "afterPHID" and a "beforePHID" if the card
      // was dropped immediately adjacent to another card. If a card was
      // dropped before or after a header, we don't send a PHID for the card
      // on the other side of the header.

      // If the view has headers, we always send the header the card was
      // dropped under.

      var after_data;
      var after_card = after_node;
      while (after_card) {
        after_data = JX.Stratcom.getData(after_card);
        if (after_data.objectPHID) {
          break;
        }
        if (after_data.headerKey) {
          break;
        }
        after_card = after_card.previousSibling;
      }

      if (after_data) {
        if (after_data.objectPHID) {
          data.afterPHID = after_data.objectPHID;
        }
      }

      var before_data;
      var before_card = item.nextSibling;
      while (before_card) {
        before_data = JX.Stratcom.getData(before_card);
        if (before_data.objectPHID) {
          break;
        }
        if (before_data.headerKey) {
          break;
        }
        before_card = before_card.nextSibling;
      }

      if (before_data) {
        if (before_data.objectPHID) {
          data.beforePHID = before_data.objectPHID;
        }
      }

      var header_data;
      var header_node = after_node;
      while (header_node) {
        header_data = JX.Stratcom.getData(header_node);
        if (header_data.headerKey) {
          break;
        }
        header_node = header_node.previousSibling;
      }

      if (header_data) {
        var header_key = header_data.headerKey;
        if (header_key) {
          var properties = this.getHeaderTemplate(header_key)
            .getEditProperties();
          data.header = JX.JSON.stringify(properties);
        }
      }

      var visible_phids = [];
      var column = this.getColumn(dst_phid);
      for (var object_phid in column.getCards()) {
        visible_phids.push(object_phid);
      }

      data.visiblePHIDs = visible_phids.join(',');

      var onupdate = JX.bind(
        this,
        this._oncardupdate,
        list,
        src_phid,
        dst_phid,
        data.afterPHID);

      new JX.Workflow(this.getController().getMoveURI(), data)
        .setHandler(onupdate)
        .start();
    },

    _oncardupdate: function(list, src_phid, dst_phid, after_phid, response) {
      var src_column = this.getColumn(src_phid);
      var dst_column = this.getColumn(dst_phid);

      var card = src_column.removeCard(response.objectPHID);
      dst_column.addCard(card, after_phid);

      src_column.markForRedraw();
      dst_column.markForRedraw();

      this.updateCard(response);

      list.unlock();
    },

    updateCard: function(response, options) {
      options = options || {};
      options.dirtyColumns = options.dirtyColumns || {};

      var columns = this.getColumns();

      var phid = response.objectPHID;

      for (var add_phid in response.columnMaps) {
        var target_column = this.getColumn(add_phid);

        if (!target_column) {
          // If the column isn't visible, don't try to add a card to it.
          continue;
        }

        target_column.newCard(phid);
      }

      var column_maps = response.columnMaps;
      var natural_column;
      for (var natural_phid in column_maps) {
        natural_column = this.getColumn(natural_phid);
        if (!natural_column) {
          // Our view of the board may be out of date, so we might get back
          // information about columns that aren't visible. Just ignore the
          // position information for any columns we aren't displaying on the
          // client.
          continue;
        }

        natural_column.setNaturalOrder(column_maps[natural_phid]);
      }

      for (var card_phid in response.cards) {
        var card_data = response.cards[card_phid];
        var card_template = this.getCardTemplate(card_phid);

        if (card_data.nodeHTMLTemplate) {
          card_template.setNodeHTMLTemplate(card_data.nodeHTMLTemplate);
        }

        var order;
        for (order in card_data.vectors) {
          card_template.setSortVector(order, card_data.vectors[order]);
        }

        for (order in card_data.headers) {
          card_template.setHeaderKey(order, card_data.headers[order]);
        }

        for (var key in card_data.properties) {
          card_template.setObjectProperty(key, card_data.properties[key]);
        }
      }

      var headers = response.headers;
      for (var jj = 0; jj < headers.length; jj++) {
        var header = headers[jj];

        this.getHeaderTemplate(header.key)
          .setOrder(header.order)
          .setNodeHTMLTemplate(header.template)
          .setVector(header.vector)
          .setEditProperties(header.editProperties);
      }

      for (var column_phid in columns) {
        var column = columns[column_phid];

        var cards = column.getCards();
        for (var object_phid in cards) {
          if (object_phid !== phid) {
            continue;
          }

          var card = cards[object_phid];
          card.redraw();

          column.markForRedraw();
        }
      }

      this._redrawColumns();
    },

    _redrawColumns: function() {
      var columns = this.getColumns();
      for (var k in columns) {
        if (columns[k].isMarkedForRedraw()) {
          columns[k].redraw();
        }
      }
    }

  }

});
