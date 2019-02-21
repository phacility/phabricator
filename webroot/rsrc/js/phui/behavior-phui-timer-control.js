/**
 * @provides javelin-behavior-phui-timer-control
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phui-timer-control', function(config) {
  var node = JX.$(config.nodeID);
  var uri = config.uri;
  var state = null;

  function onupdate(result) {
    var markup = result.markup;
    if (markup) {
      var new_node = JX.$H(markup).getFragment().firstChild;
      JX.DOM.replace(node, new_node);
      node = new_node;

      // If the overall state has changed from the previous display state,
      // animate the control to draw the user's attention to the state change.
      if (result.state !== state) {
        state = result.state;
        JX.DOM.alterClass(node, 'phui-form-timer-updated', true);
      }
    }

    var retry = result.retry;
    if (retry) {
      setTimeout(update, 1000);
    }
  }

  function update() {
    new JX.Request(uri, onupdate)
      .setTimeout(10000)
      .send();
  }

  update();
});
