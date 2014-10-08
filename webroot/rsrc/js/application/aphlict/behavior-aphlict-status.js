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

    var tip = null;
    var status = client.getStatus();

    if (status == 'error') {
      tip = pht(client.getStatusCode());
    }

    var status_node = JX.$N(
      'span',
      {
        className: 'aphlict-connection-status-' + status,
        sigil: tip ? 'has-tooltip' : null,
        meta: tip ? {tip: tip, align: 'S', size: 300} : {}
      },
      pht(status));

    JX.DOM.setContent(node, status_node);
  }

  JX.Aphlict.listen('didChangeStatus', update);
  update();
});
