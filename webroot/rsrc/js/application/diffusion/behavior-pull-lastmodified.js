/**
 * @provides javelin-behavior-diffusion-pull-lastmodified
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-request
 */

JX.behavior('diffusion-pull-lastmodified', function(config) {

  for (var uri in config) {
    new JX.Request(uri, JX.bind(config[uri], function(r) {
      for (var k in r) {
        if (this[k]) {
          JX.DOM.setContent(JX.$(this[k]), JX.$H(r[k]));
        }
      }
    })).send();
  }

});
