/**
 * @provides javelin-workboard-controller
 * @requires javelin-install
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-drag-and-drop-file-upload
 *           javelin-workboard-board
 * @javelin
 */

JX.install('WorkboardController', {

  construct: function() {
    this._boards = {};
  },

  properties: {
    uploadURI: null,
    coverURI: null,
    moveURI: null,
    reloadURI: null,
    chunkThreshold: null
  },

  members: {
    _boards: null,

    _panOrigin: null,
    _panNode: null,
    _panX: null,

    start: function() {
      this._setupCoverImageHandlers();
      this._setupPanHandlers();
      this._setupEditHandlers();

      return this;
    },

    newBoard: function(phid, node) {
      var board = new JX.WorkboardBoard(this, phid, node);
      this._boards[phid] = board;
      return board;
    },

    _getBoard: function(board_phid) {
      return this._boards[board_phid];
    },

    _setupCoverImageHandlers: function() {
      if (!JX.PhabricatorDragAndDropFileUpload.isSupported()) {
        return;
      }

      var drop = new JX.PhabricatorDragAndDropFileUpload('project-card')
        .setURI(this.getUploadURI())
        .setChunkThreshold(this.getChunkThreshold());

      drop.listen('didBeginDrag', function(node) {
        JX.DOM.alterClass(node, 'phui-workcard-upload-target', true);
      });

      drop.listen('didEndDrag', function(node) {
        JX.DOM.alterClass(node, 'phui-workcard-upload-target', false);
      });

      drop.listen('didUpload', JX.bind(this, this._oncoverupload));

      drop.start();
    },

    _oncoverupload: function(file) {
      var node = file.getTargetNode();

      var board = this._getBoardFromNode(node);

      var column_node = JX.DOM.findAbove(node, 'ul', 'project-column');
      var column_phid = JX.Stratcom.getData(column_node).columnPHID;
      var column = board.getColumn(column_phid);

      var data = {
        boardPHID: board.getPHID(),
        objectPHID: JX.Stratcom.getData(node).objectPHID,
        filePHID: file.getPHID(),
        visiblePHIDs: column.getCardPHIDs()
      };

      new JX.Workflow(this.getCoverURI(), data)
        .setHandler(JX.bind(board, board.updateCard))
        .start();
    },

    _getBoardFromNode: function(node) {
      var board_node = JX.DOM.findAbove(node, 'div', 'jx-workboard');
      var board_phid = JX.Stratcom.getData(board_node).boardPHID;
      return this._getBoard(board_phid);
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

    _setupEditHandlers: function() {
      var onadd = JX.bind(this, this._onaddcard);
      var onedit = JX.bind(this, this._oneditcard);

      JX.Stratcom.listen('click', 'column-add-task', onadd);
      JX.Stratcom.listen('click', 'edit-project-card', onedit);
    },

    _onaddcard: function(e) {
      // We want the 'boards-dropdown-menu' behavior to see this event and
      // close the dropdown, but don't want to follow the link.
      e.prevent();

      var column_data = e.getNodeData('column-add-task');
      var column_phid = column_data.columnPHID;

      var board_phid = column_data.boardPHID;
      var board = this._getBoard(board_phid);
      var column = board.getColumn(column_phid);

      var request_data = {
        responseType: 'card',
        columnPHID: column.getPHID(),
        projects: column_data.projectPHID,
        visiblePHIDs: column.getCardPHIDs(),
        order: board.getOrder()
      };

      new JX.Workflow(column_data.createURI, request_data)
        .setHandler(JX.bind(board, board.updateCard))
        .start();
    },

    _oneditcard: function(e) {
      e.kill();

      var column_node = e.getNode('project-column');
      var column_phid = JX.Stratcom.getData(column_node).columnPHID;

      var board = this._getBoardFromNode(column_node);
      var column = board.getColumn(column_phid);

      var request_data = {
        responseType: 'card',
        columnPHID: column.getPHID(),
        visiblePHIDs: column.getCardPHIDs(),
        order: board.getOrder()
      };

      new JX.Workflow(e.getNode('tag:a').href, request_data)
        .setHandler(JX.bind(board, board.updateCard))
        .start();
    }

  }

});
