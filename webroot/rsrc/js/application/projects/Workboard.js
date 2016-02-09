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
      this._boardNodes[board_phid] = board_node;
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
    }

  }

});
