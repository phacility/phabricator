/**
 * @provides javelin-behavior-launch-icon-composer
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-workflow
 */

JX.behavior('launch-icon-composer', function(config) {

  JX.DOM.listen(
    JX.$(config.launchID),
    'click',
    null,
    function(e) {
      e.kill();
      new JX.Workflow('/file/compose/')
        .setHandler(function(r) {
          JX.$(config.inputID).value = r.phid;
          JX.DOM.findAbove(e.getTarget(), 'form').submit();
        })
        .start();
    });

});
