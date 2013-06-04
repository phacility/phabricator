/**
 * @provides javelin-behavior-maniphest-list-editor
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-fx
 *           javelin-util
 */

JX.behavior('maniphest-list-editor', function(config) {

  var onedit = function(task, r) {
    var nodes = JX.$H(r.tasks).getFragment().firstChild;
    var new_task = JX.DOM.find(nodes, 'li', 'maniphest-task');
    JX.DOM.replace(task, new_task);

    new JX.FX(new_task).setDuration(500).start({opacity: [0, 1]});
  };

  JX.Stratcom.listen('click', 'maniphest-edit-task', function(e) {
    e.kill();
    var task = e.getNode('maniphest-task');
    JX.Workflow.newFromLink(e.getNode('maniphest-edit-task'))
      .setHandler(JX.bind(null, onedit, task))
      .start();
  });

});
