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
  var display = [];

  var handles = config.handles;
  for (var k in handles) {
    phids[k] = true;
  }

  var query_timer = null;
  var query_delay = 50;

  // TODO: This is fairly grotesque, but the dialog has two different forms
  // inside it and there's no way to sigil the inputs in the "real" form right
  // now. Clean this up when the dialog as a whole gets cleaned up.

  var inputs = JX.DOM.scry(
    JX.$(config.form),
    'input',
    'aphront-dialog-application-input');
  var phid_input;
  for (var ii = 0; ii < inputs.length; ii++) {
    if (inputs[ii].name == 'phids') {
      phid_input = inputs[ii];
      break;
    }
  }

  var last_value = JX.$(config.query).value;

  function onreceive(seq, r) {
    if (seq != n) {
      return;
    }

    display = [];
    for (var k in r) {
      handles[r[k].phid] = r[k];
      display.push({phid: r[k].phid});
    }

    redrawList(true);
  }

  function redrawAttached() {
    var attached = [];

    for (var k in phids) {
      attached.push(renderHandle(handles[k], false).item);
    }

    if (!attached.length) {
      attached = renderNote('Nothing attached.');
    }

    JX.DOM.setContent(JX.$(config.current), attached);
    phid_input.value = JX.keys(phids).join(';');
  }

  function redrawList(rebuild) {
    var ii;
    var content;

    if (rebuild) {
      if (display.length) {
        var handle;

        content = [];
        for (ii = 0; ii < display.length; ii++) {
          handle = handles[display[ii].phid];

          display[ii].node = renderHandle(handle, true);
          content.push(display[ii].node.item);
        }
      } else {
        content = renderNote('No results.');
      }

      JX.DOM.setContent(JX.$(config.results), content);
    }

    var phid;
    var is_disabled;
    var button;

    var at_maximum = !canSelectMore();

    for (ii = 0; ii < display.length; ii++) {
      phid = display[ii].phid;

      is_disabled = false;

      // If this object is already selected, you can not select it again.
      if (phids.hasOwnProperty(phid)) {
        is_disabled = true;
      }

      // If the maximum number of objects are already selected, you can
      // not select more.
      if (at_maximum) {
        is_disabled = true;
      }

      button = display[ii].node.button;
      JX.DOM.alterClass(button, 'disabled', is_disabled);
      button.disabled = is_disabled;
    }

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
      {href: '#',
        sigil: 'object-attacher',
        className: 'button small button-grey'},
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

    return {
      item: table,
      button: select_object_button
    };
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

  function canSelectMore() {
    if (!config.maximum) {
      return true;
    }

    if (JX.keys(phids).length < config.maximum) {
      return true;
    }

    return false;
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

      if (!canSelectMore()) {
        return;
      }

      phids[phid] = true;

      redrawList(false);
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

      redrawList(false);
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

  redrawList(true);
  redrawAttached();
});
