/**
 * @provides javelin-behavior-phabricator-line-linker
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-history
 */

JX.behavior('phabricator-line-linker', function() {
  var origin = null;
  var target = null;
  var root = null;

  var editor_link = null;
  try {
    editor_link = JX.$('editor_link');
  } catch (ex) {
    // Ignore.
  }

  function getRowNumber(tr) {
    var th = JX.DOM.find(tr, 'th', 'phabricator-source-line');
    return +(th.textContent || th.innerText);
  }

  JX.Stratcom.listen(
    'mousedown',
    'phabricator-source-line',
    function(e) {
      if (!e.isNormalMouseEvent()) {
        return;
      }
      origin = e.getNode('tag:tr');
      target = origin;
      root = e.getNode('phabricator-source');
      e.kill();
    });

  JX.Stratcom.listen(
    'click',
    'phabricator-source-line',
    function(e) {
      e.kill();
    });

  var highlight = function(e) {
    if (!origin || e.getNode('phabricator-source') !== root) {
      return;
    }
    target = e.getNode('tag:tr');

    var highlighting = false;
    var source = null;
    var trs = JX.DOM.scry(root, 'tr');
    for (var i = 0; i < trs.length; i++) {
      if (!highlighting && (trs[i] === origin || trs[i] === target)) {
        highlighting = true;
        source = trs[i];
      }
      JX.DOM.alterClass(trs[i], 'phabricator-source-highlight', highlighting);
      if (trs[i] === (source === origin ? target : origin)) {
        highlighting = false;
      }
    }
  };

  JX.Stratcom.listen('mouseover', 'phabricator-source', highlight);

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (!origin) {
        return;
      }

      highlight(e);

      var o = getRowNumber(origin);
      var t = getRowNumber(target);
      var lines = (o == t ? o : Math.min(o, t) + '-' + Math.max(o, t));
      var th = JX.DOM.find(origin, 'th', 'phabricator-source-line');
      var uri = JX.DOM.find(th, 'a').href;
      uri = uri.replace(/(.*\$)\d+/, '$1' + lines);
      origin = null;
      target = null;
      e.kill();
      JX.History.replace(uri);
      if (editor_link.href) {
        var editdata = JX.Stratcom.getData(editor_link);
        editor_link.href = editdata.link_template.replace('%25l', o);
      }
    });

});
