/**
 * @provides javelin-behavior-diffusion-commit-branches
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-request
 */

JX.behavior('diffusion-commit-branches', function(config) {

  for (var uri in config) {
    JX.DOM.setContent(JX.$(config[uri]), 'Loading...');
    new JX.Request(uri, JX.bind(config[uri], function(r) {
      JX.DOM.setContent(JX.$(this), JX.$H(r));
    })).send();
  }

});
