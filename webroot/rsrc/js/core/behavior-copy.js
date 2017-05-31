/**
 * @provides javelin-behavior-phabricator-clipboard-copy
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 * @javelin
 */

JX.behavior('phabricator-clipboard-copy', function() {

  if (!document.queryCommandSupported) {
    return;
  }

  if (!document.queryCommandSupported('copy')) {
    return;
  }

  JX.DOM.alterClass(document.body, 'supports-clipboard', true);

  JX.Stratcom.listen('click', 'clipboard-copy', function(e) {
    e.kill();

    var data = e.getNodeData('clipboard-copy');
    var attr = {
      value: data.text || '',
      className: 'clipboard-buffer'
    };

    var node = JX.$N('textarea', attr);
    document.body.appendChild(node);

    try {
      node.select();
      document.execCommand('copy');
    } catch (ignored) {
      // Ignore any errors we hit.
    }

   JX.DOM.remove(node);
  });

});
