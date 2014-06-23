/**
 * @provides javelin-behavior-phabricator-hovercards
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           javelin-vector
 *           phabricator-hovercard
 * @javelin
 */

JX.behavior('phabricator-hovercards', function() {

  // We listen for mousemove instead of mouseover to handle the case when user
  // scrolls with keyboard. We don't want to display hovercard if node gets
  // under the mouse cursor randomly placed somewhere on the screen. This
  // unfortunately doesn't work in Google Chrome which triggers both mousemove
  // and mouseover in this case but works in other browsers.
  JX.Stratcom.listen(
    'mousemove',
    'hovercard',
    function (e) {
      if (JX.Device.getDevice() != 'desktop') {
        return;
      }

      var data = e.getNodeData('hovercard');

      JX.Hovercard.show(
        e.getNode('hovercard'),
        data.hoverPHID);
    });

  JX.Stratcom.listen(
    'mousemove',
    null,
    function (e) {
      if (!JX.Hovercard.getCard()) {
        return;
      }

      var root = JX.Hovercard.getAnchor();
      var node = JX.Hovercard.getCard();

      // TODO: Add southern cases here, too
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
  // phantom card. We also hide cards if the window resizes.
  JX.Stratcom.listen(
    ['unload', 'onresize'],
    null,
    function() {
      JX.Hovercard.hide();
    });

});
