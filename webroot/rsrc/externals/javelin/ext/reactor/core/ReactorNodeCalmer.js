/**
 * @provides javelin-reactor-node-calmer
 * @requires javelin-install
 *           javelin-reactor
 *           javelin-util
 * @javelin
 */

JX.install('ReactorNodeCalmer', {
  properties : {
    lastTime : 0,
    timeout : null,
    minInterval : 0,
    reactorNode : null,
    isEnabled : true
  },
  construct : function(node, min_interval) {
    this.setLastTime(-min_interval);
    this.setMinInterval(min_interval);
    this.setReactorNode(node);
  },
  members: {
    onPulse : function(pulse) {
      if (!this.getIsEnabled()) {
        return pulse;
      }
      var current_time = JX.now();
      if (current_time - this.getLastTime() > this.getMinInterval()) {
        this.setLastTime(current_time);
        return pulse;
      } else {
        clearTimeout(this.getTimeout());
        this.setTimeout(setTimeout(
          JX.bind(this, this.send, pulse),
          this.getLastTime() + this.getMinInterval() - current_time
        ));
        return JX.Reactor.DoNotPropagate;
      }
    },
    send : function(pulse) {
      this.setLastTime(JX.now());
      this.setIsEnabled(false);
      this.getReactorNode().forceSendValue(pulse);
      this.setIsEnabled(true);
    }
  }
});
