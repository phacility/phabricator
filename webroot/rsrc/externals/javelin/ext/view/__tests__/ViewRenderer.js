/**
 * @requires javelin-view-renderer
 *           javelin-view
 */

describe('JX.ViewRenderer', function() {
  it('should render children then parent', function() {
    var child_rendered = false;
    var child_rendered_first = false;

    var child = new JX.View({});
    var parent = new JX.View({});
    parent.addChild(child);
    child.render = function() {
      child_rendered = true;
    };

    parent.render = function() {
      child_rendered_first = child_rendered;
    };

    JX.ViewRenderer.render(parent);
    expect(child_rendered_first).toBe(true);
  });
});
