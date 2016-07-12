/**
 * @requires javelin-install
 * @provides javelin-typeahead-normalizer
 * @javelin
 */

JX.install('TypeaheadNormalizer', {
  statics : {
    /**
     * Normalizes a string by lowercasing it and stripping out extra spaces
     * and punctuation.
     *
     * @param string
     * @return string Normalized string.
     */
    normalize : function(str) {

      // NOTE: We specifically normalize "(" and ")" into spaces so that
      // we can match tokenizer functions like "members(project)".

      return ('' + str)
        .toLocaleLowerCase()
        .replace(/[\.,\/#!$%\^&\*;:{}=_`~]/g, '')
        .replace(/[-\[\]\(\)]/g, ' ')
        .replace(/ +/g, ' ')
        .replace(/^\s*|\s*$/g, '');
    }
  }
});
