/**
 * @provides javelin-behavior-phui-profile-menu
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phui-profile-menu', function(config) {
  // NOTE: This behavior is not initialized in the rendering pipeline for the
  // menu, so it can get initialized when we build but do not render a menu
  // (for example, when a page like the panel edit page only has items in
  // the mobile/application menu, and does not show the profile menu). For now,
  // just bail if we can't find the menu.

  try {
    var menu_node = JX.$(config.menuID);
  } catch (ex) {
    return;
  }

  var collapse_node = JX.$(config.collapseID);

  var is_collapsed = config.isCollapsed;

  JX.DOM.listen(collapse_node, 'click', null, function(e) {
    is_collapsed = !is_collapsed;

    JX.DOM.alterClass(menu_node, 'phui-profile-menu-collapsing', is_collapsed);
    JX.DOM.alterClass(menu_node, 'phui-profile-menu-expanding', !is_collapsed);

    var duration = 0.2;

    setTimeout(function() {
      JX.DOM.alterClass(menu_node, 'phui-profile-menu-collapsed', is_collapsed);
      JX.DOM.alterClass(menu_node, 'phui-profile-menu-expanded', !is_collapsed);
    }, (duration / 2) * 1000);

    setTimeout(function() {
      JX.DOM.alterClass(menu_node, 'phui-profile-menu-collapsing', false);
      JX.DOM.alterClass(menu_node, 'phui-profile-menu-expanding', false);
    }, duration * 1000);


    if (config.settingsURI) {
      new JX.Request(config.settingsURI)
        .setData({value: (is_collapsed ? 1 : 0)})
        .send();
    }

    e.kill();
  });

});
