/**
 * @provides phabricator-uiexample-javelin-view
 * @requires javelin-install
 *           javelin-dom
 *           javelin-view
 */

JX.install('JavelinViewExample', {
  extend: 'View',
  members: {
    render: function(rendered_children) {
      return JX.$N(
        'div',
        { className: 'client-view' },
        rendered_children
      );
    }
  }
});
