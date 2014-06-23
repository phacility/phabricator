/**
 * @provides phabricator-uiexample-reactor-sendclass
 * @requires javelin-install
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorSendClassExample', {
  extend: 'View',
  members: {
    render: function() {
      var input = JX.$N('input', { type: 'checkbox' });
      var span = JX.$N('a', 'Hey');
      JX.RDOM.sendClass(JX.RDOM.checkbox(input), span, 'disabled');
      return [input, span];
    }
  }
});
