/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-vector
 *           javelin-util
 * @provides javelin-typeahead
 * @javelin
 */

/**
 * A typeahead is a UI component similar to a text input, except that it
 * suggests some set of results (like friends' names, common searches, or
 * repository paths) as the user types them. Familiar examples of this UI
 * include Google Suggest, the Facebook search box, and OS X's Spotlight
 * feature.
 *
 * To build a @{JX.Typeahead}, you need to do four things:
 *
 *  1. Construct it, passing some DOM nodes for it to attach to. See the
 *     constructor for more information.
 *  2. Attach a datasource by calling setDatasource() with a valid datasource,
 *     often a @{JX.TypeaheadPreloadedSource}.
 *  3. Configure any special options that you want.
 *  4. Call start().
 *
 * If you do this correctly, a dropdown menu should appear under the input as
 * the user types, suggesting matching results.
 *
 * @task build        Building a Typeahead
 * @task datasource   Configuring a Datasource
 * @task config       Configuring Options
 * @task start        Activating a Typeahead
 * @task control      Controlling Typeaheads from Javascript
 * @task internal     Internal Methods
 */
JX.install('Typeahead', {
  /**
   * Construct a new Typeahead on some "hardpoint". At a minimum, the hardpoint
   * should be a ##<div>## with "position: relative;" wrapped around a text
   * ##<input>##. The typeahead's dropdown suggestions will be appended to the
   * hardpoint in the DOM. Basically, this is the bare minimum requirement:
   *
   *   LANG=HTML
   *   <div style="position: relative;">
   *     <input type="text" />
   *   </div>
   *
   * Then get a reference to the ##<div>## and pass it as 'hardpoint', and pass
   * the ##<input>## as 'control'. This will enhance your boring old
   * ##<input />## with amazing typeahead powers.
   *
   * On the Facebook/Tools stack, ##<javelin:typeahead-template />## can build
   * this for you.
   *
   * @param Node  "Hardpoint", basically an anchorpoint in the document which
   *              the typeahead can append its suggestion menu to.
   * @param Node? Actual ##<input />## to use; if not provided, the typeahead
   *              will just look for a (solitary) input inside the hardpoint.
   * @task build
   */
  construct : function(hardpoint, control) {
    this._hardpoint = hardpoint;
    this._control = control || JX.DOM.find(hardpoint, 'input');

    this._root = JX.$N(
      'div',
      {className: 'jx-typeahead-results'});
    this._display = [];

    this._listener = JX.DOM.listen(
      this._control,
      ['focus', 'blur', 'keypress', 'keydown', 'input'],
      null,
      JX.bind(this, this.handleEvent));

    JX.DOM.listen(
      this._root,
      ['mouseover', 'mouseout'],
      null,
      JX.bind(this, this._onmouse));

    JX.DOM.listen(
      this._root,
      'mousedown',
      'tag:a',
      JX.bind(this, function(e) {
        if (!e.isRightButton()) {
          this._choose(e.getNode('tag:a'));
        }
      }));

  },

  events : ['choose', 'query', 'start', 'change', 'show'],

  properties : {

    /**
     * Boolean. If true (default), the user is permitted to submit the typeahead
     * with a custom or empty selection. This is a good behavior if the
     * typeahead is attached to something like a search input, where the user
     * might type a freeform query or select from a list of suggestions.
     * However, sometimes you require a specific input (e.g., choosing which
     * user owns something), in which case you can prevent null selections.
     *
     * @task config
     */
    allowNullSelection : true
  },

  members : {
    _root : null,
    _control : null,
    _hardpoint : null,
    _listener : null,
    _value : null,
    _stop : false,
    _focus : -1,
    _focused : false,
    _placeholderVisible : false,
    _placeholder : null,
    _display : null,
    _datasource : null,
    _waitingListener : null,
    _readyListener : null,
    _completeListener : null,

    /**
     * Activate your properly configured typeahead. It won't do anything until
     * you call this method!
     *
     * @task start
     * @return void
     */
    start : function() {
      this.invoke('start');
      if (__DEV__) {
        if (!this._datasource) {
          throw new Error(
            'JX.Typeahead.start(): ' +
            'No datasource configured. Create a datasource and call ' +
            'setDatasource().');
        }
      }
      this.updatePlaceholder();
    },


    /**
     * Configure a datasource, which is where the Typeahead gets suggestions
     * from. See @{JX.TypeaheadDatasource} for more information. You must
     * provide exactly one datasource.
     *
     * @task datasource
     * @param JX.TypeaheadDatasource The datasource which the typeahead will
     *                               draw from.
     */
    setDatasource : function(datasource) {
      if (this._datasource) {
        this._datasource.unbindFromTypeahead();
        this._waitingListener.remove();
        this._readyListener.remove();
        this._completeListener.remove();
      }
      this._waitingListener = datasource.listen(
        'waiting',
        JX.bind(this, this.waitForResults));

      this._readyListener = datasource.listen(
        'resultsready',
        JX.bind(this, this.showResults));

      this._completeListener = datasource.listen(
        'complete',
        JX.bind(this, this.doneWaitingForResults));

      datasource.bindToTypeahead(this);
      this._datasource = datasource;
    },

    getDatasource : function() {
      return this._datasource;
    },

    /**
     * Override the <input /> selected in the constructor with some other input.
     * This is primarily useful when building a control on top of the typeahead,
     * like @{JX.Tokenizer}.
     *
     * @task config
     * @param node An <input /> node to use as the primary control.
     */
    setInputNode : function(input) {
      this._control = input;
      return this;
    },


    /**
     * Hide the typeahead's dropdown suggestion menu.
     *
     * @task control
     * @return void
     */
    hide : function() {
      this._changeFocus(Number.NEGATIVE_INFINITY);
      this._display = [];
      this._moused = false;
      JX.DOM.hide(this._root);
    },


    /**
     * Show a given result set in the typeahead's dropdown suggestion menu.
     * Normally, you don't call this method directly. Usually it gets called
     * in response to events from the datasource you have configured.
     *
     * @task   control
     * @param  list     List of ##<a />## tags to show as suggestions/results.
     * @param  string   The query this result list corresponds to.
     * @return void
     */
    showResults : function(results, value) {
      if (value != this._value) {
        // This result list is for an old query, and no longer represents
        // the input state of the typeahead.

        // For example, the user may have typed "dog", and then they delete
        // their query and type "cat", and then the "dog" results arrive from
        // the source.

        // Another case is that the user made a selection in a tokenizer,
        // and then results returned. However, the typeahead is now empty, and
        // we don't want to pop it back open.

        // In all cases, just throw these results away. They are no longer
        // relevant.
        return;
      }

      var obj = {show: results};
      var e = this.invoke('show', obj);

      // If the user has an element focused, store the value before we redraw.
      // After we redraw, try to select the same element if it still exists in
      // the list. This prevents redraws from disrupting keyboard element
      // selection.
      var old_focus = null;
      if (this._focus >= 0 && this._display[this._focus]) {
        old_focus = this._display[this._focus].name;
      }

      // Note that the results list may have been update by the "show" event
      // listener. Non-result node (e.g. divider or label) may have been
      // inserted.
      JX.DOM.setContent(this._root, results);
      this._display = JX.DOM.scry(this._root, 'a', 'typeahead-result');

      if (this._display.length && !e.getPrevented()) {
        this._changeFocus(Number.NEGATIVE_INFINITY);
        var d = JX.Vector.getDim(this._hardpoint);
        d.x = 0;
        d.setPos(this._root);
        if (this._root.parentNode !== this._hardpoint) {
          this._hardpoint.appendChild(this._root);
        }
        JX.DOM.show(this._root);

        // If we had a node focused before, look for a node with the same value
        // and focus it.
        if (old_focus !== null) {
          for (var ii = 0; ii < this._display.length; ii++) {
            if (this._display[ii].name == old_focus) {
              this._focus = ii;
              this._drawFocus();
              break;
            }
          }
        }
      } else {
        this.hide();
        JX.DOM.setContent(this._root, null);
      }
    },

    refresh : function() {
      if (this._stop) {
        return;
      }

      this._value = this._control.value;
      this.invoke('change', this._value);
    },

    /**
     * Show a "waiting for results" UI. We may be showing a partial result set
     * at this time, if the user is extending a query we already have results
     * for.
     *
     * @task control
     * @return void
     */
    waitForResults : function() {
      JX.DOM.alterClass(this._hardpoint, 'jx-typeahead-waiting', true);
    },

    /**
     * Hide the "waiting for results" UI.
     *
     * @task control
     * @return void
     */
    doneWaitingForResults : function() {
      JX.DOM.alterClass(this._hardpoint, 'jx-typeahead-waiting', false);
    },

    /**
     * @task internal
     */
    _onmouse : function(event) {
      this._moused = (event.getType() == 'mouseover');
      this._drawFocus();
    },


    /**
     * @task internal
     */
    _changeFocus : function(d) {
      var n = Math.min(Math.max(-1, this._focus + d), this._display.length - 1);
      if (!this.getAllowNullSelection()) {
        n = Math.max(0, n);
      }
      if (this._focus >= 0 && this._focus < this._display.length) {
        JX.DOM.alterClass(this._display[this._focus], 'focused', false);
      }
      this._focus = n;
      this._drawFocus();
      return true;
    },


    /**
     * @task internal
     */
    _drawFocus : function() {
      var f = this._display[this._focus];
      if (f) {
        JX.DOM.alterClass(f, 'focused', !this._moused);
      }
    },


    /**
     * @task internal
     */
    _choose : function(target) {
      var result = this.invoke('choose', target);
      if (result.getPrevented()) {
        return;
      }

      this._control.value = target.name;
      this.hide();
    },


    /**
     * @task control
     */
    clear : function() {
      this._control.value = '';
      this._value = '';
      this.hide();
    },


    /**
     * @task control
     */
    enable : function() {
      this._control.disabled = false;
      this._stop = false;
    },


    /**
     * @task control
     */
    disable : function() {
      this._control.blur();
      this._control.disabled = true;
      this._stop = true;
    },


    /**
     * @task control
     */
    submit : function() {
      if (this._focus >= 0 && this._display[this._focus]) {
        this._choose(this._display[this._focus]);
        return true;
      } else {
        var result = this.invoke('query', this._control.value);
        if (result.getPrevented()) {
          return true;
        }
      }
      return false;
    },

    setValue : function(value) {
      this._control.value = value;
    },

    getValue : function() {
      return this._control.value;
    },

    /**
     * @task internal
     */
    _update : function(event) {

      if (event.getType() == 'focus') {
        this._focused = true;
        this.updatePlaceholder();
      }

      var k = event.getSpecialKey();
      if (k && event.getType() == 'keydown') {
        switch (k) {
          case 'up':
            if (this._display.length && this._changeFocus(-1)) {
              event.prevent();
            }
            break;
          case 'down':
            if (this._display.length && this._changeFocus(1)) {
              event.prevent();
            }
            break;
          case 'return':
            if (this.submit()) {
              event.prevent();
              return;
            }
            break;
          case 'esc':
            if (this._display.length && this.getAllowNullSelection()) {
              this.hide();
              event.prevent();
            }
            break;
          case 'tab':
            // If the user tabs out of the field, don't refresh.
            return;
        }
      }

      // We need to defer because the keystroke won't be present in the input's
      // value field yet.
      setTimeout(JX.bind(this, function() {
        if (this._value == this._control.value) {
          // The typeahead value hasn't changed.
          return;
        }
        this.refresh();
      }), 0);
    },

    /**
     * This method is pretty much internal but @{JX.Tokenizer} needs access to
     * it for delegation. You might also need to delegate events here if you
     * build some kind of meta-control.
     *
     * Reacts to user events in accordance to configuration.
     *
     * @task internal
     * @param JX.Event User event, like a click or keypress.
     * @return void
     */
    handleEvent : function(e) {
      if (this._stop || e.getPrevented()) {
        return;
      }
      var type = e.getType();
      if (type == 'blur') {
        this._focused = false;
        this.updatePlaceholder();
        this.hide();
      } else {
        this._update(e);
      }
    },

    removeListener : function() {
      if (this._listener) {
        this._listener.remove();
      }
    },


    /**
     * Set a string to display in the control when it is not focused, like
     * "Type a user's name...". This string hints to the user how to use the
     * control.
     *
     * When the string is displayed, the input will have class
     * "jx-typeahead-placeholder".
     *
     * @param string Placeholder string, or null for no placeholder.
     * @return this
     *
     * @task config
     */
    setPlaceholder : function(string) {
      this._placeholder = string;
      this.updatePlaceholder();
      return this;
    },


    /**
     * Update the control to either show or hide the placeholder text as
     * necessary.
     *
     * @return void
     * @task internal
     */
    updatePlaceholder : function() {

      if (this._placeholderVisible) {
        // If the placeholder is visible, we want to hide if the control has
        // been focused or the placeholder has been removed.
        if (this._focused || !this._placeholder) {
          this._placeholderVisible = false;
          this._control.value = '';
        }
      } else if (!this._focused) {
        // If the placeholder is not visible, we want to show it if the control
        // has benen blurred.
        if (this._placeholder && !this._control.value) {
          this._placeholderVisible = true;
        }
      }

      if (this._placeholderVisible) {
        // We need to resist the Tokenizer wiping the input on blur.
        this._control.value = this._placeholder;
      }

      JX.DOM.alterClass(
        this._control,
        'jx-typeahead-placeholder',
        this._placeholderVisible);
    }
  }
});
