/**
 * @provides javelin-behavior-typeahead-search
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 */

JX.behavior('typeahead-search', function(config) {
  var input = JX.$(config.inputID);
  var frame = JX.$(config.frameID);
  var last = input.value;
  var in_flight = {};

  function update() {
    if (input.value == last) {
      // This is some kind of non-input keypress like an arrow key. Don't
      // send a query to the server.
      return;
    }

    // Call load() in a little while. If the user hasn't typed anything else,
    // we'll send a request to get results.
    setTimeout(JX.bind(null, load, input.value), 100);
  }

  function load(value) {
    if (value != input.value) {
      // The user has typed some more text, so don't send a request yet. We
      // want to wait for them to stop typing.
      return;
    }

    if (value in in_flight) {
      // We've already sent a request for this query.
      return;
    }
    in_flight[value] = true;

    JX.DOM.alterClass(frame, 'loading', true);
    new JX.Workflow(config.uri, {q: value, format: 'html'})
      .setHandler(function(r) {
        delete in_flight[value];

        if (value != input.value) {
          // The user typed some more stuff while the request was in flight,
          // so ignore the response.
          return;
        }

        last = input.value;
        JX.DOM.setContent(frame, JX.$H(r.markup));
        JX.DOM.alterClass(frame, 'loading', false);
      })
      .start();
  }

  JX.DOM.listen(input, ['keydown', 'keypress', 'keyup'], null, function() {
    // We need to delay this to actually read the value after the keypress.
    setTimeout(update, 0);
  });

  JX.DOM.focus(input);

});
