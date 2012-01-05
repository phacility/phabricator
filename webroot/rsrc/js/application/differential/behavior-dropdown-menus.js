/**
 * @provides javelin-behavior-differential-dropdown-menus
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phabricator-dropdown-menu
 *           phabricator-menu-item
 */

JX.behavior('differential-dropdown-menus', function(config) {

  function standalone(button) {
    return function() {
      window.open(JX.Stratcom.getData(button).detailURI);
    }
  }

  function left_file(button) {
    return function() {
      window.open(JX.Stratcom.getData(button).leftURI);
    }
  }

  function right_file(button) {
    return function() {
      window.open(JX.Stratcom.getData(button).rightURI);
    }
  }

  var buttons = JX.DOM.scry(window.document, 'a', 'differential-view-options');
  for (var ii = 0; ii < buttons.length; ii++) {
    var button = buttons[ii];
    new JX.PhabricatorDropdownMenu(buttons[ii])
      .addItem(
        new JX.PhabricatorMenuItem(
          'View Standalone',
          standalone(button)))
      .addItem(
        new JX.PhabricatorMenuItem(
          'Show Raw File (Left)',
          left_file(button)))
      .addItem(
        new JX.PhabricatorMenuItem(
          'Show Raw File (Right)',
          right_file(button)));
  }

});
