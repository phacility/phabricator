/**
 * Javelin Reactive functions to work with the DOM.
 * @provides javelin-reactor-dom
 * @requires javelin-dom
 *           javelin-dynval
 *           javelin-reactor
 *           javelin-reactornode
 *           javelin-install
 *           javelin-util
 * @javelin
 */
JX.install('RDOM', {
  statics : {
    _time : null,
    /**
     * DynVal of the current time in milliseconds.
     */
    time : function() {
      if (JX.RDOM._time === null) {
        var time = new JX.ReactorNode([], JX.id);
        window.setInterval(function() {
          time.forceSendValue(JX.now());
        }, 100);
        JX.RDOM._time = new JX.DynVal(time, JX.now());
      }
      return JX.RDOM._time;
    },

    /**
     * Given a DynVal[String], return a DOM text node whose value tracks it.
     */
    $DT : function(dyn_string) {
      var node = document.createTextNode(dyn_string.getValueNow());
      dyn_string.transform(function(s) { node.data = s; });
      return node;
    },

    _recvEventPulses : function(node, event) {
      var reactor_node = new JX.ReactorNode([], JX.id);
      var no_path = null;
      JX.DOM.listen(
        node,
        event,
        no_path,
        JX.bind(reactor_node, reactor_node.forceSendValue)
      );

      reactor_node.setGraphID(JX.DOM.uniqID(node));
      return reactor_node;
    },

    _recvChangePulses : function(node) {
      return JX.RDOM._recvEventPulses(node, 'change').transform(function() {
        return node.value;
      });
    },


    /**
     * Sets up a bidirectional DynVal for a node.
     * @param node :: DOM Node
     * @param inPulsesFn :: DOM Node -> ReactorNode
     * @param inDynValFn :: DOM Node -> ReactorNode -> DynVal
     * @param outFn :: ReactorNode -> DOM Node
     */
    _bidi : function(node, inPulsesFn, inDynValFn, outFn) {
      var inPulses = inPulsesFn(node);
      var inDynVal = inDynValFn(node, inPulses);
      outFn(inDynVal.getChanges(), node);
      inDynVal.getChanges().listen(inPulses);
      return inDynVal;
    },

    /**
     * ReactorNode[String] of the incoming values of a radio group.
     * @param Array of DOM elements, all the radio buttons in a group.
     */
    _recvRadioPulses : function(buttons) {
      var ins = [];
      for (var ii = 0; ii < buttons.length; ii++) {
        ins.push(JX.RDOM._recvChangePulses(buttons[ii]));
      }
      return new JX.ReactorNode(ins, JX.id);
    },

    /**
     * DynVal[String] of the incoming values of a radio group.
     * pulses is a ReactorNode[String] of the incoming values of the group
     */
    _recvRadio : function(buttons, pulses) {
      var init = '';
      for (var ii = 0; ii < buttons.length; ii++) {
        if (buttons[ii].checked) {
          init = buttons[ii].value;
          break;
        }
      }

      return new JX.DynVal(pulses, init);
    },

    /**
     * Send the pulses from the ReactorNode[String] to the radio group.
     * Sending an invalid value will result in a log message in __DEV__.
     */
    _sendRadioPulses : function(rnode, buttons) {
      return rnode.transform(function(val) {
        var found;
        if (__DEV__) {
          found = false;
        }

        for (var ii = 0; ii < buttons.length; ii++) {
          if (buttons[ii].value == val) {
            buttons[ii].checked = true;
            if (__DEV__) {
              found = true;
            }
          }
        }

        if (__DEV__) {
          if (!found) {
            throw new Error('Mismatched radio button value');
          }
        }
      });
    },

    /**
     * Bidirectional DynVal[String] for a radio group.
     * Sending an invalid value will result in a log message in __DEV__.
     */
    radio : function(input) {
      return JX.RDOM._bidi(
        input,
        JX.RDOM._recvRadioPulses,
        JX.RDOM._recvRadio,
        JX.RDOM._sendRadioPulses
      );
    },

    /**
     * ReactorNode[Boolean] of the values of the checkbox when it changes.
     */
    _recvCheckboxPulses : function(checkbox) {
      return JX.RDOM._recvChangePulses(checkbox).transform(function(val) {
        return Boolean(val);
      });
    },

    /**
     * DynVal[Boolean] of the value of a checkbox.
     */
    _recvCheckbox : function(checkbox, pulses) {
      return new JX.DynVal(pulses, Boolean(checkbox.checked));
    },

    /**
     * Send the pulses from the ReactorNode[Boolean] to the checkbox
     */
    _sendCheckboxPulses : function(rnode, checkbox) {
      return rnode.transform(function(val) {
        if (__DEV__) {
          if (!(val === true || val === false)) {
            throw new Error('Send boolean values to checkboxes.');
          }
        }

        checkbox.checked = val;
      });
    },

    /**
     * Bidirectional DynVal[Boolean] for a checkbox.
     */
    checkbox : function(input) {
      return JX.RDOM._bidi(
        input,
        JX.RDOM._recvCheckboxPulses,
        JX.RDOM._recvCheckbox,
        JX.RDOM._sendCheckboxPulses
      );
    },

    /**
     * ReactorNode[String] of the changing values of a text input.
     */
    _recvInputPulses : function(input) {
      // This misses advanced changes like paste events.
      var live_changes = [
        JX.RDOM._recvChangePulses(input),
        JX.RDOM._recvEventPulses(input, 'keyup'),
        JX.RDOM._recvEventPulses(input, 'keypress'),
        JX.RDOM._recvEventPulses(input, 'keydown')
      ];

      return new JX.ReactorNode(live_changes, function() {
        return input.value;
      });
    },

    /**
     * DynVal[String] of the value of a text input.
     */
    _recvInput : function(input, pulses) {
      return new JX.DynVal(pulses, input.value);
    },

    /**
     * Send the pulses from the ReactorNode[String] to the input
     */
    _sendInputPulses : function(rnode, input) {
      var result = rnode.transform(function(val) {
        input.value = val;
      });
      result.setGraphID(JX.DOM.uniqID(input));
      return result;
    },


    /**
     * Bidirectional DynVal[String] for a text input.
     */
    input : function(input) {
      return JX.RDOM._bidi(
        input,
        JX.RDOM._recvInputPulses,
        JX.RDOM._recvInput,
        JX.RDOM._sendInputPulses
      );
    },

    /**
     * ReactorNode[String] of the incoming changes in value of a select element.
     */
    _recvSelectPulses : function(select) {
      return JX.RDOM._recvChangePulses(select);
    },

    /**
     * DynVal[String] of the value of a select element.
     */
    _recvSelect : function(select, pulses) {
      return new JX.DynVal(pulses, select.value);
    },

    /**
     * Send the pulses from the ReactorNode[String] to the select.
     * Sending an invalid value will result in a log message in __DEV__.
     */
    _sendSelectPulses : function(rnode, select) {
      return rnode.transform(function(val) {
        select.value = val;

        if (__DEV__) {
          if (select.value !== val) {
            throw new Error('Mismatched select value');
          }
        }
      });
    },

    /**
     * Bidirectional DynVal[String] for the value of a select.
     */
    select : function(select) {
      return JX.RDOM._bidi(
        select,
        JX.RDOM._recvSelectPulses,
        JX.RDOM._recvSelect,
        JX.RDOM._sendSelectPulses
      );
    },

    /**
     * ReactorNode[undefined] that fires when a button is clicked.
     */
    clickPulses : function(button) {
      return JX.RDOM._recvEventPulses(button, 'click').transform(function() {
        return null;
      });
    },

    /**
     * ReactorNode[Boolean] of whether the mouse is over a target.
     */
    _recvIsMouseOverPulses : function(target) {
      var mouseovers = JX.RDOM._recvEventPulses(target, 'mouseover').transform(
        function() {
          return true;
        });
      var mouseouts = JX.RDOM._recvEventPulses(target, 'mouseout').transform(
        function() {
          return false;
        });

      return new JX.ReactorNode([mouseovers, mouseouts], JX.id);
    },

    /**
     * DynVal[Boolean] of whether the mouse is over a target.
     */
    isMouseOver : function(target) {
      // Not worth it to initialize this properly.
      return new JX.DynVal(JX.RDOM._recvIsMouseOverPulses(target), false);
    },

    /**
     * ReactorNode[Boolean] of whether an element has the focus.
     */
    _recvHasFocusPulses : function(target) {
      var focuses = JX.RDOM._recvEventPulses(target, 'focus').transform(
        function() {
          return true;
        });
      var blurs = JX.RDOM._recvEventPulses(target, 'blur').transform(
        function() {
          return false;
        });

      return new JX.ReactorNode([focuses, blurs], JX.id);
    },

    /**
     * DynVal[Boolean] of whether an element has the focus.
     */
    _recvHasFocus : function(target) {
      var is_focused_now = (target === document.activeElement);
      return new JX.DynVal(JX.RDOM._recvHasFocusPulses(target), is_focused_now);
    },

    _sendHasFocusPulses : function(rnode, target) {
      rnode.transform(function(should_focus) {
        if (should_focus) {
          target.focus();
        } else {
          target.blur();
        }
        return should_focus;
      });
    },

    /**
     * Bidirectional DynVal[Boolean] of whether an element has the focus.
     */
    hasFocus : function(target) {
      return JX.RDOM._bidi(
        target,
        JX.RDOM._recvHasFocusPulses,
        JX.RDOM._recvHasFocus,
        JX.RDOM._sendHasFocusPulses
      );
    },

    /**
     * Send a CSS class from a DynVal to a node
     */
    sendClass : function(dynval, node, className) {
      return dynval.transform(function(add) {
        JX.DOM.alterClass(node, className, add);
      });
    },

    /**
     * Dynamically attach a set of DynVals to a DOM node's properties as
     * specified by props.
     * props: {left: someDynVal, style: {backgroundColor: someOtherDynVal}}
     */
    sendProps : function(node, props) {
      var dynvals = [];
      var keys = [];
      var style_keys = [];
      for (var key in props) {
        keys.push(key);
        if (key === 'style') {
          for (var style_key in props[key]) {
            style_keys.push(style_key);
            dynvals.push(props[key][style_key]);
            node.style[style_key] = props[key][style_key].getValueNow();
          }
        } else {
          dynvals.push(props[key]);
          node[key] = props[key].getValueNow();
        }
      }

      return JX.Reactor.lift(JX.bind(null, function(keys, style_keys, node) {
        var args = JX.$A(arguments).slice(3);

        for (var ii = 0; ii < args.length; ii++) {
          if (keys[ii] === 'style') {
            for (var jj = 0; jj < style_keys.length; jj++) {
              node.style[style_keys[jj]] = args[ii];
              ii++;
            }
            ii--;
          } else {
            node[keys[ii]] = args[ii];
          }
        }
      }, keys, style_keys, node), dynvals);
    }
  }
});
