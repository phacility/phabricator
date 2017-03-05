/**
 * @provides javelin-behavior-project-boards
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-workboard-controller
 */

JX.behavior('project-boards', function(config, statics) {

  function update_statics(update_config) {
    statics.boardID = update_config.boardID;
    statics.projectPHID = update_config.projectPHID;
    statics.order = update_config.order;
    statics.moveURI = update_config.moveURI;
  }

  function setup() {
    JX.Stratcom.listen('click', 'boards-dropdown-menu', function(e) {
      var data = e.getNodeData('boards-dropdown-menu');
      if (data.menu) {
        return;
      }

      e.kill();

      var list = JX.$H(data.items).getFragment().firstChild;

      var button = e.getNode('boards-dropdown-menu');
      data.menu = new JX.PHUIXDropdownMenu(button);
      data.menu.setContent(list);
      data.menu.open();
    });

    JX.Stratcom.listen(
      'quicksand-redraw',
      null,
      function (e) {
        var data = e.getData();
        if (!data.newResponse.boardConfig) {
          return;
        }
        var new_config;
        if (data.fromServer) {
          new_config = data.newResponse.boardConfig;
          statics.boardConfigCache[data.newResponseID] = new_config;
        } else {
          new_config = statics.boardConfigCache[data.newResponseID];
          statics.boardID = new_config.boardID;
        }
        update_statics(new_config);
      });

    return true;
  }

  if (!statics.setup) {
    update_statics(config);
    var current_page_id = JX.Quicksand.getCurrentPageID();
    statics.boardConfigCache = {};
    statics.boardConfigCache[current_page_id] = config;
    statics.setup = setup();
  }

  if (!statics.workboard) {
    statics.workboard = new JX.WorkboardController()
      .setUploadURI(config.uploadURI)
      .setCoverURI(config.coverURI)
      .setMoveURI(config.moveURI)
      .setChunkThreshold(config.chunkThreshold)
      .start();
  }

  var board_phid = config.projectPHID;
  var board_node = JX.$(config.boardID);

  var board = statics.workboard.newBoard(board_phid, board_node)
    .setOrder(config.order)
    .setPointsEnabled(config.pointsEnabled);

  var templates = config.templateMap;
  for (var k in templates) {
    board.setCardTemplate(k, templates[k]);
  }

  var column_maps = config.columnMaps;
  for (var column_phid in column_maps) {
    var column = board.getColumn(column_phid);
    var column_map = column_maps[column_phid];
    for (var ii = 0; ii < column_map.length; ii++) {
      column.newCard(column_map[ii]);
    }
  }

  var order_maps = config.orderMaps;
  for (var object_phid in order_maps) {
    board.setOrderMap(object_phid, order_maps[object_phid]);
  }

  var property_maps = config.propertyMaps;
  for (var property_phid in property_maps) {
    board.setObjectProperties(property_phid, property_maps[property_phid]);
  }

  board.start();

});
