/**
 * @provides javelin-behavior-diffusion-jump-to
 * @requires javelin-behavior
 *           javelin-util
 *           javelin-vector
 *           javelin-dom
 */

JX.behavior('diffusion-jump-to', function(config) {

  JX.defer(
    function() {
      window.scrollTo(0, JX.$V(JX.$(config.target)).y - 100);
    });

});

