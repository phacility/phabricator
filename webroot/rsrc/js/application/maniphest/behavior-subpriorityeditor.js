/**
 * @provides javelin-behavior-maniphest-subpriority-editor
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-vector
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
    .setGhostNode(JX.$N('li', {className: 'maniphest-subpriority-target'}))
    .setGhostHandler(function(ghost, target) {
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
    if (e.getNode('slippery')) {
      JX.Stratcom.context().kill();
    }
  });

  draggable.listen('didBeginDrag', function(node) {
    draggable.getGhostNode().style.height = JX.Vector.getDim(node).y + 'px';
    JX.DOM.alterClass(node, 'maniphest-task-dragging', true);
  });

  draggable.listen('didEndDrag', function(node) {
    JX.DOM.alterClass(node, 'maniphest-task-dragging', false);
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
    JX.DOM.alterClass(node, 'maniphest-task-loading', true);

    var onresponse = function(r) {
      var nodes = JX.$H(r.tasks).getFragment().firstChild;
      var task = JX.DOM.find(nodes, 'li', 'maniphest-task');
      JX.DOM.replace(node, task);

      draggable.unlock();
    };

    new JX.Workflow(config.uri, data)
      .setHandler(onresponse)
      .start();
  });

});
