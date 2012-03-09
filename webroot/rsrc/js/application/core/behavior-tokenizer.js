/**
 * @provides javelin-behavior-aphront-basic-tokenizer
 * @requires javelin-behavior
 *           phabricator-prefab
 */

JX.behavior('aphront-basic-tokenizer', function(config) {
  JX.Prefab.buildTokenizer(config).tokenizer.start();
});
