/**
 * @provides javelin-behavior-workflow
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           javelin-router
 */

JX.behavior('workflow', function() {

  // Queue a workflow at elevated priority. The user just clicked or submitted
  // something, so service this before loading background content.
  var queue = function(workflow) {
    var routable = workflow.getRoutable()
      .setPriority(2000)
      .setType('workflow');

    JX.Router.getInstance().queue(routable);
  };

  // If a user clicks an alternate submit button, make sure it gets marshalled
  // into the workflow.
  JX.Stratcom.listen(
    'click',
    ['workflow', 'tag:form', 'alternate-submit-button'],
    function(e) {
      e.prevent();

      var target = e.getNode('alternate-submit-button');
      var form = e.getNode('tag:form');
      var button = {};
      button[target.name] = target.value || true;

      JX.DOM.invoke(form, 'didSyntheticSubmit', {extra: button});
    });

  // Listen for both real and synthetic submit events.
  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    ['workflow', 'tag:form'],
    function(e) {
      if (JX.Stratcom.pass()) {
        return;
      }

      var data = e.getData();
      var extra = (data && data.extra) || {};

      // NOTE: We activate workflow if any parent node has the "workflow" sigil,
      // not just the <form /> itself.

      e.prevent();

      queue(JX.Workflow.newFromForm(e.getNode('tag:form'), extra));
    });

  JX.Stratcom.listen(
    'click',
    ['workflow', 'tag:a'],
    function(e) {
      if (!e.isNormalClick()) {
        return;
      }

      // NOTE: As above, we want to activate workflow if a parent node has
      // the sigil, not just the <a /> that the user clicked. However, there
      // is an exception in this case: if the <a /> does not have workflow and
      // is inside a <form /> which does, we don't workflow it (this covers
      // things like "help" links in captions). Test if the node with the
      // workflow sigil is a form.

      var workflow_node = e.getNode('workflow');
      if (JX.DOM.isType(workflow_node, 'form')) {
        // This covers the case of an <a /> without workflow inside a <form />
        // with workflow.
        return;
      }

      if (JX.Stratcom.pass()) {
        return;
      }

      e.prevent();
      queue(JX.Workflow.newFromLink(e.getNode('tag:a')));
    });

});
