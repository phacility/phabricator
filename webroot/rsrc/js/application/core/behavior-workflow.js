/**
 * @provides javelin-behavior-workflow
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 */

JX.behavior('workflow', function() {
  JX.Stratcom.listen(
    'submit',
    ['workflow', 'tag:form'],
    function(e) {
      if (JX.Stratcom.pass()) {
        return;
      }
      if (e.getNode('workflow') !== e.getTarget()) {
        return;
      }
      e.prevent();
      JX.Workflow.newFromForm(e.getTarget()).start();
    });
  JX.Stratcom.listen(
    'click',
    ['workflow', 'tag:a'],
    function(e) {
      if (JX.Stratcom.pass()) {
        return;
      }
      if (e.getNode('workflow') !== e.getTarget()) {
        return;
      }
      var raw = e.getRawEvent();
      if (raw.altKey || raw.ctrlKey || raw.metaKey || raw.shiftKey) {
        return;
      }
      e.prevent();
      JX.Workflow.newFromLink(e.getTarget()).start();
    });
});
