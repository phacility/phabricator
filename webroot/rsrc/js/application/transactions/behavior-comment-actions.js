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

  function remove_action(key) {
    var row = rows[key];
    if (row) {
      JX.DOM.remove(row.node);
      row.option.disabled = false;
      delete rows[key];
    }
  }

  function serialize_actions() {
    var data = [];

    for (var k in rows) {
      data.push({
        type: k,
        value: rows[k].control.getValue(),
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
      var preview_root = JX.$(config.timelineID);
      JX.DOM.setContent(
        preview_root,
        [
          JX.$H(response.xactions.join('')),
          JX.$H(response.previewContent)
        ]);
      JX.DOM.show(panel);

      // NOTE: Resonses are currently processed before associated behaviors are
      // registered. We need to defer invoking this event so that any behaviors
      // accompanying the response are registered.
      var invoke_preview = function() {
        JX.Stratcom.invoke(
          'EditEngine.didCommentPreview',
          null,
          {
            rootNode: preview_root
          });
      };
      setTimeout(invoke_preview, 0);
    }
  }

  function force_preview() {
    if (!config.shouldPreview) {
      return;
    }

    new JX.Request(config.actionURI, onresponse)
      .setData(get_data())
      .send();
  }

  function add_row(option) {
    var action = action_map[option.value];
    if (!action) {
      return;
    }

    // Remove any conflicting actions. For example, "Accept Revision" conflicts
    // with "Reject Revision".
    var conflict_key = action.conflictKey || null;
    if (conflict_key !== null) {
      for (var k in action_map) {
        if (k === action.key) {
          continue;
        }
        if (action_map[k].conflictKey !== conflict_key) {
          continue;
        }

        if (!(k in rows)) {
          continue;
        }

        remove_action(k);
      }
    }

    option.disabled = true;

    var icon = new JX.PHUIXIconView()
      .setIcon('fa-times-circle');
    var remove = JX.$N('a', {href: '#'}, icon.getNode());

    var control = new JX.PHUIXFormControl()
      .setLabel(action.label)
      .setError(remove)
      .setControl(action.type, action.spec)
      .setClass('phui-comment-action');
    var node = control.getNode();

    JX.Stratcom.addSigil(node, 'touchable');

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

      remove_action(action.key);
    });

    rows[action.key] = {
      control: control,
      node: node,
      option: option
    };

    JX.DOM.listen(remove, 'click', null, function(e) {
      e.kill();
      remove_action(action.key);
    });

    place_node.parentNode.insertBefore(node, place_node);

    force_preview();

    return control;
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

    JX.DOM.listen(form_node, 'shouldRefresh', null, force_preview);
    request.start();

    var old_device = JX.Device.getDevice();

    var ondevicechange = function() {
      var new_device = JX.Device.getDevice();

      var panel = JX.$(config.panelID);
      if (new_device == 'desktop') {
        request.setRateLimit(500);

        // Force an immediate refresh if we switched from another device type
        // to desktop.
        if (old_device != new_device) {
          force_preview();
        }
      } else {
        // On mobile, don't show live previews and only save drafts every
        // 10 seconds.
        request.setRateLimit(10000);
        JX.DOM.hide(panel);
      }

      old_device = new_device;
    };

    ondevicechange();

    JX.Stratcom.listen('phabricator-device-change', null, ondevicechange);
  }

  restore_draft_actions(config.drafts || []);

});
