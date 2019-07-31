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
    _dropPreviewNode: null,
    _dropPreviewListNode: null,
    _previewPHID: null,
    _hidePreivew: false,
    _previewPositionVector: null,
    _previewDimState: false,

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

      // TODO: This is temporary code to make it easier to debug this workflow
      // by pressing the "R" key.
      var on_reload = JX.bind(this, this._reloadCards);
      new JX.KeyboardShortcut('R', 'Reload Card State (Prototype)')
        .setHandler(on_reload)
        .register();

      var board_phid = this.getPHID();

      JX.Stratcom.listen('aphlict-server-message', null, function(e) {
        var message = e.getData();

        if (message.type != 'workboards') {
          return;
        }

        // Check if this update notification is about the currently visible
        // board. If it is, update the board state.

        var found_board = false;
        for (var ii = 0; ii < message.subscribers.length; ii++) {
          var subscriber_phid = message.subscribers[ii];
          if (subscriber_phid === board_phid) {
            found_board = true;
            break;
          }
        }

        if (found_board) {
          on_reload();
        }
      });

      JX.Stratcom.listen('aphlict-reconnect', null, function(e) {
        on_reload();
      });

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

      var on_over = JX.bind(this, this._showTriggerPreview);
      var on_out = JX.bind(this, this._hideTriggerPreview);
      JX.Stratcom.listen('mouseover', 'trigger-preview', on_over);
      JX.Stratcom.listen('mouseout', 'trigger-preview', on_out);

      var on_move = JX.bind(this, this._dimPreview);
      JX.Stratcom.listen('mousemove', null, on_move);
    },

    _dimPreview: function(e) {
      var p = this._previewPositionVector;
      if (!p) {
        return;
      }

      // When the mouse cursor gets near the drop preview element, fade it
      // out so you can see through it. We can't do this with ":hover" because
      // we disable cursor events.

      var cursor = JX.$V(e);
      var margin = 64;

      var near_x = (cursor.x > (p.x - margin));
      var near_y = (cursor.y > (p.y - margin));
      var should_dim = (near_x && near_y);

      this._setPreviewDimState(should_dim);
    },

    _setPreviewDimState: function(is_dim) {
      if (is_dim === this._previewDimState) {
        return;
      }

      this._previewDimState = is_dim;
      var node = this._getDropPreviewNode();
      JX.DOM.alterClass(node, 'workboard-drop-preview-fade', is_dim);
    },

    _showTriggerPreview: function(e) {
      if (this._disablePreview) {
        return;
      }

      var target = e.getTarget();
      var node = e.getNode('trigger-preview');

      if (target !== node) {
        return;
      }

      var phid = JX.Stratcom.getData(node).columnPHID;
      var column = this._columns[phid];

      // Bail out if we don't know anything about this column.
      if (!column) {
        return;
      }

      if (phid === this._previewPHID) {
        return;
      }

      this._previewPHID = phid;

      var effects = column.getDropEffects();

      var triggers = [];
      for (var ii = 0; ii < effects.length; ii++) {
        if (effects[ii].getIsTriggerEffect()) {
          triggers.push(effects[ii]);
        }
      }

      if (triggers.length) {
        var header = column.getTriggerPreviewEffect();
        triggers = [header].concat(triggers);
      }

      this._showEffects(triggers);
    },

    _hideTriggerPreview: function(e) {
      if (this._disablePreview) {
        return;
      }

      var target = e.getTarget();

      if (target !== e.getNode('trigger-preview')) {
        return;
      }

      this._removeTriggerPreview();
    },

    _removeTriggerPreview: function() {
      this._showEffects([]);
      this._previewPHID = null;
    },

    _beginDrag: function() {
      this._disablePreview = true;
      this._showEffects([]);
    },

    _endDrag: function() {
      this._disablePreview = false;
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

        list.setTargetChangeHandler(JX.bind(this, this._didChangeDropTarget));

        list.listen('didDrop', JX.bind(this, this._onmovecard, list));

        list.listen('didBeginDrag', JX.bind(this, this._beginDrag));
        list.listen('didEndDrag', JX.bind(this, this._endDrag));

        lists.push(list);
      }

      for (var ii = 0; ii < lists.length; ii++) {
        lists[ii].setGroup(lists);
      }
    },

    _didChangeDropTarget: function(src_list, src_node, dst_list, dst_node) {
      if (!dst_list) {
        // The card is being dragged into a dead area, like the left menu.
        this._showEffects([]);
        return;
      }

      if (dst_node === false) {
        // The card is being dragged over itself, so dropping it won't
        // affect anything.
        this._showEffects([]);
        return;
      }

      var src_phid = JX.Stratcom.getData(src_list.getRootNode()).columnPHID;
      var dst_phid = JX.Stratcom.getData(dst_list.getRootNode()).columnPHID;

      var src_column = this.getColumn(src_phid);
      var dst_column = this.getColumn(dst_phid);

      var effects = [];
      if (src_column !== dst_column) {
        effects = effects.concat(dst_column.getDropEffects());
      }

      var context = this._getDropContext(dst_node);
      if (context.headerKey) {
        var header = this.getHeaderTemplate(context.headerKey);
        effects = effects.concat(header.getDropEffects());
      }

      var card_phid = JX.Stratcom.getData(src_node).objectPHID;
      var card = src_column.getCard(card_phid);

      var visible = [];
      for (var ii = 0; ii < effects.length; ii++) {
        if (effects[ii].isEffectVisibleForCard(card)) {
          visible.push(effects[ii]);
        }
      }
      effects = visible;

      this._showEffects(effects);
    },

    _showEffects: function(effects) {
      var node = this._getDropPreviewNode();

      if (!effects.length) {
        JX.DOM.remove(node);
        this._previewPositionVector = null;
        return;
      }

      var items = [];
      for (var ii = 0; ii < effects.length; ii++) {
        var effect = effects[ii];
        items.push(effect.newNode());
      }

      JX.DOM.setContent(this._getDropPreviewListNode(), items);
      document.body.appendChild(node);

      // Undim the drop preview element if it was previously dimmed.
      this._setPreviewDimState(false);
      this._previewPositionVector = JX.$V(node);
    },

    _getDropPreviewNode: function() {
      if (!this._dropPreviewNode) {
        var attributes = {
          className: 'workboard-drop-preview'
        };

        var content = [
          this._getDropPreviewListNode()
        ];

        this._dropPreviewNode = JX.$N('div', attributes, content);
      }

      return this._dropPreviewNode;
    },

    _getDropPreviewListNode: function() {
      if (!this._dropPreviewListNode) {
        var attributes = {};
        this._dropPreviewListNode = JX.$N('ul', attributes);
      }

      return this._dropPreviewListNode;
    },

    _findCardsInColumn: function(column_node) {
      return JX.DOM.scry(column_node, 'li', 'project-card');
    },

    _getDropContext: function(after_node, item) {
      var header_key;
      var after_phids = [];
      var before_phids = [];

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

        if (after_data.headerKey) {
          break;
        }

        if (after_data.objectPHID) {
          after_phids.push(after_data.objectPHID);
        }

        after_card = after_card.previousSibling;
      }

      if (item) {
        var before_data;
        var before_card = item.nextSibling;
        while (before_card) {
          before_data = JX.Stratcom.getData(before_card);

          if (before_data.headerKey) {
            break;
          }

          if (before_data.objectPHID) {
            before_phids.push(before_data.objectPHID);
          }

          before_card = before_card.nextSibling;
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
        header_key = header_data.headerKey;
      }

      return {
        headerKey: header_key,
        afterPHIDs: after_phids,
        beforePHIDs: before_phids
      };
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

      var context = this._getDropContext(after_node, item);
      data.afterPHIDs = context.afterPHIDs.join(',');
      data.beforePHIDs = context.beforePHIDs.join(',');

      if (context.headerKey) {
        var properties = this.getHeaderTemplate(context.headerKey)
          .getEditProperties();
        data.header = JX.JSON.stringify(properties);
      }

      var visible_phids = [];
      var column = this.getColumn(dst_phid);
      for (var object_phid in column.getCards()) {
        visible_phids.push(object_phid);
      }

      data.visiblePHIDs = visible_phids.join(',');

      // If the user cancels the workflow (for example, by hitting an MFA
      // prompt that they click "Cancel" on), put the card back where it was
      // and reset the UI state.
      var on_revert = JX.bind(
        this,
        this._revertCard,
        list,
        item,
        src_phid,
        dst_phid);

      var after_phid = null;
      if (data.afterPHIDs.length) {
        after_phid = data.afterPHIDs[0];
      }

      var onupdate = JX.bind(
        this,
        this._oncardupdate,
        list,
        src_phid,
        dst_phid,
        after_phid);

      new JX.Workflow(this.getController().getMoveURI(), data)
        .setHandler(onupdate)
        .setCloseHandler(on_revert)
        .start();
    },

    _revertCard: function(list, item, src_phid, dst_phid) {
      JX.DOM.alterClass(item, 'drag-sending', false);

      var src_column = this.getColumn(src_phid);
      var dst_column = this.getColumn(dst_phid);

      src_column.markForRedraw();
      dst_column.markForRedraw();
      this._redrawColumns();

      list.unlock();
    },

    _oncardupdate: function(list, src_phid, dst_phid, after_phid, response) {
      this.updateCard(response);

      var sounds = response.sounds || [];
      for (var ii = 0; ii < sounds.length; ii++) {
        JX.Sound.queue(sounds[ii]);
      }

      list.unlock();
    },

    updateCard: function(response) {
      var columns = this.getColumns();
      var column_phid;
      var card_phid;
      var card_data;

      // The server may send us a full or partial update for a card. If we've
      // received a full update, we're going to redraw the entire card and may
      // need to change which columns it appears in.

      // For a partial update, we've just received supplemental sorting or
      // property information and do not need to perform a full redraw.

      // When we reload card state, edit a card, or move a card, we get a full
      // update for the card.

      // Ween we move a card in a column, we may get a partial update for other
      // visible cards in the column.


      // Figure out which columns each card now appears in. For cards that
      // have received a full update, we'll use this map to move them into
      // the correct columns.
      var update_map = {};
      for (column_phid in response.columnMaps) {
        var target_column = this.getColumn(column_phid);

        if (!target_column) {
          // If the column isn't visible, don't try to add a card to it.
          continue;
        }

        var column_map = response.columnMaps[column_phid];

        for (var ii = 0; ii < column_map.length; ii++) {
          card_phid = column_map[ii];
          if (!update_map[card_phid]) {
            update_map[card_phid] = {};
          }
          update_map[card_phid][column_phid] = true;
        }
      }

      // Process card removals. These are cases where the client still sees
      // a particular card on a board but it has been removed on the server.
      for (card_phid in response.cards) {
        card_data = response.cards[card_phid];

        if (!card_data.remove) {
          continue;
        }

        for (column_phid in columns) {
          var column = columns[column_phid];

          var card = column.getCard(card_phid);
          if (card) {
            column.removeCard(card_phid);
            column.markForRedraw();
          }
        }
      }

      // Process partial updates for cards. This is supplemental data which
      // we can just merge in without any special handling.
      for (card_phid in response.cards) {
        card_data = response.cards[card_phid];

        if (card_data.remove) {
          continue;
        }

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

      // Process full updates for cards which we have a full update for. This
      // may involve moving them between columns.
      for (card_phid in response.cards) {
        card_data = response.cards[card_phid];

        if (!card_data.update) {
          continue;
        }

        for (column_phid in columns) {
          var column = columns[column_phid];
          var card = column.getCard(card_phid);

          if (card) {
            card.redraw();
            column.markForRedraw();
          }

          // Compare the server state to the client state, and add or remove
          // cards on the client as necessary to synchronize them.

          if (update_map[card_phid] && update_map[card_phid][column_phid]) {
            if (!card) {
              column.newCard(card_phid);
              column.markForRedraw();
            }
          } else {
            if (card) {
              column.removeCard(card_phid);
              column.markForRedraw();
            }
          }
        }
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

      var headers = response.headers;
      for (var jj = 0; jj < headers.length; jj++) {
        var header = headers[jj];

        this.getHeaderTemplate(header.key)
          .setOrder(header.order)
          .setNodeHTMLTemplate(header.template)
          .setVector(header.vector)
          .setEditProperties(header.editProperties);
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
    },

    _reloadCards: function() {
      var state = {};

      var columns = this.getColumns();
      for (var column_phid in columns) {
        var cards = columns[column_phid].getCards();
        for (var card_phid in cards) {
          state[card_phid] = this.getCardTemplate(card_phid).getVersion();
        }
      }

      var data = {
        state: JX.JSON.stringify(state),
        order: this.getOrder()
      };

      var on_reload = JX.bind(this, this._onReloadResponse);

      new JX.Request(this.getController().getReloadURI(), on_reload)
        .setData(data)
        .send();
    },

    _onReloadResponse: function(response) {
      this.updateCard(response);
    }

  }

});
