/**
 * @provides javelin-behavior-phabricator-object-selector
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-request
 *           javelin-util
 */

JX.behavior('phabricator-object-selector', function(config) {
  var n = 0;

  var phids = {};
  var handles = config.handles;
  for (var k in handles) {
    phids[k] = true;
  }
  var button_list = {};
  var query_timer = null;
  var query_delay = 50;

  var phid_input = JX.DOM.find(
    JX.$(config.form),
    'input',
    'aphront-dialog-application-input');

  var last_value = JX.$(config.query).value;

  function onreceive(seq, r) {
    if (seq != n) {
      return;
    }

    var display = [];
    button_list = {};
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
    phid_input.value = JX.keys(phids).join(';');
  }

  function renderHandle(h, attach) {

    var some_icon = JX.$N(
      'span',
      {className: 'phui-icon-view phui-font-fa ' +
        'fa-external-link phabricator-object-selector-popicon'},
      '');

    var view_object_link = JX.$N(
      'a',
      {href: h.uri, target: '_blank'},
      some_icon);

    var select_object_link = JX.$N(
      'a',
      {href: h.uri, sigil: 'object-attacher'},
      h.name);

    var select_object_button = JX.$N(
      'a',
      {href: '#', sigil: 'object-attacher', className: 'button small grey'},
      attach ? 'Select' : 'Remove');

    var cells = [
      JX.$N('td', {}, view_object_link),
      JX.$N('th', {}, select_object_link),
      JX.$N('td', {}, select_object_button)
    ];

    var table = JX.$N(
      'table',
      {className: 'phabricator-object-selector-handle'});

    table.appendChild(
      JX.$N(
        'tr',
        {sigil: 'object-attach-row',
          className: 'phabricator-object-selector-row',
          meta: {handle: h, table:table}},
        cells));

    if (attach) {
      button_list[h.phid] = select_object_button;
      if (h.phid in phids) {
        JX.DOM.alterClass(select_object_button, 'disabled', true);
        select_object_button.disabled = true;
      }
    }

    return table;
  }

  function renderNote(note) {
    return JX.$N('div', {className : 'object-selector-nothing'}, note);
  }

  function sendQuery() {
    query_timer = null;
    JX.DOM.setContent(JX.$(config.results), renderNote('Loading...'));
    new JX.Request(config.uri, JX.bind(null, onreceive, ++n))
      .setData({
        filter: JX.$(config.filter).value,
        exclude: config.exclude,
        query: JX.$(config.query).value
      })
      .send();
  }

  JX.DOM.listen(
    JX.$(config.results),
    'click',
    'object-attacher',
    function(e) {
      e.kill();

      var data = e.getNodeData('object-attach-row');
      var phid = data.handle.phid;
      if (phids[phid]) {
        return;
      }

      phids[phid] = true;
      JX.DOM.alterClass(button_list[phid], 'disabled', true);
      button_list[phid].disabled = true;

      redrawAttached();
    });

  JX.DOM.listen(
    JX.$(config.current),
    'click',
    'object-attacher',
    function(e) {
      e.kill();

      var data = e.getNodeData('object-attach-row');
      var phid = data.handle.phid;

      delete phids[phid];

      // NOTE: We may not have a button in the button list, if this result is
      // not visible in the current search results.
      if (button_list[phid]) {
        JX.DOM.alterClass(button_list[phid], 'disabled', false);
        button_list[phid].disabled = false;
      }

      redrawAttached();
    });

  JX.DOM.listen(
    JX.$(config.filter),
    'change',
    null,
    function(e) {
      e.kill();
      sendQuery();
    });

  JX.DOM.listen(
    JX.$(config.query),
    ['change', 'keydown', 'keyup', 'keypress'],
    null,
    function() {
      var cur_value = JX.$(config.query).value;
      if (last_value == cur_value) {
        return;
      }
      last_value = cur_value;

      clearTimeout(query_timer);
      query_timer = setTimeout(sendQuery, query_delay);
    });

  sendQuery();
  redrawAttached();

});
