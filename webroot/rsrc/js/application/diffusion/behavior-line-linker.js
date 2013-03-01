/**
 * @provides javelin-behavior-diffusion-line-linker
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-history
 */

JX.behavior('diffusion-line-linker', function() {

  var origin = null;
  var target = null;
  var root = null;

  function getRowNumber(tr) {
    var th = JX.DOM.find(tr, 'th', 'diffusion-line-link');
    return +(th.textContent || th.innerText);
  }

  JX.Stratcom.listen(
    'mousedown',
    'diffusion-line-link',
    function(e) {
      if (e.isRightButton()) {
        return;
      }
      origin = e.getNode('tag:tr');
      target = origin;
      root = e.getNode('diffusion-source');
      e.kill();
    });

  JX.Stratcom.listen(
    'click',
    'diffusion-line-link',
    function(e) {
      e.kill();
    });

  var highlight = function(e) {
    if (!origin || e.getNode('diffusion-source') !== root) {
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
      JX.DOM.alterClass(trs[i], 'highlighted', highlighting);
      if (trs[i] === (source === origin ? target : origin)) {
        highlighting = false;
      }
    }
  };

  JX.Stratcom.listen('mouseover', 'diffusion-source', highlight);

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
      var th = JX.DOM.find(origin, 'th', 'diffusion-line-link');
      var uri = JX.DOM.find(th, 'a').href;
      uri = uri.replace(/(.*\$)\d+/, '$1' + lines);

      origin = null;
      target = null;
      e.kill();
      JX.History.replace(uri);
    });

});
