/**
 * @provides phabricator-uiexample-reactor-button
 * @requires javelin-install
 *           javelin-dom
 *           javelin-util
 *           javelin-dynval
 *           javelin-reactor-dom
 */

JX.install('ReactorButtonExample', {
  extend: 'View',
  members: {
    render: function() {
      var button = JX.$N('button', {}, 'Fun');
      var clicks = JX.RDOM.clickPulses(button);

      var time = JX.RDOM.time();

      // function snapshot(pulses, dynval) {
      //   return new DynVal(
      //     pulses.transform(JX.bind(dynval, dynval.getValueNow)),
      //     dynval.getValueNow()
      //   );
      // }
      //
      // Below could be...
      // time.snapshot(clicks)
      // clicks.snapshot(time)

      var snapshot_time = new JX.DynVal(
        clicks.transform(JX.bind(time, time.getValueNow)),
        time.getValueNow()
      );

      return [button, JX.RDOM.$DT(snapshot_time)];
    }
  }
});
