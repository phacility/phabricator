/**
 * @provides phabricator-uiexample-reactor-radio
 * @requires javelin-install
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorRadioExample', {
  extend: 'View',
  members: {
    render: function() {
      var radio_one = JX.$N('input', {type: 'radio', name: 'n', value: 'one'});
      var radio_two = JX.$N('input', {type: 'radio', name: 'n', value: 'two'});

      radio_one.checked = true;

      return [
        radio_one,
        radio_two,
        JX.RDOM.$DT(JX.RDOM.radio([radio_one, radio_two]))
      ];
    }
  }
});
