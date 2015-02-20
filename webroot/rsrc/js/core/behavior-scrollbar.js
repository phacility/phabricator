/**
 * @provides javelin-behavior-scrollbar
 * @requires javelin-behavior
 *           javelin-scrollbar
 */

JX.behavior('scrollbar', function(config) {
  var bar = new JX.Scrollbar(JX.$(config.nodeID));
  if (config.isMainContent) {
    bar.setAsScrollFrame();
  }
});
