/**
 * @provides javelin-behavior-scrollbar
 * @requires javelin-behavior
 *           javelin-scrollbar
 */

JX.behavior('scrollbar', function(config) {
  new JX.Scrollbar(JX.$(config.nodeID));
});
