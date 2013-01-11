/**
 * @provides javelin-behavior-differential-show-more
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-workflow
 *           javelin-util
 *           javelin-stratcom
 */

JX.behavior('differential-show-more', function(config) {

  function onresponse(context, response) {
    var div = JX.$N('div', {}, JX.$H(response.changeset));
    var root = context.parentNode;
    copyRows(root, div, context);
    root.removeChild(context);
  }

  JX.Stratcom.listen(
    'click',
    'show-more',
    function(e) {
      var event_data = {
        context :  e.getNodes()['context-target'],
        show : e.getNodes()['show-more']
      };

      JX.Stratcom.invoke('differential-reveal-context', null, event_data);
      e.kill();
    });

  JX.Stratcom.listen(
    'differential-reveal-context',
    null,
    function(e) {
      var context = e.getData().context;
      var data = JX.Stratcom.getData(e.getData().show);

      var container = JX.DOM.scry(context, 'td')[0];
      JX.DOM.setContent(container, 'Loading...');
      JX.DOM.alterClass(context, 'differential-show-more-loading', true);

      if (!data['whitespace']) {
        data['whitespace'] = config.whitespace;
      }

      new JX.Workflow(config.uri, data)
        .setHandler(JX.bind(null, onresponse, context))
        .start();
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
  return rows;
}
