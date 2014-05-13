/**
 * @provides javelin-behavior-diffusion-pull-lastmodified
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-json
 */

JX.behavior('diffusion-pull-lastmodified', function(config) {

  new JX.Workflow(config.uri, {paths: JX.JSON.stringify(JX.keys(config.map))})
    .setHandler(function(r) {
      for (var k in r) {
        for (var l in r[k]) {
          if (!config.map[k][l]) {
            continue;
          }
          JX.DOM.setContent(JX.$(config.map[k][l]), JX.$H(r[k][l]));
        }
      }
    })
    .start();

});
