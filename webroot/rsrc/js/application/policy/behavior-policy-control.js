/**
 * @provides javelin-behavior-policy-control
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-dropdown-menu
 *           phabricator-menu-item
 *           javelin-workflow
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

      var header = new JX.PhabricatorMenuItem(config.labels[group], JX.bag);
      header.setDisabled(true);
      menu.addItem(header);

      for (var jj = 0; jj < config.order[group].length; jj++) {
        var phid = config.order[group][jj];

        var onselect;
        if (group == 'custom') {
          onselect = JX.bind(null, function(phid) {
            var uri = get_custom_uri(phid);

            new JX.Workflow(uri)
              .setHandler(function(response) {
                if (!response.phid) {
                  return;
                }

                replace_policy(phid, response.phid, response.info);
                select_policy(response.phid);
              })
              .start();

          }, phid);
        } else {
          onselect = JX.bind(null, select_policy, phid);
        }

        var item = new JX.PhabricatorMenuItem(
          render_option(phid, true),
          onselect);

        if (phid == value) {
          item.setSelected(true);
        }

        menu.addItem(item);
      }
    }

  });


  var select_policy = function(phid) {
    JX.DOM.setContent(
      JX.DOM.find(control, 'span', 'policy-label'),
      render_option(phid));

    input.value = phid;
    value = phid;
  };


  var render_option = function(phid, with_title) {
    var option = config.options[phid];

    var name = option.name;
    if (with_title && (option.full != option.name)) {
      name = JX.$N('span', {title: option.full}, name);
    }

    return [JX.$H(config.icons[option.icon]), name];
  };


  /**
   * Get the workflow URI to create or edit a policy with a given PHID.
   */
  var get_custom_uri = function(phid) {
    var uri = '/policy/edit/';
    if (phid != config.customPlaceholder) {
      uri += phid + '/';
    }
    return uri;
  };


  /**
   * Replace an existing policy option with a new one. Used to swap out custom
   * policies after the user edits them.
   */
  var replace_policy = function(old_phid, new_phid, info) {
    config.options[new_phid] = info;
    for (var k in config.order) {
      for (var ii = 0; ii < config.order[k].length; ii++) {
        if (config.order[k][ii] == old_phid) {
          config.order[k][ii] = new_phid;
          return;
        }
      }
    }
  };


});
