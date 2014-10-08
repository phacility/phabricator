/**
 * @provides phabricator-uiexample-reactor-checkbox
 * @requires javelin-install
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorCheckboxExample', {
  extend: 'View',
  members: {
    render: function() {
      var checkbox = JX.$N('input', {type: 'checkbox'});

      return [checkbox, JX.RDOM.$DT(JX.RDOM.checkbox(checkbox))];
    }
  }
});
