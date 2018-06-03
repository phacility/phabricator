/**
 * @requires javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-install
 * @provides javelin-tokenizer
 * @javelin
 */

/**
 * A tokenizer is a UI component similar to a text input, except that it
 * allows the user to input a list of items ("tokens"), generally from a fixed
 * set of results. A familiar example of this UI is the "To:" field of most
 * email clients, where the control autocompletes addresses from the user's
 * address book.
 *
 * @{JX.Tokenizer} is built on top of @{JX.Typeahead}, and primarily adds the
 * ability to choose multiple items.
 *
 * To build a @{JX.Tokenizer}, you need to do four things:
 *
 *  1. Construct it, padding a DOM node for it to attach to. See the constructor
 *     for more information.
 *  2. Build a {@JX.Typeahead} and configure it with setTypeahead().
 *  3. Configure any special options you want.
 *  4. Call start().
 *
 * If you do this correctly, the input should suggest items and enter them as
 * tokens as the user types.
 *
 * When the tokenizer is focused, the CSS class `jx-tokenizer-container-focused`
 * is added to the container node.
 */
JX.install('Tokenizer', {
  construct : function(containerNode) {
    this._containerNode = containerNode;
  },

  events : [
    /**
     * Emitted when the value of the tokenizer changes, similar to an 'onchange'
     * from a <select />.
     */
    'change'],

  properties : {
    limit : null,
    renderTokenCallback : null,
    browseURI: null,
    disabled: false
  },

  members : {
    _containerNode : null,
    _root : null,
    _frame: null,
    _focus : null,
    _orig : null,
    _typeahead : null,
    _tokenid : 0,
    _tokens : null,
    _tokenMap : null,
    _initialValue : null,
    _seq : 0,
    _lastvalue : null,
    _placeholder : null,

    start : function() {
      if (this.getDisabled()) {
        JX.DOM.alterClass(this._containerNode, 'disabled-control', true);
        return;
      }

      if (__DEV__) {
        if (!this._typeahead) {
          throw new Error(
            'JX.Tokenizer.start(): ' +
            'No typeahead configured! Use setTypeahead() to provide a ' +
            'typeahead.');
        }
      }

      this._orig = JX.DOM.find(this._containerNode, 'input', 'tokenizer-input');
      this._tokens = [];
      this._tokenMap = {};

      try {
        this._frame = JX.DOM.findAbove(this._orig, 'div', 'tokenizer-frame');
      } catch (e) {
        // Ignore, this tokenizer doesn't have a frame.
      }

      if (this._frame) {
        JX.DOM.alterClass(this._frame, 'has-browse', !!this.getBrowseURI());
        JX.DOM.listen(
          this._frame,
          'click',
          'tokenizer-browse',
          JX.bind(this, this._onbrowse));
      }

      var focus = this.buildInput(this._orig.value);
      this._focus = focus;

      var input_container = JX.DOM.scry(
        this._containerNode,
        'div',
        'tokenizer-input-container'
      );
      input_container = input_container[0] || this._containerNode;

      JX.DOM.listen(
        focus,
        ['click', 'focus', 'blur', 'keydown', 'keypress', 'paste'],
        null,
        JX.bind(this, this.handleEvent));

      // NOTE: Safari on the iPhone does not normally delegate click events on
      // <div /> tags. This causes the event to fire. We want a click (in this
      // case, a touch) anywhere in the div to trigger this event so that we
      // can focus the input. Without this, you must tap an arbitrary area on
      // the left side of the input to focus it.
      //
      // http://www.quirksmode.org/blog/archives/2010/09/click_event_del.html
      input_container.onclick = JX.bag;

      JX.DOM.listen(
        input_container,
        'click',
        null,
        JX.bind(
          this,
          function(e) {
            if (e.getNode('remove')) {
              this._remove(e.getNodeData('token').key, true);
            } else if (e.getTarget() == this._root) {
              this.focus();
            }
          }));

      var root = JX.$N('div');
      root.id = this._orig.id;
      JX.DOM.alterClass(root, 'jx-tokenizer', true);
      root.style.cursor = 'text';
      this._root = root;

      root.appendChild(focus);

      var typeahead = this._typeahead;
      typeahead.setInputNode(this._focus);
      typeahead.start();

      setTimeout(JX.bind(this, function() {
        var container = this._orig.parentNode;
        JX.DOM.setContent(container, root);
        var map = this._initialValue || {};
        for (var k in map) {
          this.addToken(k, map[k]);
        }
        JX.DOM.appendContent(
          root,
          JX.$N('div', {style: {clear: 'both'}})
        );
        this._redraw();
      }), 0);
    },

    setInitialValue : function(map) {
      this._initialValue = map;
      return this;
    },

    setTypeahead : function(typeahead) {

      typeahead.setAllowNullSelection(false);
      typeahead.removeListener();

      typeahead.listen(
        'choose',
        JX.bind(this, function(result) {
          JX.Stratcom.context().prevent();
          if (this.addToken(result.rel, result.name)) {
            if (this.shouldHideResultsOnChoose()) {
              this._typeahead.hide();
            }
            this._typeahead.clear();
            this._redraw();
            this.focus();
          }
        })
      );

      typeahead.listen(
        'query',
        JX.bind(
          this,
          function(query) {

          // TODO: We should emit a 'query' event here to allow the caller to
          // generate tokens on the fly, e.g. email addresses or other freeform
          // or algorithmic tokens.

          // Then do this if something handles the event.
          // this._focus.value = '';
          // this._redraw();
          // this.focus();

          if (query.length) {
            // Prevent this event if there's any text, so that we don't submit
            // the form (either we created a token or we failed to create a
            // token; in either case we shouldn't submit). If the query is
            // empty, allow the event so that the form submission takes place.
            JX.Stratcom.context().prevent();
          }
        }));

      this._typeahead = typeahead;

      return this;
    },

    shouldHideResultsOnChoose : function() {
      return true;
    },

    handleEvent : function(e) {
      this._typeahead.handleEvent(e);
      if (e.getPrevented()) {
        return;
      }

      if (e.getType() == 'click') {
        if (e.getTarget() == this._root) {
          this.focus();
          e.prevent();
          return;
        }
      } else if (e.getType() == 'keydown') {
        this._onkeydown(e);
      } else if (e.getType() == 'blur') {
        this._didblur();

        // Explicitly update the placeholder since we just wiped the field
        // value.
        this._typeahead.updatePlaceholder();
      } else if (e.getType() == 'focus') {
        this._didfocus();
      } else if (e.getType() == 'paste') {
        setTimeout(JX.bind(this, this._redraw), 0);
      }

    },

    refresh : function() {
      this._redraw(true);
      return this;
    },

    _redraw : function(force) {

      // If there are tokens in the tokenizer, never show a placeholder.
      // Otherwise, show one if one is configured.
      if (JX.keys(this._tokenMap).length) {
        this._typeahead.setPlaceholder(null);
      } else {
        this._typeahead.setPlaceholder(this._placeholder);
      }

      var focus = this._focus;

      if (focus.value === this._lastvalue && !force) {
        return;
      }
      this._lastvalue = focus.value;

      var metrics = JX.DOM.textMetrics(
        this._focus,
        'jx-tokenizer-metrics');
      metrics.y = null;
      metrics.x += 24;
      metrics.setDim(focus);

      // NOTE: Once, long ago, we set "focus.value = focus.value;" here to fix
      // an issue with copy/paste in Firefox not redrawing correctly. However,
      // this breaks input of Japanese glyphs in Chrome, and I can't reproduce
      // the original issue in modern Firefox.
      //
      // If future changes muck around with things here, test that Japanese
      // inputs still work. Example:
      //
      //   - Switch to Hiragana mode.
      //   - Type "ni".
      //   - This should produce a glyph, not the value "n".
      //
      // With the assignment, Chrome loses the partial input on the "n" when
      // the value is assigned.
    },

    setPlaceholder : function(string) {
      this._placeholder = string;
      return this;
    },

    addToken : function(key, value) {
      if (key in this._tokenMap) {
        return false;
      }

      var focus = this._focus;
      var root = this._root;
      var token = this.buildToken(key, value);

      this._tokenMap[key] = {
        value : value,
        key : key,
        node : token
      };
      this._tokens.push(key);

      root.insertBefore(token, focus);

      this._didChangeValue();

      return true;
    },

    removeToken : function(key) {
      return this._remove(key, false);
    },

    buildInput: function(value) {
      return JX.$N('input', {
        className: 'jx-tokenizer-input',
        type: 'text',
        autocomplete: 'off',
        value: value
      });
    },

    /**
     * Generate a token based on a key and value. The "token" and "remove"
     * sigils are observed by a listener in start().
     */
    buildToken: function(key, value) {
      var input = JX.$N('input', {
        type: 'hidden',
        value: key,
        name: this._orig.name + '[' + (this._seq++) + ']'
      });

      var remove = JX.$N('a', {
        className: 'jx-tokenizer-x',
        sigil: 'remove'
      }, '\u00d7'); // U+00D7 multiplication sign

      var display_token = value;

      var attrs = {
        className: 'jx-tokenizer-token',
        sigil: 'token',
        meta: {key: key}
      };
      var container = JX.$N('a', attrs);

      var render_callback = this.getRenderTokenCallback();
      if (render_callback) {
        display_token = render_callback(value, key, container);
      }

      JX.DOM.setContent(container, [display_token, input, remove]);

      return container;
    },

    getTokens : function() {
      var result = {};
      for (var key in this._tokenMap) {
        result[key] = this._tokenMap[key].value;
      }
      return result;
    },

    _onkeydown : function(e) {
      var raw = e.getRawEvent();
      if (raw.ctrlKey || raw.metaKey || raw.altKey) {
        return;
      }

      switch (e.getSpecialKey()) {
        case 'tab':
          var completed = this._typeahead.submit();
          if (!completed) {
            this._focus.value = '';
          }
          break;
        case 'delete':
          if (!this._focus.value.length) {
            // In unusual cases, it's possible for us to end up with a token
            // that has the empty string ("") as a value. Support removal of
            // this unusual token.

            var tok;
            while (this._tokens.length) {
              tok = this._tokens.pop();
              if (this._remove(tok, true)) {
                break;
              }
            }
          }
          break;
        case 'return':
          // Don't subject this to token limits.
          break;
        default:
          if (this.getLimit() &&
              JX.keys(this._tokenMap).length == this.getLimit()) {
            e.prevent();
          }
          setTimeout(JX.bind(this, this._redraw), 0);
          break;
      }
    },

    _remove : function(index, focus) {
      if (!this._tokenMap[index]) {
        return false;
      }
      JX.DOM.remove(this._tokenMap[index].node);
      delete this._tokenMap[index];
      this._redraw(true);
      focus && this.focus();

      this._didChangeValue();

      return true;
    },

    _didChangeValue: function() {

      if (this.getBrowseURI()) {
        var button = JX.DOM.find(this._frame, 'a', 'tokenizer-browse');
        JX.DOM.alterClass(button, 'disabled', !!this._shouldLockBrowse());
      }

      this.invoke('change', this);
    },

    _shouldLockBrowse: function() {
      var limit = this.getLimit();

      if (!limit) {
        // If there's no limit, never lock the browse button.
        return false;
      }

      if (limit == 1) {
        // If the limit is 1, we'll replace the current token if the
        // user selects a new one, so we never need to lock the button.
        return false;
      }

      if (limit > JX.keys(this.getTokens()).length) {
        return false;
      }

      return true;
    },

    focus : function() {
      var focus = this._focus;
      JX.DOM.show(focus);

      // NOTE: We must fire this focus event immediately (during event
      // handling) for the iPhone to bring up the keyboard. Previously this
      // focus was wrapped in setTimeout(), but it's unclear why that was
      // necessary. If this is adjusted later, make sure tapping the inactive
      // area of the tokenizer to focus it on the iPhone still brings up the
      // keyboard.

      JX.DOM.focus(focus);
    },

    _didfocus : function() {
      JX.DOM.alterClass(
        this._containerNode,
        'jx-tokenizer-container-focused',
        true);
    },

    _didblur : function() {
      JX.DOM.alterClass(
        this._containerNode,
        'jx-tokenizer-container-focused',
        false);
      this._focus.value = '';
      this._redraw();
    },

    _onbrowse: function(e) {
      e.kill();

      var uri = this.getBrowseURI();
      if (!uri) {
        return;
      }

      if (this._shouldLockBrowse()) {
        return;
      }

      new JX.Workflow(uri, {exclude: JX.keys(this.getTokens()).join(',')})
        .setHandler(
          JX.bind(this, function(r) {
            var source = this._typeahead.getDatasource();

            source.addResult(r.token);
            var result = source.getResult(r.key);

            // If we have a limit of 1 token, replace the current token with
            // the new token if we currently have a token.
            if (this.getLimit() == 1) {
              for (var k in this.getTokens()) {
                this.removeToken(k);
              }
            }

            this.addToken(r.key, result.name);
            this.focus();
          }))
        .start();
    }

  }
});
