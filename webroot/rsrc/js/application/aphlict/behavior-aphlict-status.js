/**
 * @provides javelin-behavior-aphlict-status
 * @requires javelin-behavior
 *           javelin-aphlict
 *           phabricator-phtize
 *           javelin-dom
 * @javelin
 */

JX.behavior('aphlict-status', function(config) {
  var pht = JX.phtize(config.pht);

  function update() {
    var client = JX.Aphlict.getInstance();
    if (!client) {
      return;
    }

    var node;
    try {
      node = JX.$(config.nodeID);
    } catch (ignored) {
      return;
    }

    var status = client.getStatus();
    var status_node = JX.$N(
      'span',
      {
        className: 'aphlict-connection-status-' + status
      },
      pht(status));

    JX.DOM.setContent(node, status_node);
  }

  JX.Aphlict.listen('didChangeStatus', update);
  update();
});
