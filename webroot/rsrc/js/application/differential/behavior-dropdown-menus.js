/**
 * @provides javelin-behavior-differential-dropdown-menus
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           phabricator-dropdown-menu
 *           phabricator-menu-item
 */

JX.behavior('differential-dropdown-menus', function(config) {

  var buttons = JX.DOM.scry(window.document, 'a', 'differential-view-options');

  for (var ii = 0; ii < buttons.length; ii++) {
    var data = JX.Stratcom.getData(buttons[ii]);

    var diffusion_item = new JX.PhabricatorMenuItem(
      'Browse in Diffusion',
      JX.bind(null, window.open, data.diffusionURI))

    if (!data.diffusionURI) {
      diffusion_item.setDisabled(true);
    }

    new JX.PhabricatorDropdownMenu(buttons[ii])
      .addItem(diffusion_item)
      .addItem(
        new JX.PhabricatorMenuItem(
          'View Standalone',
          JX.bind(null, window.open, data.detailURI)))
      .addItem(
        new JX.PhabricatorMenuItem(
          'Show Raw File (Left)',
          JX.bind(null, window.open, data.leftURI)))
      .addItem(
        new JX.PhabricatorMenuItem(
          'Show Raw File (Right)',
          JX.bind(null, window.open, data.rightURI)));
  }

});
