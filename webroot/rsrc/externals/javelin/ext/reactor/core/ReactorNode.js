/**
 * @provides javelin-reactornode
 * @requires javelin-install
 *           javelin-reactor
 *           javelin-util
 *           javelin-reactor-node-calmer
 * @javelin
 */

JX.install('ReactorNode', {
  members : {
    _transformer : null,
    _sendsTo : null,
    _nextPulse : null,
    _graphID : null,

    getGraphID : function() {
      return this._graphID || this.__id__;
    },

    setGraphID : function(id) {
      this._graphID = id;
      return this;
    },

    setTransformer : function(fn) {
      this._transformer = fn;
      return this;
    },

    /**
     * Set up dest as a listener to this.
     */
    listen : function(dest) {
      this._sendsTo[dest.__id__] = dest;
      return { remove : JX.bind(null, this._removeListener, dest) };
    },
    /**
     * Helper for listen.
     */
    _removeListener : function(dest) {
      delete this._sendsTo[dest.__id__];
    },
    /**
     * For internal use by the Reactor system
     */
    primeValue : function(value) {
      this._nextPulse = value;
    },
    getListeners : function() {
      var result = [];
      for (var k in this._sendsTo) {
        result.push(this._sendsTo[k]);
      }
      return result;
    },
    /**
     * For internal use by the Reactor system
     */
    getNextPulse : function() {
      return this._nextPulse;
    },
    getTransformer : function() {
      return this._transformer;
    },
    forceSendValue : function(pulse) {
      JX.Reactor.propagatePulse(pulse, this);
    },
    // fn should return JX.Reactor.DoNotPropagate to indicate a value that
    // should not be retransmitted.
    transform : function(fn) {
      return new JX.ReactorNode([this], fn);
    },

    /**
     * Suppress events to happen at most once per min_interval.
     * The last event that fires within an interval will fire at the end
     * of the interval. Events that are sandwiched between other events
     * within an interval are dropped.
     */
    calm : function(min_interval) {
      var result = new JX.ReactorNode([this], JX.id);
      var transformer = new JX.ReactorNodeCalmer(result, min_interval);
      result.setTransformer(JX.bind(transformer, transformer.onPulse));
      return result;
    }
  },
  construct : function(source_streams, transformer) {
    this._nextPulse = JX.Reactor.DoNotPropagate;
    this._transformer = transformer;
    this._sendsTo = {};
    for (var ix = 0; ix < source_streams.length; ix++) {
      source_streams[ix].listen(this);
    }
  }
});
