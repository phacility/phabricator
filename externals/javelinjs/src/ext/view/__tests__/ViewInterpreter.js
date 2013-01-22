/**
 * @requires javelin-view
 *           javelin-view-interpreter
 *           javelin-view-html
 *           javelin-util
 */

describe('JX.ViewInterpreter', function() {
  var html = new JX.ViewInterpreter();

  JX.HTMLView.registerToInterpreter(html);

  it('should allow purty syntax to make a view', function() {
    var little_view =
    html.div({},
      html.p({className: 'pretty'},
        html.span({sigil: 'hook'},
          'Check out ',
          html.a({href: 'https://fb.com/', rel: '_blank' }, 'Facebook'))));

    var rendered = JX.ViewRenderer.render(little_view);

    expect(rendered.tagName).toBe('DIV');
    expect(JX.DOM.scry(rendered, 'span', 'hook').length).toBe(1);
  });

  it('should handle no-attr case', function() {
    /* Coffeescript:
     *     div(
     *       span className: 'some-css-class', ref: 'actorTargetLine'
     *       span className: 'message-css', ref: 'message'
     *     )
     *
     * = javascript:
     * div(span({
     *   className: 'some-css-class',
     *   ref: 'actorTargetLine'
     * }), span({
     *  className: 'message-css',
     *  ref: 'message'
     * }));
     */
    var little_view = html.div(html.span({sigil: 'hook'}));
    var rendered = JX.ViewRenderer.render(little_view);
    expect(JX.DOM.scry(rendered, 'span', 'hook').length).toBe(1);
  });
});
