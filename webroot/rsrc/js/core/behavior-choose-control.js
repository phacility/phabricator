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
    'phui-form-iconset-button',
    function(e) {
      e.kill();

      var data = e.getNodeData('phui-form-iconset');

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
