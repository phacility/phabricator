/**
 * @provides javelin-behavior-workflow
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 */

JX.behavior('workflow', function() {

  // Listen for both real
  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    ['workflow', 'tag:form'],
    function(e) {
      if (JX.Stratcom.pass()) {
        return;
      }
      var target = e.getNode('workflow');
      e.prevent();
      JX.Workflow.newFromForm(target).start();
    });

  JX.Stratcom.listen(
    'click',
    ['workflow', 'tag:a'],
    function(e) {
      if (!e.isNormalClick()) {
        return;
      }

      var target = e.getNode('workflow');
      if (!JX.DOM.isType(target, 'a')) {
        // This covers the case of an <a /> without workflow inside a <form />
        // with workflow.
        return;
      }

      if (JX.Stratcom.pass()) {
        return;
      }

      e.prevent();
      JX.Workflow.newFromLink(target).start();
    });

});
