/**
 * @provides javelin-behavior-phame-post-blogs
 * @requires javelin-behavior
 *           javelin-dom
 */

JX.behavior('phame-post-blogs', function(config) {

  var visibility_select = JX.$(config.visibility.select_id);
  var blogs_widget      = JX.$(config.blogs.checkbox_id);

  var visibilityCallback = function(e) {
    if (visibility_select.value == config.visibility.published) {
      JX.DOM.show(blogs_widget);
    } else {
      JX.DOM.hide(blogs_widget);
    }
    e.kill();
  }

  JX.DOM.listen(visibility_select, 'change', null, visibilityCallback);

});
