/**
 * @provides phabricator-uiexample-reactor-checkbox
 * @requires javelin-install
 *           javelin-view
 *           javelin-util
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorCheckboxExample', {
  extend: 'View',
  members: {
    render: function(rendered_children) {
      var checkbox = JX.$N('input', {type: 'checkbox'});

      return [checkbox, JX.RDOM.$DT(JX.RDOM.checkbox(checkbox))];
    }
  }
});
