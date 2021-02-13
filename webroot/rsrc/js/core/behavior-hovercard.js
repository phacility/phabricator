/**
 * @provides javelin-behavior-phui-hovercards
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           javelin-vector
 *           phui-hovercard
 *           phui-hovercard-list
 * @javelin
 */

JX.behavior('phui-hovercards', function(config, statics) {
  if (statics.hovercardList) {
    return;
  }

  var cards = new JX.HovercardList();
  statics.hovercardList = cards;


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

      var node = e.getNode('hovercard');
      var data = e.getNodeData('hovercard').hovercardSpec;

      var card = cards.getCard(data);

      cards.drawCard(card, node);
    });

  JX.Stratcom.listen(
    'mousemove',
    null,
    function (e) {
      cards.onMouseMove(e);
    });

  // When we leave the page, hide any visible hovercards. If we don't do this,
  // clicking a link with a hovercard and then hitting "back" will give you a
  // phantom card. We also hide cards if the window resizes.
  JX.Stratcom.listen(
    ['unload', 'onresize'],
    null,
    function() {
      cards.hideCard();
    });

});
