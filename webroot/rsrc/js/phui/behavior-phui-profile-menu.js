/**
 * @provides javelin-behavior-phui-profile-menu
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phui-profile-menu', function(config) {
  var menu_node = JX.$(config.menuID);
  var collapse_node = JX.$(config.collapseID);

  var is_collapsed = config.isCollapsed;

  JX.DOM.listen(collapse_node, 'click', null, function(e) {
    is_collapsed = !is_collapsed;
    JX.DOM.alterClass(menu_node, 'phui-profile-menu-collapsed', is_collapsed);
    JX.DOM.alterClass(menu_node, 'phui-profile-menu-expanded', !is_collapsed);

    if (config.settingsURI) {
      new JX.Request(config.settingsURI)
        .setData({value: (is_collapsed ? 1 : 0)})
        .send();
    }

    e.kill();
  });

});
