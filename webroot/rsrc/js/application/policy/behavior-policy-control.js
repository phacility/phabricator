/**
 * @provides javelin-behavior-policy-control
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-dropdown-menu
 *           phabricator-menu-item
 * @javelin
 */
JX.behavior('policy-control', function(config) {
  var control = JX.$(config.controlID);
  var input = JX.$(config.inputID);
  var value = config.value;

  var menu = new JX.PhabricatorDropdownMenu(control)
    .setWidth(260);

  menu.toggleAlignDropdownRight(false);

  menu.listen('open', function() {
    menu.clear();

    for (var ii = 0; ii < config.groups.length; ii++) {
      var group = config.groups[ii];

      var header = new JX.PhabricatorMenuItem(config.labels[group]);
      header.setDisabled(true);
      menu.addItem(header);

      for (var jj = 0; jj < config.order[group].length; jj++) {
        var phid = config.order[group][jj];
        var option = config.options[phid];

        var render = [JX.$H(config.icons[option.icon]), option.name];

        var item = new JX.PhabricatorMenuItem(
          render,
          JX.bind(null, function(phid, render) {
            JX.DOM.setContent(
              JX.DOM.find(control, 'span', 'policy-label'),
              render);
            input.value = phid;
            value = phid;
          }, phid, render));

        if (phid == value) {
          item.setSelected(true);
        }

        menu.addItem(item);
      }
    }

  });

});
