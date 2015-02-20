/**
 * @provides javelin-reactor
 * @requires javelin-install
 *           javelin-util
 * @javelin
 */

JX.install('Reactor', {
  statics : {
    /**
     * Return this value from a ReactorNode transformer to indicate that
     * its listeners should not be activated.
     */
    DoNotPropagate : {},
    /**
     * For internal use by the Reactor system.
     */
    propagatePulse : function(start_pulse, start_node) {
      var reverse_post_order =
        JX.Reactor._postOrder(start_node).reverse();
      start_node.primeValue(start_pulse);

      for (var ix = 0; ix < reverse_post_order.length; ix++) {
        var node = reverse_post_order[ix];
        var pulse = node.getNextPulse();
        if (pulse === JX.Reactor.DoNotPropagate) {
          continue;
        }

        var next_pulse = node.getTransformer()(pulse);
        var sends_to = node.getListeners();
        for (var jx = 0; jx < sends_to.length; jx++) {
          sends_to[jx].primeValue(next_pulse);
        }
      }
    },
    /**
     * For internal use by the Reactor system.
     */
    _postOrder : function(node, result, pending) {
      if (typeof result === 'undefined') {
        result = [];
        pending = {};
      }
      pending[node.getGraphID()] = true;

      var nexts = node.getListeners();
      for (var ix = 0; ix < nexts.length; ix++) {
        var next = nexts[ix];
        if (pending[next.getGraphID()]) {
          continue;
        }
        JX.Reactor._postOrder(next, result, pending);
      }

      result.push(node);
      return result;
    },

    // Helper for lift.
    _valueNow : function(fn, dynvals) {
      var values = [];
      for (var ix = 0; ix < dynvals.length; ix++) {
        values.push(dynvals[ix].getValueNow());
      }
      return fn.apply(null, values);
    },

    /**
     * Lift a function over normal values to be a function over dynvals.
     * @param fn       A function expecting normal values
     * @param dynvals  Array of DynVals whose instaneous values will be passed
     *                 to fn.
     * @return A DynVal representing the changing value of fn applies to dynvals
     *         over time.
     */
    lift : function(fn, dynvals) {
      var valueNow = JX.bind(null, JX.Reactor._valueNow, fn, dynvals);

      var streams = [];
      for (var ix = 0; ix < dynvals.length; ix++) {
        streams.push(dynvals[ix].getChanges());
      }

      var result = new JX['ReactorNode'](streams, valueNow);
      return new JX['DynVal'](result, valueNow());
    }
  }
});
