/**
 * @provides phabricator-uiexample-reactor-input
 * @requires javelin-install
 *           javelin-reactor-dom
 *           javelin-view-html
 *           javelin-view-interpreter
 *           javelin-view-renderer
 */

JX.install('ReactorInputExample', {
  extend: 'View',
  members: {
    render: function() {
      var html = JX.HTMLView.registerToInterpreter(new JX.ViewInterpreter());

      var raw_input = JX.ViewRenderer.render(
        html.input({ value: this.getAttr('init') })
      );
      var input = JX.RDOM.input(raw_input);

      return JX.ViewRenderer.render(
        html.div(
          raw_input,
          html.br(),
          html.span(JX.RDOM.$DT(input)),
          html.br(),
          html.span(JX.RDOM.$DT(input.calm(500)))
        )
      );
    }
  }
});
