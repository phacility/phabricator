/**
 * @provides javelin-behavior-maniphest-transaction-expand
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-workflow
 *           javelin-stratcom
 */

/**
 * When the user clicks "show details" in a Maniphest transaction, replace the
 * summary rendering with a detailed rendering.
 */
JX.behavior('maniphest-transaction-expand', function() {

  JX.Stratcom.listen(
    'click',
    'maniphest-expand-transaction',
    function(e) {
      e.kill();
      JX.Workflow.newFromLink(e.getTarget(), {})
        .setHandler(function(r) {
          JX.DOM.setContent(
            e.getNode('maniphest-transaction-description'),
            JX.$H(r));
        })
        .start();
    });

});
