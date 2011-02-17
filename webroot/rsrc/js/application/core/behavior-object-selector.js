/**
 * @provides javelin-behavior-phabricator-object-selector
 * @requires javelin-lib-dev
 */

JX.behavior('phabricator-object-selector', function(config) {
  var n = 0;

  var phids = {};
  var handles = {};
  var attach_list = {};

  function onreceive(seq, r) {
    if (seq != n) {
      return;
    }

    var display = [];
    attach_list = {};
    for (var k in r) {
      handles[r[k].phid] = r[k];
      display.push(renderHandle(r[k], true));
    }

    if (!display.length) {
      display = renderNote('No results.');
    }

    JX.DOM.setContent(JX.$(config.results), display);
  }

  function redrawAttached() {
    var display = [];

    for (var k in phids) {
      display.push(renderHandle(handles[k], false));
    }

    if (!display.length) {
      display = renderNote('Nothing attached.');
    }

    JX.DOM.setContent(JX.$(config.current), display);
  }

  function renderHandle(h, attach) {

    var td = JX.$N('td');
    var table = JX.$N(
      'table',
      {className: 'phabricator-object-selector-handle'},
      JX.$N(
        'tbody',
        {},
        [JX.$N('th', {}, h.name), td]));
    var btn = JX.$N(
      'a',
      {className: 'button small grey'},
      attach ? 'Attach' : 'Remove');

    JX.Stratcom.addSigil(btn, 'object-attach-button');
    JX.Stratcom.addData(btn, {handle : h, table : table});
    if (attach) {
      attach_list[h.phid] = btn;
    }

    JX.DOM.setContent(td, btn);

    return table;
  }

  function renderNote(note) {
    return JX.$N('div', {}, note);
  }

  function sendQuery() {
    JX.DOM.setContent(JX.$(config.results), renderNote('Loading...'))
    new JX.Request(config.uri, JX.bind(null, onreceive, ++n))
      .setData({
        filter: JX.$(config.filter).value,
        query: JX.$(config.query).value
      })
      .send();
  }

  JX.DOM.listen(
    JX.$(config.search),
    'click',
    null,
    function(e) {
      e.kill();
      sendQuery();
    });

  JX.DOM.listen(
    JX.$(config.results),
    'click',
    'object-attach-button',
    function(e) {
      e.kill();
      var button = e.getNode('object-attach-button');
      if (button.disabled) {
        return;
      }

      var data = e.getNodeData('object-attach-button');
      phids[data.handle.phid] = true;
      JX.DOM.alterClass(button, 'disabled', true);
      button.disabled = true;

      redrawAttached();
    });

  JX.DOM.listen(
    JX.$(config.current),
    'click',
    'object-attach-button',
    function(e) {
      e.kill();
      var button = e.getNode('object-attach-button');
      if (button.disabled) {
        return;
      }

      var data = e.getNodeData('object-attach-button');
      delete phids[data.handle.phid];

      if (attach_list[data.handle.phid]) {
        JX.DOM.alterClass(attach_list[data.handle.phid], 'disabled', false);
        attach_list[data.handle.phid].disabled = false;
      }

      redrawAttached();
    });

  sendQuery();
  redrawAttached();

});
