/**
 * @provides javelin-behavior-dashboard-async-panel
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-workflow
 */

JX.behavior('dashboard-async-panel', function(config) {
  var panel = JX.$(config.panelID);
  panel.style.opacity = '0.5';

  new JX.Workflow(config.uri)
    .setHandler(function(r) {
      JX.DOM.replace(panel, JX.$H(r.panelMarkup));
    })
    .start();
});
