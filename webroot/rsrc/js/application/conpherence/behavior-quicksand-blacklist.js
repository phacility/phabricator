/**
 * @provides javelin-behavior-quicksand-blacklist
 * @requires javelin-behavior
 *           javelin-quicksand
 */

JX.behavior('quicksand-blacklist', function(config) {
  JX.Quicksand.setURIPatternBlacklist(config.patterns);
});
