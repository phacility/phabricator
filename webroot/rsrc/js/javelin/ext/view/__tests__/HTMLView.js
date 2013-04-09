/**
 * @requires javelin-view-html
 *           javelin-view-interpreter
 */


describe('JX.HTMLView', function() {
  var html = new JX.ViewInterpreter();

  JX.HTMLView.registerToInterpreter(html);

  it('should fail validation for a little view', function() {
    var little_view =
    html.div({className: 'pretty'},
      html.p({},
        html.span({sigil: 'hook', invalid: 'foo'},
          'Check out ',
          html.a({href: 'https://fb.com/', target: '_blank' }, 'Facebook'))));


    expect(function() {
      little_view.validate();
    }).toThrow();
  });
});
