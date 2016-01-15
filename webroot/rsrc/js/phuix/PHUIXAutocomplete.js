/**
 * @provides phuix-autocomplete
 * @requires javelin-install
 *           javelin-dom
 *           phuix-icon-view
 *           phabricator-prefab
 */

JX.install('PHUIXAutocomplete', {

  construct: function() {
    this._map = {};
    this._datasources = {};
    this._listNodes = [];
  },

  members: {
    _area: null,
    _active: false,
    _cursorHead: null,
    _cursorTail: null,
    _pixelHead: null,
    _pixelTail: null,
    _map: null,
    _datasource: null,
    _datasources: null,
    _value: null,
    _node: null,
    _echoNode: null,
    _listNode: null,
    _promptNode: null,
    _focus: null,
    _focusRef: null,
    _listNodes: null,

    setArea: function(area) {
      this._area = area;
      return this;
    },

    addAutocomplete: function(code, spec) {
      this._map[code] = spec;
      return this;
    },

    start: function() {
      var area = this._area;

      JX.DOM.listen(area, 'keypress', null, JX.bind(this, this._onkeypress));

      JX.DOM.listen(
        area,
        ['click', 'keyup', 'keydown', 'keypress'],
        null,
        JX.bind(this, this._update));

      var select = JX.bind(this, this._onselect);
      JX.DOM.listen(this._getNode(), 'click', 'typeahead-result', select);

      var device = JX.bind(this, this._ondevice);
      JX.Stratcom.listen('phabricator-device-change', null, device);

      // When the user clicks away from the textarea, deactivate.
      var deactivate = JX.bind(this, this._deactivate);
      JX.DOM.listen(area, 'blur', null, deactivate);
    },

    _getSpec: function() {
      return this._map[this._active];
    },

    _ondevice: function() {
      if (JX.Device.getDevice() != 'desktop') {
        this._deactivate();
      }
    },

    _activate: function(code) {
      if (JX.Device.getDevice() != 'desktop') {
        return;
      }

      if (!this._map[code]) {
        return;
      }

      var area = this._area;
      var range = JX.TextAreaUtils.getSelectionRange(area);

      // Check the character immediately before the trigger character. We'll
      // only activate the typeahead if it's something that we think a user
      // might reasonably want to autocomplete after, like a space, newline,
      // or open parenthesis. For example, if a user types "alincoln@",
      // the prior letter will be the last "n" in "alincoln". They are probably
      // typing an email address, not a username, so we don't activate the
      // autocomplete.
      var head = range.start;
      var prior;
      if (head > 1) {
        prior = area.value.substring(head - 2, head - 1);
      } else {
        prior = '<start>';
      }

      switch (prior) {
        case '<start>':
        case ' ':
        case '\n':
        case '\t':
        case '(': // Might be "(@username, what do you think?)".
        case '-': // Might be an unnumbered list.
        case '.': // Might be a numbered list.
        case '|': // Might be a table cell.
          // We'll let these autocomplete.
          break;
        default:
          // We bail out on anything else, since the user is probably not
          // typing a username or project tag.
          return;
      }

      this._cursorHead = head;
      this._cursorTail = range.end;
      this._pixelHead = JX.TextAreaUtils.getPixelDimensions(
        area,
        range.start,
        range.end);

      var spec = this._map[code];
      if (!this._datasources[code]) {
        var datasource = new JX.TypeaheadOnDemandSource(spec.datasourceURI);
        datasource.listen(
          'resultsready',
          JX.bind(this, this._onresults, code));

        datasource.setTransformer(JX.bind(this, this._transformresult));

        this._datasources[code] = datasource;
      }

      this._datasource = this._datasources[code];
      this._active = code;

      var head_icon = new JX.PHUIXIconView()
        .setIcon(spec.headerIcon)
        .getNode();
      var head_text = spec.headerText;

      var node = this._getPromptNode();
      JX.DOM.setContent(node, [head_icon, head_text]);
    },

    _transformresult: function(fields) {
      var map = JX.Prefab.transformDatasourceResults(fields);

      var icon;
      if (map.icon) {
        icon = new JX.PHUIXIconView()
          .setIcon(map.icon)
          .getNode();
      }

      map.display = [icon, map.displayName];

      return map;
    },

    _deactivate: function() {
      var node = this._getNode();
      JX.DOM.hide(node);

      this._active = false;
    },

    _onkeypress: function(e) {
      var r = e.getRawEvent();

      if (r.metaKey || r.altKey || r.ctrlKey) {
        return;
      }

      var code = r.charCode;
      if (this._map[code]) {
        setTimeout(JX.bind(this, this._activate, code), 0);
      }
    },

    _onresults: function(code, nodes, value) {
      if (code !== this._active) {
        return;
      }

      if (value !== this._value) {
        return;
      }

      var list = this._getListNode();
      JX.DOM.setContent(list, nodes);

      this._listNodes = nodes;

      var old_ref = this._focusRef;
      this._clearFocus();

      for (var ii = 0; ii < nodes.length; ii++) {
        if (nodes[ii].rel == old_ref) {
          this._setFocus(ii);
          break;
        }
      }

      if (this._focus === null && nodes.length) {
        this._setFocus(0);
      }
    },

    _setFocus: function(idx) {
      if (!this._listNodes[idx]) {
        this._clearFocus();
        return false;
      }

      if (this._focus !== null) {
        JX.DOM.alterClass(this._listNodes[this._focus], 'focused', false);
      }

      this._focus = idx;
      this._focusRef = this._listNodes[idx].rel;
      JX.DOM.alterClass(this._listNodes[idx], 'focused', true);

      return true;
    },

    _changeFocus: function(delta) {
      if (this._focus === null) {
        return false;
      }

      return this._setFocus(this._focus + delta);
    },

    _clearFocus: function() {
      this._focus = null;
      this._focusRef = null;
    },

    _onselect: function (e) {
      var target = e.getNode('typeahead-result');

      for (var ii = 0; ii < this._listNodes.length; ii++) {
        if (this._listNodes[ii] === target) {
          this._setFocus(ii);
          this._autocomplete();
          break;
        }
      }

      this._deactivate();
      e.kill();
    },

    _getSuffixes: function() {
      return[' ', ':', ','];
    },

    _trim: function(str) {
      var suffixes = this._getSuffixes();
      for (var ii = 0; ii < suffixes.length; ii++) {
        if (str.substring(str.length - suffixes[ii].length) == suffixes[ii]) {
          str = str.substring(0, str.length - suffixes[ii].length);
        }
      }
      return str;
    },

    _update: function(e) {
      if (!this._active) {
        return;
      }

      var special = e.getSpecialKey();

      // Deactivate if the user types escape.
      if (special == 'esc') {
        this._deactivate();
        e.kill();
        return;
      }

      var area = this._area;

      if (e.getType() == 'keydown') {
        if (special == 'up' || special == 'down') {
          var delta = (special == 'up') ? -1 : +1;
          if (!this._changeFocus(delta)) {
            this._deactivate();
          }
          e.kill();
          return;
        }
      }

      if (special == 'tab' || special == 'return') {
        var r = e.getRawEvent();
        if (r.shiftKey && special == 'tab') {
          // Don't treat "Shift + Tab" as an autocomplete action. Instead,
          // let it through normally so the focus shifts to the previous
          // control.
          this._deactivate();
          return;
        }

        // If we autocomplete, we're done. Otherwise, just eat the event. This
        // happens if you type too fast and try to tab complete before results
        // load.
        if (this._autocomplete()) {
          this._deactivate();
        }

        e.kill();
        return;
      }

      // Deactivate if the user moves the cursor to the left of the assist
      // range. For example, they might press the "left" arrow to move the
      // cursor to the left, or click in the textarea prior to the active
      // range.
      var range = JX.TextAreaUtils.getSelectionRange(area);
      if (range.start < this._cursorHead) {
        this._deactivate();
        return;
      }

      // Deactivate if the user moves the cursor to the right of the assist
      // range. For example, they might click later in the document. If the user
      // is pressing the "right" arrow key, they are not allowed to move the
      // cursor beyond the existing end of the text range. If they are pressing
      // other keys, assume they're typing and allow the tail to move forward
      // one character.
      var margin;
      if (special == 'right') {
        margin = 0;
      } else {
        margin = 1;
      }

      var tail = this._cursorTail;

      if ((range.start > tail + margin) || (range.end > tail + margin)) {
        this._deactivate();
        return;
      }

      this._cursorTail = Math.max(this._cursorTail, range.end);

      var text = area.value.substring(
        this._cursorHead,
        this._cursorTail);

      this._value = text;

      var pixels = JX.TextAreaUtils.getPixelDimensions(
        area,
        range.start,
        range.end);

      var x = this._pixelHead.start.x;
      var y = Math.max(this._pixelHead.end.y, pixels.end.y) + 24;

      var trim = this._trim(text);

      this._datasource.didChange(trim);

      var node = this._getNode();
      node.style.left = x + 'px';
      node.style.top = y + 'px';
      JX.DOM.show(node);

      var echo = this._getEchoNode();
      var hint = trim;
      if (!hint.length) {
        hint = this._getSpec().hintText;
      }

      JX.DOM.setContent(echo, hint);
    },

    _autocomplete: function() {
      if (this._focus === null) {
        return false;
      }

      var area = this._area;
      var head = this._cursorHead;
      var tail = this._cursorTail;

      var text = area.value;

      var ref = this._focusRef;
      var result = this._datasource.getResult(ref);
      if (!result) {
        return false;
      }

      ref = result.autocomplete;
      if (!ref || !ref.length) {
        return false;
      }

      // If the user types a string like "@username:" (with a trailing comma),
      // then presses tab or return to pick the completion, don't destroy the
      // trailing character.
      var suffixes = this._getSuffixes();
      var value = this._value;
      for (var ii = 0; ii < suffixes.length; ii++) {
        var last = value.substring(value.length - suffixes[ii].length);
        if (last == suffixes[ii]) {
          ref += suffixes[ii];
          break;
        }
      }

      area.value = text.substring(0, head - 1) + ref + text.substring(tail);

      var end = head + ref.length;
      JX.TextAreaUtils.setSelectionRange(area, end, end);

      return true;
    },

    _getNode: function() {
      if (!this._node) {
        var head = this._getHeadNode();
        var list = this._getListNode();

        this._node = JX.$N(
          'div',
          {
            className: 'phuix-autocomplete',
            style: {
              display: 'none'
            }
          },
          [head, list]);

        JX.DOM.hide(this._node);

        document.body.appendChild(this._node);
      }
      return this._node;
    },

    _getHeadNode: function() {
      if (!this._headNode) {
        this._headNode = JX.$N(
          'div',
          {
            className: 'phuix-autocomplete-head'
          },
          [
            this._getPromptNode(),
            this._getEchoNode()
          ]);
      }

      return this._headNode;
    },

    _getPromptNode: function() {
      if (!this._promptNode) {
        this._promptNode = JX.$N(
          'span',
          {
            className: 'phuix-autocomplete-prompt',
          });
      }
      return this._promptNode;
    },

    _getEchoNode: function() {
      if (!this._echoNode) {
        this._echoNode = JX.$N(
          'span',
          {
            className: 'phuix-autocomplete-echo'
          });
      }
      return this._echoNode;
    },

    _getListNode: function() {
      if (!this._listNode) {
        this._listNode = JX.$N(
          'div',
          {
            className: 'phuix-autocomplete-list'
          });
      }
      return this._listNode;
    }

  }

});
