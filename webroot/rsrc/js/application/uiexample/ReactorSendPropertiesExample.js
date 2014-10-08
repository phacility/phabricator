/**
 * @provides phabricator-uiexample-reactor-sendproperties
 * @requires javelin-install
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorSendPropertiesExample', {
  extend: 'View',
  members: {
    render: function() {
      var color = JX.$N('input', {value: '#fff000'});
      var title = JX.$N('input', {value: 'seen on hover'});
      var target = JX.$N('span', 'Change my color and title');

      JX.RDOM.sendProps(target, {
        style: {
          backgroundColor: JX.RDOM.input(color)
        },
        title: JX.RDOM.input(title)
      });

      return [color, title, target];
    }
  }
});
