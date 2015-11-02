/**
 * @provides javelin-behavior-drydock-live-operation-status
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-request
 * @javelin
 */

JX.behavior('drydock-live-operation-status', function(config) {
  var node = JX.$(config.statusID);

  function update() {
    new JX.Request(config.updateURI, onresponse)
      .send();
  }

  function onresponse(r) {
    var new_node = JX.$H(r.markup).getNode();
    JX.DOM.replace(node, new_node);
    node = new_node;

    if (r.isUnderway) {
      poll();
    }
  }

  function poll() {
    setTimeout(update, 1000);
  }

  poll();
});
