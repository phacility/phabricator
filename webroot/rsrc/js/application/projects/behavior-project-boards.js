/**
 * @provides javelin-behavior-project-boards
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-draggable-list
 */

JX.behavior('project-boards', function(config) {

  function finditems(col) {
    return JX.DOM.scry(col, 'li', 'project-card');
  }

  function onupdate(node) {
    JX.DOM.alterClass(node, 'project-column-empty', !this.findItems().length);
  }

  function onresponse(response, item, list) {
    list.unlock();
    JX.DOM.alterClass(item, 'drag-sending', false);
    JX.DOM.replace(item, JX.$H(response.task));
  }

  function colsort(u, v) {
    var ud = JX.Stratcom.getData(u).sort || [];
    var vd = JX.Stratcom.getData(v).sort || [];

    for (var ii = 0; ii < ud.length; ii++) {

      if (parseInt(ud[ii]) < parseInt(vd[ii])) {
        return 1;
      }
      if (parseInt(ud[ii]) > parseInt(vd[ii])) {
        return -1;
      }
    }

    return 0;
  }

  function ondrop(list, item, after) {
    list.lock();
    JX.DOM.alterClass(item, 'drag-sending', true);

    var item_phid = JX.Stratcom.getData(item).objectPHID;
    var data = {
      objectPHID: item_phid,
      columnPHID: JX.Stratcom.getData(list.getRootNode()).columnPHID
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
  var cols = JX.DOM.scry(JX.$(config.boardID), 'ul', 'project-column');

  for (ii = 0; ii < cols.length; ii++) {
    var list = new JX.DraggableList('project-card', cols[ii])
      .setFindItemsHandler(JX.bind(null, finditems, cols[ii]));

    list.listen('didSend', JX.bind(list, onupdate, cols[ii]));
    list.listen('didReceive', JX.bind(list, onupdate, cols[ii]));

    list.listen('didDrop', JX.bind(null, ondrop, list));

    lists.push(list);
  }

  for (ii = 0; ii < lists.length; ii++) {
    lists[ii].setGroup(lists);
  }

  var onedit = function(column, r) {
    var new_card = JX.$H(r.tasks).getNode();
    var new_data = JX.Stratcom.getData(new_card);
    var items = finditems(column);
    var edited = false;

    for (var ii = 0; ii < items.length; ii++) {
      var item = items[ii];

      var data = JX.Stratcom.getData(item);
      var phid = data.objectPHID;

      if (phid == new_data.objectPHID) {
        items[ii] = new_card;
        data = new_data;
        edited = true;
      }

      data.sort = r.data.sortMap[data.objectPHID] || data.sort;
    }

    // this is an add then...!
    if (!edited) {
      items[items.length + 1] = new_card;
      new_data.sort = r.data.sortMap[new_data.objectPHID] || new_data.sort;
    }

    items.sort(colsort);

    JX.DOM.setContent(column, items);
  };

  JX.Stratcom.listen(
    'click',
    ['edit-project-card'],
    function(e) {
      e.kill();
      var column = e.getNode('project-column');
      var request_data = {
        'responseType' : 'card',
        'columnPHID'   : JX.Stratcom.getData(column).columnPHID };
      new JX.Workflow(e.getNode('tag:a').href, request_data)
      .setHandler(JX.bind(null, onedit, column))
      .start();
    });

  JX.Stratcom.listen(
    'click',
    ['column-add-task'],
    function (e) {
      e.kill();
      var column_phid = e.getNodeData('column-add-task').columnPHID;
      var request_data = {
        'responseType' : 'card',
        'columnPHID'   : column_phid,
        'projects'     : config.projectPHID };
      var cols = JX.DOM.scry(JX.$(config.boardID), 'ul', 'project-column');
      var ii;
      var column;
      for (ii = 0; ii < cols.length; ii++) {
        if (JX.Stratcom.getData(cols[ii]).columnPHID == column_phid) {
          column = cols[ii];
          break;
        }
      }
      new JX.Workflow(config.createURI, request_data)
      .setHandler(JX.bind(null, onedit, column))
      .start();
    });
});
