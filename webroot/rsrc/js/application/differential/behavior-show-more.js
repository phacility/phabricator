/**
 * @provides javelin-behavior-differential-show-more
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-request
 *           javelin-util
 *           javelin-stratcom
 */

JX.behavior('differential-show-more', function(config) {

  function onresponse(origin, response) {
    var div = JX.$N('div', {}, JX.$H(response));
    var anchor = origin.getNode('context-target');
    var root = anchor.parentNode;
    copyRows(root, div, anchor);
    root.removeChild(anchor);
  }

  JX.Stratcom.listen(
    'click',
    'show-more',
    function(e) {
      var context = e.getNodes()['context-target'];
      var container = JX.DOM.find(context, 'td');
      JX.DOM.setContent(container, 'Loading...');
      JX.DOM.alterClass(context, 'differential-show-more-loading', true);
      var data = e.getNodeData('show-more');
      new JX.Request(config.uri, JX.bind(null, onresponse, e))
        .setData(data)
        .send();
      e.kill();
    });

});

function copyRows(dst, src, before) {
  var rows = JX.DOM.scry(src, 'tr');
  for (var ii = 0; ii < rows.length; ii++) {
    if (before) {
      dst.insertBefore(rows[ii], before);
    } else {
      dst.appendChild(rows[ii]);
    }
  }
}
