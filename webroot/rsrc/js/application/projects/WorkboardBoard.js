/**
 * @provides javelin-workboard-board
 * @requires javelin-install
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-draggable-list
 *           javelin-workboard-column
 * @javelin
 */

JX.install('WorkboardBoard', {

  construct: function(controller, phid, root) {
    this._controller = controller;
    this._phid = phid;
    this._root = root;

    this._templates = {};
    this._orderMaps = {};
    this._propertiesMap = {};
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
    _templates: null,
    _orderMaps: null,
    _propertiesMap: null,

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

    setCardTemplate: function(phid, template)  {
      this._templates[phid] = template;
      return this;
    },

    setObjectProperties: function(phid, properties) {
      this._propertiesMap[phid] = properties;
      return this;
    },

    getObjectProperties: function(phid) {
      return this._propertiesMap[phid];
    },

    getCardTemplate: function(phid) {
      return this._templates[phid];
    },

    getController: function() {
      return this._controller;
    },

    setOrderMap: function(phid, map) {
      this._orderMaps[phid] = map;
      return this;
    },

    getOrderVector: function(phid, key) {
      return this._orderMaps[phid][key];
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

      var lists = [];
      for (var k in columns) {
        var column = columns[k];

        var list = new JX.DraggableList('project-card', column.getRoot())
          .setOuterContainer(this.getRoot())
          .setFindItemsHandler(JX.bind(column, column.getCardNodes))
          .setCanDragX(true)
          .setHasInfiniteHeight(true);

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

      if (after_node) {
        data.afterPHID = JX.Stratcom.getData(after_node).objectPHID;
      }

      var before_node = item.nextSibling;
      if (before_node) {
        var before_phid = JX.Stratcom.getData(before_node).objectPHID;
        if (before_phid) {
          data.beforePHID = before_phid;
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

      if (!this._templates[phid]) {
        for (var add_phid in response.columnMaps) {
          this.getColumn(add_phid).newCard(phid);
        }
      }

      this.setCardTemplate(phid, response.cardHTML);

      var order_maps = response.orderMaps;
      for (var order_phid in order_maps) {
        this.setOrderMap(order_phid, order_maps[order_phid]);
      }

      var column_maps = response.columnMaps;
      for (var natural_phid in column_maps) {
        this.getColumn(natural_phid).setNaturalOrder(column_maps[natural_phid]);
      }

      var property_maps = response.propertyMaps;
      for (var property_phid in property_maps) {
        this.setObjectProperties(property_phid, property_maps[property_phid]);
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
