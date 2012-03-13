/**
 * @provides javelin-behavior-differential-populate
 * @requires javelin-behavior
 *           javelin-workflow
 *           javelin-util
 *           javelin-dom
 */

JX.behavior('differential-populate', function(config) {

  function onresponse(target, response) {
    JX.DOM.replace(JX.$(target), JX.$H(response.changeset));
    if (response.coverage) {
      for (var k in response.coverage) {
        try {
          JX.DOM.replace(JX.$(k), JX.$H(response.coverage[k]));
        } catch (ignored) {
          // Not terribly important.
        }
      }
    }
  }

  for (var k in config.registry) {
    var data = {
      ref : config.registry[k],
      whitespace: config.whitespace
    };

    new JX.Workflow(config.uri, data)
      .setHandler(JX.bind(null, onresponse, k))
      .start();
  }

});
