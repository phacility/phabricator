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

    _getSuggestionNode: function(row) {
      try {
        return JX.DOM.find(row, 'textarea', 'inline-content-suggestion');
      } catch (ex) {
        return null;
      }
    }
  }

});
