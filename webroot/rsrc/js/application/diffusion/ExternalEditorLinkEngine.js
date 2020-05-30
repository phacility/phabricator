/**
 * @provides javelin-external-editor-link-engine
 * @requires javelin-install
 * @javelin
 */

JX.install('ExternalEditorLinkEngine', {

  properties: {
    template: null,
    variables: null
  },

  members: {
    newURI: function() {
      var template = this.getTemplate();
      var variables = this.getVariables();

      var parts = [];
      for (var ii = 0; ii < template.length; ii++) {
        var part = template[ii];
        var value = part.value;

        if (part.type === 'literal') {
          parts.push(value);
          continue;
        }

        if (part.type === 'variable') {
          if (variables.hasOwnProperty(value)) {
            var replacement = variables[value];
            replacement = encodeURIComponent(replacement);
            parts.push(replacement);
          }
        }
      }

      return parts.join('');
    }
  }
});
