/**
 * @provides javelin-behavior-maniphest-subpriority-editor
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-draggable-list
 */

JX.behavior('maniphest-subpriority-editor', function(config) {

  var draggable = new JX.DraggableList('maniphest-task')
    .setFindItemsHandler(function() {
      var tasks = JX.DOM.scry(document.body, 'li', 'maniphest-task');
      var heads = JX.DOM.scry(document.body, 'h1',  'task-group');
      return tasks.concat(heads);
    })
    .setGhostHandler(function(ghost, target) {
      if (!target) {
        // The user is trying to drag a task above the first group header;
        // don't permit that since it doesn't make sense.
        return false;
      }

      if (target.nextSibling) {
        if (JX.DOM.isType(target, 'h1')) {
          target.nextSibling.insertBefore(ghost, target.nextSibling.firstChild);
        } else {
          target.parentNode.insertBefore(ghost, target.nextSibling);
        }
      } else {
        target.parentNode.appendChild(ghost);
      }
    });

  draggable.listen('shouldBeginDrag', function(e) {
    if (e.getNode('slippery') || e.getNode('maniphest-edit-task')) {
      JX.Stratcom.context().kill();
    }
  });

  draggable.listen('didDrop', function(node, after) {
    var data = {
      task: JX.Stratcom.getData(node).taskID
    };

    if (JX.DOM.isType(after, 'h1')) {
      data.priority = JX.Stratcom.getData(after).priority;
    } else {
      data.after = JX.Stratcom.getData(after).taskID;
    }

    draggable.lock();
    JX.DOM.alterClass(node, 'drag-sending', true);

    var onresponse = function(r) {
      var nodes = JX.$H(r.tasks).getFragment().firstChild;
      var task = JX.DOM.find(nodes, 'li', 'maniphest-task');
      JX.DOM.replace(node, task);
      draggable.unlock();
      JX.Stratcom.invoke(
        'subpriority-changed',
        null,
        { 'task' : task });
    };

    new JX.Workflow(config.uri, data)
      .setHandler(onresponse)
      .start();
  });

});
