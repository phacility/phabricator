/**
 * @provides javelin-behavior-maniphest-transaction-controls
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-prefab
 */

JX.behavior('maniphest-transaction-controls', function(config) {

  var tokenizers = {};

  for (var k in config.tokenizers) {
    var tconfig = config.tokenizers[k];
    tokenizers[k] = JX.Prefab.buildTokenizer(tconfig).tokenizer;
    tokenizers[k].start();
  }

  JX.DOM.listen(
    JX.$(config.select),
    'change',
    null,
    function() {
      for (var k in config.controlMap) {
        if (k == JX.$(config.select).value) {
          JX.DOM.show(JX.$(config.controlMap[k]));
          if (tokenizers[k]) {
            tokenizers[k].refresh();
          }
        } else {
          JX.DOM.hide(JX.$(config.controlMap[k]));
        }
      }
    });

});
