/**
 * @provides phabricator-uiexample-reactor-select
 * @requires javelin-install
 *           javelin-dom
 *           javelin-reactor-dom
 */

JX.install('ReactorSelectExample', {
  extend: 'View',
  members: {
    render: function() {
      var select = JX.$N('select', {}, [
        JX.$N('option', { value: 'goat' }, 'Goat'),
        JX.$N('option', { value: 'bat' }, 'Bat'),
        JX.$N('option', { value: 'duck' }, 'Duck')
      ]);

      return [select, JX.RDOM.$DT(JX.RDOM.select(select))];
    }
  }
});
