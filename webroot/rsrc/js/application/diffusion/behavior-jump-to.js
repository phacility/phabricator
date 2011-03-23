/**
 * @provides javelin-behavior-diffusion-jump-to
 * @requires javelin-lib-dev
 */

JX.behavior('diffusion-jump-to', function(config) {

  JX.defer(
    function() {
      window.scrollTo(0, JX.$V(JX.$(config.target)).y - 100);
    });

});

