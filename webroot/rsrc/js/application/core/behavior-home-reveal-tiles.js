/**
 * @provides javelin-behavior-phabricator-home-reveal-tiles
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 * @javelin
 */

JX.behavior('phabricator-home-reveal-tiles', function(config) {
  JX.Stratcom.listen(
    'click',
    'home-show-applications',
    function(e) {
      e.kill();

      JX.DOM.show(JX.$(config.tilesID));
      JX.DOM.hide(JX.$(config.showID));
    });

  JX.Stratcom.listen(
    'click',
    'home-hide-applications',
    function(e) {
      e.kill();

      JX.DOM.hide(JX.$(config.tilesID));
      JX.DOM.show(JX.$(config.showID));
    });
});
