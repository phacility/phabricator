/**
 * @provides phabricator-uiexample-reactor-mouseover
 * @requires javelin-install
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorMouseoverExample', {
  extend: 'View',
  members: {
    render: function() {
      var target = JX.$N('span', 'mouseover me ');
      return [target, JX.RDOM.$DT(JX.RDOM.isMouseOver(target))];
    }
  }
});
