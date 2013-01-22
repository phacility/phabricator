/**
 * @requires javelin-install
 * @provides javelin-typeahead-normalizer
 * @javelin
 */

/**
 * @group control
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
      return ('' + str)
        .toLocaleLowerCase()
        .replace(/[\.,-\/#!$%\^&\*;:{}=\-_`~()]/g, '')
        .replace(/ +/g, ' ')
        .replace(/^\s*|\s*$/g, '');
    }
  }
});
