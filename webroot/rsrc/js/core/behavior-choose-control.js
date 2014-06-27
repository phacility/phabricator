/**
 * @provides javelin-behavior-choose-control
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-workflow
 */

JX.behavior('choose-control', function() {

  JX.Stratcom.listen(
    'click',
    'aphront-form-choose-button',
    function(e) {
      e.kill();

      var data = e.getNodeData('aphront-form-choose');

      var params = {
        value: JX.$(data.inputID).value
      };

      new JX.Workflow(data.uri, params)
        .setHandler(function(r) {
          JX.$(data.inputID).value = r.value;
          JX.DOM.setContent(JX.$(data.displayID), JX.$H(r.display));
        })
        .start();
    });

});
