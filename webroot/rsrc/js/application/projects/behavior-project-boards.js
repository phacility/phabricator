/**
 * @provides javelin-behavior-project-boards
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-workboard-controller
 *           javelin-workboard-drop-effect
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
      .setReloadURI(config.reloadURI)
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
    board.getCardTemplate(k)
      .setNodeHTMLTemplate(templates[k]);
  }

  var ii;
  var jj;
  var effects;

  for (ii = 0; ii < config.columnTemplates.length; ii++) {
    var spec = config.columnTemplates[ii];

    var column = board.getColumn(spec.columnPHID);

    effects = [];
    for (jj = 0; jj < spec.effects.length; jj++) {
      effects.push(
        JX.WorkboardDropEffect.newFromDictionary(
          spec.effects[jj]));
    }
    column.setDropEffects(effects);

    for (jj = 0; jj < spec.cardPHIDs.length; jj++) {
      column.newCard(spec.cardPHIDs[jj]);
    }

    if (spec.triggerPreviewEffect) {
      column.setTriggerPreviewEffect(
        JX.WorkboardDropEffect.newFromDictionary(
          spec.triggerPreviewEffect));
    }
  }

  var order_maps = config.orderMaps;
  for (var object_phid in order_maps) {
    var order_card = board.getCardTemplate(object_phid);
    for (var order_key in order_maps[object_phid]) {
      order_card.setSortVector(order_key, order_maps[object_phid][order_key]);
    }
  }

  var property_maps = config.propertyMaps;
  for (var property_phid in property_maps) {
    board.getCardTemplate(property_phid)
      .setObjectProperties(property_maps[property_phid]);
  }

  var headers = config.headers;
  for (ii = 0; ii < headers.length; ii++) {
    var header = headers[ii];

    effects = [];
    for (jj = 0; jj < header.effects.length; jj++) {
      effects.push(
        JX.WorkboardDropEffect.newFromDictionary(
          header.effects[jj]));
    }

    board.getHeaderTemplate(header.key)
      .setOrder(header.order)
      .setNodeHTMLTemplate(header.template)
      .setVector(header.vector)
      .setEditProperties(header.editProperties)
      .setDropEffects(effects);
  }

  var orders = config.orders;
  for (ii = 0; ii < orders.length; ii++) {
    var order = orders[ii];

    board.getOrderTemplate(order.orderKey)
      .setHasHeaders(order.hasHeaders)
      .setCanReorder(order.canReorder);
  }

  var header_keys = config.headerKeys;
  for (var header_phid in header_keys) {
    board.getCardTemplate(header_phid)
      .setHeaderKey(config.order, header_keys[header_phid]);
  }

  board.start();

  // In Safari, we can only play sounds that we've already loaded, and we can
  // only load them in response to an explicit user interaction like a click.
  var sounds = config.preloadSounds;
  var listener = JX.Stratcom.listen('mousedown', null, function() {
    for (var ii = 0; ii < sounds.length; ii++) {
      JX.Sound.load(sounds[ii]);
    }

    // Remove this callback once it has run once.
    listener.remove();
  });

});
