/**
 * @provides javelin-behavior-conpherence-search
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-stratcom
 */

JX.behavior('conpherence-search', function() {

  var shown = true;
  var request = null;

  function _toggleSearch(e) {
    e.kill();
    var node = JX.$('conpherence-main-layout');

    shown = !shown;
    JX.DOM.alterClass(node, 'show-searchbar', !shown);
    if (!shown) {
      JX.$('conpherence-search-input').focus();
    } else {
      var form_root = JX.DOM.find(document, 'div', 'conpherence-form');
      var textarea = JX.DOM.find(form_root, 'textarea');
      textarea.focus();
    }
    JX.Stratcom.invoke('resize');
  }

  function _doSearch(e) {
    e.kill();
    var search_text = JX.$('conpherence-search-input').value;
    var search_uri = JX.$('conpherence-search-form').action;
    var search_node = JX.$('conpherence-search-results');

    if (request || !search_text) {
      return;
    }

    request = new JX.Request(search_uri, function(response) {
      JX.DOM.setContent(search_node, JX.$H(response));
      request = null;
    });
    request.setData({fulltext: search_text});
    request.send();
  }

  function _viewResult(e) {
    e.kill();
    var uri = e.getNode('tag:a');
    _toggleSearch(e);
    JX.$U(uri).go();
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
    'conpherence-search-result-jump',
    _viewResult);

  JX.Stratcom.listen(
    'click',
    'conpherence-search-toggle',
    _toggleSearch);

});
