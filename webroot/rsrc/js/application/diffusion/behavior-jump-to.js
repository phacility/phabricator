/**
 * @provides javelin-behavior-diffusion-jump-to
 * @requires javelin-behavior
 *           javelin-vector
 *           javelin-dom
 */

JX.behavior('diffusion-jump-to', function(config) {

  setTimeout(function() {
    window.scrollTo(0, JX.$V(JX.$(config.target)).y - 100);
  }, 0);

});
