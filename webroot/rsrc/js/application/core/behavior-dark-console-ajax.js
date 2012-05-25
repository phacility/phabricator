/**
 * @provides javelin-behavior-dark-console-ajax
 * @requires javelin-behavior
 *           javelin-dom
 */

JX.behavior('dark-console-ajax', function(config) {
  var requestLog    = JX.DOM.find(document.body,
                                  'table',
                                  'dark-console-request-log');
  var requestTable  = JX.DOM.find(requestLog,   'table');
  var requestRows   = JX.DOM.scry(requestTable, 'tr');
  var requestNumber = requestRows.length - 1; // header don't count
  var requestURI    = config.uri;
  var console       = JX.$H(config.console);
  var newRowType    = 'ajax';

  var newRowNumber = JX.$N(
    'a',
    {
      'sigil' : 'request-log-number',
      'meta'  : { 'console' : console }
    },
    requestNumber
  );
  var newRowURI = JX.$N(
    'a',
    {
      'sigil' : 'request-log-uri',
      'meta'  : { 'console' : console }
    },
    requestURI
  );

  var newRow = JX.$N(
      'tr',
      {
        'className' : requestNumber % 2 ? 'alt' : ''
      },
      [
        JX.$N('td', {}, newRowNumber),
        JX.$N('td', {}, newRowType),
        JX.$N('td', {}, newRowURI)
      ]
  );

  JX.DOM.appendContent(requestTable, newRow);

});
