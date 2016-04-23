/**
 * @provides javelin-behavior-comment-actions
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phuix-form-control-view
 *           phuix-icon-view
 *           javelin-behavior-phabricator-gesture
 */

JX.behavior('comment-actions', function(config) {
  var action_map = config.actions;

  var action_node = JX.$(config.actionID);
  var form_node = JX.$(config.formID);
  var input_node = JX.$(config.inputID);
  var place_node = JX.$(config.placeID);

  var rows = {};

  JX.DOM.listen(action_node, 'change', null, function() {
    var option = find_option(action_node.value);

    action_node.value = '+';

    if (option) {
      add_row(option);
    }
  });

  function find_option(key) {
    var options = action_node.options;
    var option;

    for (var ii = 0; ii < options.length; ii++) {
      option = options[ii];
      if (option.value == key) {
        return option;
      }
    }

    return null;
  }

  function add_row(option) {
    var action = action_map[option.value];
    if (!action) {
      return;
    }

    option.disabled = true;

    var icon = new JX.PHUIXIconView()
      .setIcon('fa-times-circle');
    var remove = JX.$N('a', {href: '#'}, icon.getNode());

    var control = new JX.PHUIXFormControl()
      .setLabel(action.label)
      .setError(remove)
      .setControl(action.type, action.spec);
    var node = control.getNode();

    JX.Stratcom.addSigil(node, 'touchable');

    var remove_action = function() {
      JX.DOM.remove(node);
      delete rows[action.key];
      option.disabled = false;
    };

    JX.DOM.listen(node, 'gesture.swipe.end', null, function(e) {
      var data = e.getData();

      if (data.direction != 'left') {
        // Didn't swipe left.
        return;
      }

      if (data.length <= (JX.Vector.getDim(node).x / 2)) {
        // Didn't swipe far enough.
        return;
      }

      remove_action();
    });

    rows[action.key] = control;

    JX.DOM.listen(remove, 'click', null, function(e) {
      e.kill();
      remove_action();
    });

    place_node.parentNode.insertBefore(node, place_node);

    return control;
  }

  function serialize_actions() {
    var data = [];

    for (var k in rows) {
      data.push({
        type: k,
        value: rows[k].getValue(),
        initialValue: action_map[k].initialValue || null
      });
    }

    return JX.JSON.stringify(data);
  }

  function get_data() {
    var data = JX.DOM.convertFormToDictionary(form_node);

    data.__preview__ = 1;
    data[input_node.name] = serialize_actions();

    return data;
  }

  function restore_draft_actions(drafts) {
    var draft;
    var option;
    var control;

    for (var ii = 0; ii < drafts.length; ii++) {
      draft = drafts[ii];

      option = find_option(draft);
      if (!option) {
        continue;
      }

      control = add_row(option);
    }
  }

  function onresponse(response) {
    if (JX.Device.getDevice() != 'desktop') {
      return;
    }

    var panel = JX.$(config.panelID);
    if (!response.xactions.length) {
      JX.DOM.hide(panel);
    } else {
      JX.DOM.setContent(
        JX.$(config.timelineID),
        JX.$H(response.xactions.join('')));
      JX.DOM.show(panel);
    }
  }

  JX.DOM.listen(form_node, ['submit', 'didSyntheticSubmit'], null, function() {
    input_node.value = serialize_actions();
  });

  if (config.showPreview) {
    var request = new JX.PhabricatorShapedRequest(
      config.actionURI,
      onresponse,
      get_data);

    var trigger = JX.bind(request, request.trigger);

    JX.DOM.listen(form_node, 'keydown', null, trigger);

    var always_trigger = function() {
      new JX.Request(config.actionURI, onresponse)
        .setData(get_data())
        .send();
    };

    JX.DOM.listen(form_node, 'shouldRefresh', null, always_trigger);
    request.start();

    var ondevicechange = function() {
      var panel = JX.$(config.panelID);
      if (JX.Device.getDevice() == 'desktop') {
        request.setRateLimit(500);
        always_trigger();
      } else {
        // On mobile, don't show live previews and only save drafts every
        // 10 seconds.
        request.setRateLimit(10000);
        JX.DOM.hide(panel);
      }
    };

    ondevicechange();

    JX.Stratcom.listen('phabricator-device-change', null, ondevicechange);
  }

  restore_draft_actions(config.drafts || []);

});
