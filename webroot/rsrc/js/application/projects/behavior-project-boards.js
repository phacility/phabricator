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

  function ondrop(list, item, after, from) {
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

  var onedit = function(card, column, r) {
    var new_card = JX.$H(r.tasks);
    var items = finditems(column);
    var insert_after = r.data.insertAfterPHID;
    if (!insert_after) {
      JX.DOM.prependContent(column, new_card);
      if (card) {
        JX.DOM.remove(card);
      }
      return;
    }
    var ii;
    var item;
    var item_phid;
    for (ii = 0; ii< items.length; ii++) {
      item = items[ii];
      item_phid = JX.Stratcom.getData(item).objectPHID;
      if (item_phid == insert_after) {
        JX.DOM.replace(item, [item, new_card]);
        if (card) {
          JX.DOM.remove(card);
        }
        return;
      }
    }
  };

  JX.Stratcom.listen(
    'click',
    ['edit-project-card'],
    function(e) {
      e.kill();
      var card = e.getNode('project-card');
      var column = e.getNode('project-column');
      var request_data = {
        'responseType' : 'card',
        'columnPHID'   : JX.Stratcom.getData(column).columnPHID };
      new JX.Workflow(e.getNode('tag:a').href, request_data)
      .setHandler(JX.bind(null, onedit, card, column))
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
      .setHandler(JX.bind(null, onedit, null, column))
      .start();
    });
});
