/**
 * @provides phabricator-diff-inline-content-state
 * @requires javelin-dom
 * @javelin
 */

JX.install('DiffInlineContentState', {

  construct : function() {

  },

  properties: {
    text: null,
    suggestionText: null,
    hasSuggestion: false
  },

  members: {
    readForm: function(row) {
      var node;

      try {
        node = JX.DOM.find(row, 'textarea', 'inline-content-text');
        this.setText(node.value);
      } catch (ex) {
        this.setText(null);
      }

      node = this._getSuggestionNode(row);
      if (node) {
        this.setSuggestionText(node.value);
      } else {
        this.setSuggestionText(null);
      }

      return this;
    },

    getWireFormat: function() {
      return {
        text: this.getText(),
        suggestionText: this.getSuggestionText(),
        hasSuggestion: this.getHasSuggestion()
      };
    },

    readWireFormat: function(map) {
      this.setText(map.text || null);
      this.setSuggestionText(map.suggestionText || null);
      this.setHasSuggestion(!!map.hasSuggestion);

      return this;
    },

    getTextForQuote: function() {
      var text = this.getText();
      text = '> ' + text.replace(/\n/g, '\n> ') + '\n\n';
      return text;
    },

    isStateEmpty: function() {
      return (this.isTextEmpty() && this.isSuggestionEmpty());
    },

    isTextEmpty: function() {
      var text = this.getText();
      if (text === null) {
        return true;
      }

      if (this._isStringSimilar(text, '')) {
        return true;
      }

      return false;
    },

    isSuggestionEmpty: function() {
      if (!this.getHasSuggestion()) {
        return true;
      }

      var suggestion = this.getSuggestionText();
      if (suggestion === null) {
        return true;
      }

      if (this._isStringSimilar(suggestion, '')) {
        return true;
      }

      return false;
    },

    isTextSimilar: function(v) {
      if (!v) {
        return false;
      }

      var us = this.getText();
      var vs = v.getText();

      return this._isStringSimilar(us, vs);
    },

    isSuggestionSimilar: function(v) {
      // If we don't have a comparison state, treat them as dissimilar. This
      // is expected to occur in old inline comments that did not save an
      // initial state.

      if (!v) {
        return false;
      }

      var us = this.getSuggestionText();
      var vs = v.getSuggestionText();

      return this._isStringSimilar(us, vs);
    },

    _isStringSimilar: function(u, v) {
      u = u || '';
      v = v || '';
      return (u === v);
    },

    _getSuggestionNode: function(row) {
      try {
        return JX.DOM.find(row, 'textarea', 'inline-content-suggestion');
      } catch (ex) {
        return null;
      }
    }
  }

});
