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
          try {
            JX.DOM.setContent(JX.$(config.map[k][l]), JX.$H(r[k][l]));
          } catch (ex) {
            // The way this works is weird and sometimes the components get
            // out of sync. Fail gently until we can eventually improve the
            // underlying mechanism.

            // In particular, we currently may generate lint information
            // without generating a lint column. See T9524.
          }
        }
      }
    })
    .start();

});
