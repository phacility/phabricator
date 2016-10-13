/**
 * @provides javelin-behavior-conpherence-search
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-stratcom
 */

JX.behavior('conpherence-search', function(config) {

  var shown = true;
  var request = null;

  function _toggleSearch(e) {
    e.kill();
    var node = JX.$('conpherence-main-layout');

    shown = !shown;
    JX.DOM.alterClass(node, 'show-searchbar', !shown);
    JX.Stratcom.invoke('resize');
  }

  function _doSearch(e) {
    e.kill();
    var search_text = JX.$('conpherence-search-input').value;
    var search_node = JX.$('conpherence-search-results');

    if (request || !search_text) {
      return;
    }

    request = new JX.Request(config.searchURI, function(response) {
      JX.DOM.setContent(search_node, JX.$H(response));
      request = null;
    });
    request.setData({fulltext: search_text});
    request.send();

  }

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-search-input',
    _doSearch);

  JX.Stratcom.listen(
    'keydown',
    'conpherence-search-input',
    function(e) {
      if (e.getSpecialKey() != 'return') {
        return;
      }
      e.kill();
      _doSearch(e);
    });

  JX.Stratcom.listen(
    'click',
    'conpherence-search-toggle',
    _toggleSearch);

});
