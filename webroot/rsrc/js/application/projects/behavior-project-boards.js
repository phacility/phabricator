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

  function onresponse(response) {

  }

  function ondrop(list, item, after, from) {
    list.lock();
    JX.DOM.alterClass(item, 'drag-sending', true);

    var data = {
      objectPHID: JX.Stratcom.getData(item).objectPHID,
      columnPHID: JX.Stratcom.getData(list.getRootNode()).columnPHID,
      afterPHID: after && JX.Stratcom.getData(after).objectPHID
    };

    var workflow = new JX.Workflow(config.moveURI, data)
      .setHandler(function(response) {
        onresponse(response);
        list.unlock();

        JX.DOM.alterClass(item, 'drag-sending', false);
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

});
