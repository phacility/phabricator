/**
 * @provides javelin-behavior-releeph-request-typeahead
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-typeahead
 *           javelin-typeahead-ondemand-source
 *           javelin-dom
 */

JX.behavior('releeph-request-typeahead', function(config) {
  var root = JX.$(config.id);
  var datasource = new JX.TypeaheadOnDemandSource(config.src);
  var callsign = config.aux.callsign;

  datasource.setAuxiliaryData(config.aux);

  datasource.setTransformer(
    function(object) {
      var full_commit_id = object[0];
      var short_commit_id = object[1];
      var author = object[2];
      var ago = object[3];
      var summary = object[4];

      var callsign_commit_id = 'r' + callsign + short_commit_id;

      var box =
        JX.$N(
          'div',
          {},
          [
            JX.$N(
              'div',
              { className: 'commit-id' },
              callsign_commit_id
            ),
            JX.$N(
              'div',
              { className: 'author-info' },
              ago + ' ago by ' + author
            ),
            JX.$N(
              'div',
              { className: 'summary' },
              summary
            )
          ]
        );

      return {
        name: callsign_commit_id,
        tokenizable: callsign_commit_id + ' '+ short_commit_id + ' ' + summary,
        display: box,
        uri: null,
        id: full_commit_id
      };
    });

  /**
   * The default normalizer removes useful control characters that would help
   * out search. For example, I was just trying to search for a commit with
   * the string "a_file" in the message, which was normalized to "afile".
   */
  datasource.setNormalizer(function(query) {
    return query;
  });

  datasource.setMaximumResultCount(config.aux.limit);

  var typeahead = new JX.Typeahead(root);
  typeahead.setDatasource(datasource);

  var placeholder = config.value || config.placeholder;
  if (placeholder) {
    typeahead.setPlaceholder(placeholder);
  }

  typeahead.start();
});
