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
      var input = JX.$(data.inputID);

      if (input.disabled) {
        return;
      }

      var params = {
        icon: input.value
      };

      new JX.Workflow(data.uri, params)
        .setHandler(function(r) {
          input.value = r.value;
          JX.DOM.setContent(JX.$(data.displayID), JX.$H(r.display));
        })
        .start();
    });

});
