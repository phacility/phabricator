/**
 * @provides javelin-behavior-harbormaster-log
 * @requires javelin-behavior
 */

JX.behavior('harbormaster-log', function(config) {
  var contentNode = JX.$(config.contentNodeID);

  JX.DOM.listen(contentNode, 'click', 'harbormaster-log-expand', function(e) {
    if (!e.isNormalClick()) {
      return;
    }

    e.kill();

    var row = e.getNode('tag:tr');
    row = JX.DOM.findAbove(row, 'tr');

    var data = e.getNodeData('harbormaster-log-expand');

    var uri = new JX.URI(config.renderURI)
      .addQueryParams(data);

    var request = new JX.Request(uri, function(r) {
      var result = JX.$H(r.markup).getNode();
      var rows = [].slice.apply(result.firstChild.childNodes);

      JX.DOM.replace(row, rows);
    });

    request.send();
  });

  function onresponse(r) {
    JX.DOM.alterClass(contentNode, 'harbormaster-log-view-loading', false);

    JX.DOM.setContent(contentNode, JX.$H(r.markup));
  }

  var uri = new JX.URI(config.renderURI);

  new JX.Request(uri, onresponse)
    .send();

});
