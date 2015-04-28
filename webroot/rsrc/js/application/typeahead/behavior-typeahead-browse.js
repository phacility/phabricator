/**
 * @provides javelin-behavior-typeahead-browse
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 */

JX.behavior('typeahead-browse', function() {
  var loading = false;

  JX.Stratcom.listen('click', 'typeahead-browse-more', function(e) {
    e.kill();

    if (loading) {
      return;
    }
    var link = e.getTarget();

    loading = true;
    JX.DOM.alterClass(link, 'loading', true);

    JX.Workflow.newFromLink(link)
      .setHandler(function(r) {
        loading = false;
        JX.DOM.replace(link, JX.$H(r.markup));
      })
      .start();
  });

});
