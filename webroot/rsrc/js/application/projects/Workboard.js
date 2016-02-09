/**
 * @provides javelin-workboard
 * @requires javelin-install
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-draggable-list
 *           phabricator-drag-and-drop-file-upload
 * @javelin
 */

JX.install('Workboard', {

  construct: function(config) {
    this._config = config;

    this._boardNodes = {};

    this._setupCoverImageHandlers();
    this._setupPanHandlers();
  },

  members: {
    _config: null,
    _boardNodes: null,
    _currentBoard: null,

    _panOrigin: null,
    _panNode: null,
    _panX: null,

    addBoard: function(board_phid, board_node) {
      this._currentBoard = board_phid;

      if (!this._boardNodes[board_phid]) {
        this._boardNodes[board_phid] = board_node;
        this._setupDragHandlers(board_node);
      }
    },

    _getConfig: function() {
      return this._config;
    },

    _setupCoverImageHandlers: function() {
      if (!JX.PhabricatorDragAndDropFileUpload.isSupported()) {
        return;
      }

      var config = this._getConfig();

      var drop = new JX.PhabricatorDragAndDropFileUpload('project-card')
        .setURI(config.uploadURI)
        .setChunkThreshold(config.chunkThreshold);

      drop.listen('didBeginDrag', function(node) {
        JX.DOM.alterClass(node, 'phui-workcard-upload-target', true);
      });

      drop.listen('didEndDrag', function(node) {
        JX.DOM.alterClass(node, 'phui-workcard-upload-target', false);
      });

      drop.listen('didUpload', function(file) {
        var node = file.getTargetNode();

        var board = JX.DOM.findAbove(node, 'div', 'jx-workboard');

        var data = {
          boardPHID: JX.Stratcom.getData(board).boardPHID,
          objectPHID: JX.Stratcom.getData(node).objectPHID,
          filePHID: file.getPHID()
        };

        new JX.Workflow(config.coverURI, data)
          .setHandler(function(r) {
            JX.DOM.replace(node, JX.$H(r.task));
          })
          .start();
      });

      drop.start();
    },

    _setupPanHandlers: function() {
      var mousedown = JX.bind(this, this._onpanmousedown);
      var mousemove = JX.bind(this, this._onpanmousemove);
      var mouseup = JX.bind(this, this._onpanmouseup);

      JX.Stratcom.listen('mousedown', 'workboard-shadow', mousedown);
      JX.Stratcom.listen('mousemove', null, mousemove);
      JX.Stratcom.listen('mouseup', null, mouseup);
    },

    _onpanmousedown: function(e) {
      if (!JX.Device.isDesktop()) {
        return;
      }

      if (e.getNode('workpanel')) {
        return;
      }

      if (JX.Stratcom.pass()) {
        return;
      }

      e.kill();

      this._panOrigin = JX.$V(e);
      this._panNode = e.getNode('workboard-shadow');
      this._panX = this._panNode.scrollLeft;
    },

    _onpanmousemove: function(e) {
      if (!this._panOrigin) {
        return;
      }

      var cursor = JX.$V(e);
      this._panNode.scrollLeft = this._panX + (this._panOrigin.x - cursor.x);
    },

    _onpanmouseup: function() {
      this._panOrigin = null;
    },


    _setupDragHandlers: function(board_node) {
      var columns = this._findBoardColumns(board_node);
      var column;
      var ii;
      var lists = [];

      for (ii = 0; ii < columns.length; ii++) {
        column = columns[ii];

        var list = new JX.DraggableList('project-card', column)
          .setOuterContainer(board_node)
          .setFindItemsHandler(JX.bind(this, this._findCardsInColumn, column))
          .setCanDragX(true)
          .setHasInfiniteHeight(true);

        // TODO: Restore these behaviors.
        // list.listen('didSend', JX.bind(list, onupdate, cols[ii]));
        // list.listen('didReceive', JX.bind(list, onupdate, cols[ii]));
        // onupdate(cols[ii]);

        list.listen('didDrop', JX.bind(this, this._onmovecard, list));

        lists.push(list);
      }

      for (ii = 0; ii < lists.length; ii++) {
        lists[ii].setGroup(lists);
      }
    },

    _findBoardColumns: function(board_node) {
      return JX.DOM.scry(board_node, 'ul', 'project-column');
    },

    _findCardsInColumn: function(column_node) {
      return JX.DOM.scry(column_node, 'li', 'project-card');
    },

    _onmovecard: function(list, item, after_node) {
      list.lock();
      JX.DOM.alterClass(item, 'drag-sending', true);

      var item_phid = JX.Stratcom.getData(item).objectPHID;
      var data = {
        objectPHID: item_phid,
        columnPHID: JX.Stratcom.getData(list.getRootNode()).columnPHID
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

      // TODO: This should be managed per-board.
      var config = this._getConfig();
      data.order = config.order;

      new JX.Workflow(config.moveURI, data)
        .setHandler(JX.bind(this, this._oncardupdate, item, list))
        .start();
    },

    _oncardupdate: function(item, list, response) {
      list.unlock();
      JX.DOM.alterClass(item, 'drag-sending', false);
      JX.DOM.replace(item, JX.$H(response.task));
    }

  }

});
