/**
 * @provides javelin-behavior-remarkup-load-image
 * @requires javelin-behavior
 *           javelin-request
 */

JX.behavior('remarkup-load-image', function(config) {

  function get_node() {
    try {
      return JX.$(config.imageID);
    } catch (ex) {
      return null;
    }
  }

  function onload(r) {
    var node = get_node();
    if (!node) {
      return;
    }

    node.src = r.imageURI;
  }

  function onerror(r) {
    var node = get_node();
    if (!node) {
      return;
    }

    var error = JX.$N(
      'div',
      {
        className: 'phabricator-remarkup-image-error'
      },
      r.info);

    JX.DOM.replace(node, error);
  }

  var request = new JX.Request(config.uri, onload);
  request.listen('error', onerror);
  request.send();
});
