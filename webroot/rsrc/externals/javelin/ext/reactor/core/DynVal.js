/**
 * @provides javelin-dynval
 * @requires javelin-install
 *           javelin-reactornode
 *           javelin-util
 *           javelin-reactor
 * @javelin
 */

JX.install('DynVal', {
  members : {
    _lastPulseVal : null,
    _reactorNode : null,
    getValueNow : function() {
      return this._lastPulseVal;
    },
    getChanges : function() {
      return this._reactorNode;
    },
    forceValueNow : function(value) {
      this.getChanges().forceSendValue(value);
    },
    transform : function(fn) {
      return new JX.DynVal(
        this.getChanges().transform(fn),
        fn(this.getValueNow())
      );
    },
    calm : function(min_interval) {
      return new JX.DynVal(
        this.getChanges().calm(min_interval),
        this.getValueNow()
      );
    }
  },
  construct : function(stream, init) {
    this._lastPulseVal = init;
    this._reactorNode =
      new JX.ReactorNode([stream], JX.bind(this, function(pulse) {
        if (this._lastPulseVal == pulse) {
          return JX.Reactor.DoNotPropagate;
        }
        this._lastPulseVal = pulse;
        return pulse;
      }));
  }
});
