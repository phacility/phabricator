/**
 * @provides javelin-behavior-differential-populate
 * @requires javelin-behavior
 *           javelin-workflow
 *           javelin-util
 *           javelin-dom
 */

JX.behavior('differential-populate', function(config) {

  function onresponse(target, response) {
    JX.DOM.replace(JX.$(target), JX.$H(response));
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
