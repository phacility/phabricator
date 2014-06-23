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

  var itemSigil = 'dashboard-panel';

  function finditems(col) {
    return JX.DOM.scry(col, 'div', itemSigil);
  }

  function markcolempty(col, toggle) {
    JX.DOM.alterClass(col, 'dashboard-column-empty', toggle);
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

    var item_phid = JX.Stratcom.getData(item).objectPHID;
    var data = {
      objectPHID: item_phid,
      columnID: JX.Stratcom.getData(list.getRootNode()).columnID
    };

    var after_phid = null;
    var items = finditems(list.getRootNode());
    if (after) {
      after_phid = JX.Stratcom.getData(after).objectPHID;
      data.afterPHID = after_phid;
    }
    var ii;
    var ii_item;
    var ii_item_phid;
    var ii_prev_item_phid = null;
    var before_phid = null;
    for (ii = 0; ii < items.length; ii++) {
      ii_item = items[ii];
      ii_item_phid = JX.Stratcom.getData(ii_item).objectPHID;
      if (ii_item_phid == item_phid) {
        // skip the item we just dropped
        continue;
      }
      // note this handles when there is no after phid - we are at the top of
      // the list - quite nicely
      if (ii_prev_item_phid == after_phid) {
        before_phid = ii_item_phid;
        break;
      }
      ii_prev_item_phid = ii_item_phid;
    }
    if (before_phid) {
      data.beforePHID = before_phid;
    }

    var workflow = new JX.Workflow(config.moveURI, data)
      .setHandler(function(response) {
        onresponse(response, item, list);
      });

    workflow.start();
  }

  var lists = [];
  var ii;
  var cols = JX.DOM.scry(JX.$(config.dashboardID), 'div', 'dashboard-column');
  var col = null;

  for (ii = 0; ii < cols.length; ii++) {
    col = cols[ii];
    var list = new JX.DraggableList(itemSigil, col)
      .setFindItemsHandler(JX.bind(null, finditems, col));

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
