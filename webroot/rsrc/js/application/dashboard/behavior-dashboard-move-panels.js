/**
 * @provides javelin-behavior-dashboard-move-panels
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-draggable-list
 */

JX.behavior('dashboard-move-panels', function(config) {

  var itemSigil = 'panel-movable';

  function finditems(col) {
    return JX.DOM.scry(col, 'div', itemSigil);
  }

  function markcolempty(col, toggle) {
    JX.DOM.alterClass(col.parentNode, 'dashboard-column-empty', toggle);
  }

  function onupdate(col) {
    markcolempty(col, !this.findItems().length);
  }

  function onresponse(response, item, list) {
    list.unlock();
    JX.DOM.alterClass(item, 'drag-sending', false);
  }

  function ondrop(list, item, after) {
    list.lock();
    JX.DOM.alterClass(item, 'drag-sending', true);

    var data = {
      panelKey: JX.Stratcom.getData(item).panelKey,
      columnKey: JX.Stratcom.getData(list.getRootNode()).columnKey
    };

    if (after) {
      var after_data = JX.Stratcom.getData(after);
      if (after_data.panelKey) {
        data.afterKey = after_data.panelKey;
      }
    }

    var workflow = new JX.Workflow(config.moveURI, data)
      .setHandler(function(response) {
        onresponse(response, item, list);
      });

    workflow.start();
  }

  var dashboard_node = JX.$(config.dashboardNodeID);

  var lists = [];
  var cols = JX.DOM.scry(dashboard_node, 'div', 'dashboard-column');

  var ii;
  for (ii = 0; ii < cols.length; ii++) {
    var col = cols[ii];
    var list = new JX.DraggableList(itemSigil, col)
      .setFindItemsHandler(JX.bind(null, finditems, col))
      .setCanDragX(true);

    list.listen('didSend', JX.bind(list, onupdate, col));
    list.listen('didReceive', JX.bind(list, onupdate, col));
    list.listen('didDrop', JX.bind(null, ondrop, list));

    lists.push(list);

    markcolempty(col, finditems(col).length === 0);
  }

  for (ii = 0; ii < lists.length; ii++) {
    lists[ii].setGroup(lists);
  }

});
