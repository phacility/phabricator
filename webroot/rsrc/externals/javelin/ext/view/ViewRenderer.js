/**
 * @provides javelin-view-renderer
 * @requires javelin-install
 *           javelin-util
 */

JX.install('ViewRenderer', {
  members: {
    visit: function(view, children) {
      return view.render(children);
    }
  },
  statics: {
    render: function(view) {
      var renderer = new JX.ViewRenderer();
      return view.accept(JX.bind(renderer, renderer.visit));
    }
  }
});
