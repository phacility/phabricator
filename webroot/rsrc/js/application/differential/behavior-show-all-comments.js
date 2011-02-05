/**
 * @provides javelin-behavior-differential-show-all-comments
 * @requires javelin-lib-dev
 */

JX.behavior('differential-show-all-comments', function(config) {

  JX.Stratcom.listen(
    'click',
    'differential-show-all-comments',
    function(e) {
      JX.DOM.setContent(
        e.getNode('differential-all-comments-container'),
        JX.HTML(config.markup));
      e.kill();
    });

});
