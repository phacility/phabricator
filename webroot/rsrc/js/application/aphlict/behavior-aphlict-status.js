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
    var icon = config.icon[status];
    var status_node = JX.$N(
      'span',
      {
        className: 'connection-status-text aphlict-connection-status-' + status
      },
      pht(status));

    var icon_node = new JX.PHUIXIconView()
      .setIcon(icon['icon'])
      .setColor(icon['color'])
      .getNode();

    var content = [icon_node, ' ', status_node];

    JX.DOM.setContent(node, content);
  }

  JX.Aphlict.listen('didChangeStatus', update);
  update();
});
