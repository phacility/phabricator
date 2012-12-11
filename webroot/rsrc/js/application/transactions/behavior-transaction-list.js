/**
 * @provides javelin-behavior-phabricator-transaction-list
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 */

JX.behavior('phabricator-transaction-list', function(config) {

  var list = JX.$(config.listID);
  var xaction_nodes = null;

  function get_xaction_nodes() {
    if (xaction_nodes === null) {
      xaction_nodes = {};
      var xactions = JX.DOM.scry(list, 'div', 'transaction');
      for (var ii = 0; ii < xactions.length; ii++) {
        xaction_nodes[JX.Stratcom.getData(xactions[ii]).phid] = xactions[ii];
      }
    }
    return xaction_nodes;
  }

  function ontransactions(response) {
    var nodes = get_xaction_nodes();
    for (var phid in response.xactions) {
      var new_node = JX.$H(response.xactions[phid]).getFragment().firstChild;
      if (nodes[phid]) {
        JX.DOM.replace(nodes[phid], new_node);
      } else {
        list.appendChild(new_node);
      }
      nodes[phid] = new_node;
    }
  }

  JX.DOM.listen(list, 'click', 'transaction-edit', function(e) {
    if (!e.isNormalClick()) {
      return;
    }

    JX.Workflow.newFromLink(e.getTarget())
      .setData({anchor: e.getNodeData('transaction').anchor})
      .setHandler(ontransactions)
      .start();

    e.kill();
  });

});
