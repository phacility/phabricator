/**
 * @provides javelin-behavior-diffusion-jump-to
 * @requires javelin-behavior
 *           javelin-vector
 *           javelin-dom
 */

JX.behavior('diffusion-jump-to', function(config) {

  setTimeout(function() {
    var pos = JX.Vector.getPosWithScroll(JX.$(config.target));
    JX.DOM.scrollToPosition(0, pos.y - 100);
  }, 0);

});
