/**
 * @provides phabricator-uiexample-reactor-focus
 * @requires javelin-install
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorFocusExample', {
  extend: 'View',
  members: {
    render: function() {
      var input = JX.$N('input');
      return [input, JX.RDOM.$DT(JX.RDOM.hasFocus(input))];
    }
  }
});
