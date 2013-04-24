/**
 * @provides javelin-behavior-aphront-basic-tokenizer
 * @requires javelin-behavior
 *           phabricator-prefab
 */

JX.behavior('aphront-basic-tokenizer', function(config) {
  var build = JX.Prefab.buildTokenizer(config);
  if (build) {
    build.tokenizer.start();
  }
});
