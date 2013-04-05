/**
 * @provides javelin-behavior-phabricator-hovercards
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           phabricator-hovercard
 * @javelin
 */

JX.behavior('phabricator-hovercards', function(config) {

  // First find all hovercard-able object on page and load them in a batch
  JX.Hovercard.scrapeAndLoad();

  // Event stuff
  JX.Stratcom.listen(
    ['mouseover'],
    'hovercard',
    function (e) {
      if (e.getType() == 'mouseout') {
        JX.Hovercard.hide();
        return;
      }

      if (JX.Device.getDevice() != 'desktop') {
        return;
      }

      var data = e.getNodeData('hovercard');

      JX.Hovercard.show(
        e.getNode('hovercard'),
        data.hoverPHID);
    });

  JX.Stratcom.listen(
    ['mousemove'],
    null,
    function (e) {
      if (JX.Device.getDevice() != 'desktop') {
        return;
      }

      if (!JX.Hovercard._node) {
        return;
      }

      var root = JX.Hovercard._activeRoot;
      var node = JX.Hovercard._node.firstChild;

      var mouse = JX.$V(e);
      var node_pos = JX.$V(node);
      var node_dim = JX.Vector.getDim(node);
      var root_pos = JX.$V(root);
      var root_dim = JX.Vector.getDim(root);

      var margin = 20;

      // Cursor is above the node.
      if (mouse.y < node_pos.y - margin) {
        JX.Hovercard.hide();
      }

      // Cursor is below the root.
      if (mouse.y > root_pos.y + root_dim.y + margin) {
        JX.Hovercard.hide();
      }

      // Cursor is too far to the left.
      if (mouse.x < Math.min(root_pos.x, node_pos.x) - margin) {
        JX.Hovercard.hide();
      }

       // Cursor is too far to the right.
      if (mouse.x >
        Math.max(root_pos.x + root_dim.x, node_pos.x + node_dim.x) + margin) {

        JX.Hovercard.hide();
      }
    });

  // When we leave the page, hide any visible hovercards. If we don't do this,
  // clicking a link with a hovercard and then hitting "back" will give you a
  // phantom tooltip.
  JX.Stratcom.listen(
    'unload',
    null,
    function(e) {
      JX.Hovercard.hide();
    });

});
