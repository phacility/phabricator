/**
 * @provides javelin-behavior-maniphest-batch-selector
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-util
 */

JX.behavior('maniphest-batch-selector', function(config) {

  var selected = {};

  // Test if a task node is selected.

  var get_id = function(task) {
    return JX.Stratcom.getData(task).taskID;
  };

  var is_selected = function(task) {
    return (get_id(task) in selected);
  };

  // Change the selected state of a task.

  var change = function(task, to) {
    if (to === undefined) {
      to = !is_selected(task);
    }

    if (to) {
      selected[get_id(task)] = true;
    } else {
      delete selected[get_id(task)];
    }

    JX.DOM.alterClass(
      task,
      'phui-oi-selected',
      is_selected(task));

    update();
  };

  // Change all tasks to some state (used by "select all" / "clear selection"
  // buttons).

  var changeall = function(to) {
    var inputs = JX.DOM.scry(document.body, 'li', 'maniphest-task');
    for (var ii = 0; ii < inputs.length; ii++) {
      change(inputs[ii], to);
    }
  };

  // Clear any document text selection after toggling a task via shift click,
  // since errant clicks tend to start selecting various ranges otherwise.

  var clear_selection = function() {
    if (window.getSelection) {
      if (window.getSelection().empty) {
        window.getSelection().empty();
      } else if (window.getSelection().removeAllRanges) {
        window.getSelection().removeAllRanges();
      }
    } else if (document.selection) {
      document.selection.empty();
    }
  };

  // Update the status text showing how many tasks are selected, and the button
  // state.

  var update = function() {
    var count = JX.keys(selected).length;
    var status;
    if (count === 0) {
      status = 'Shift-Click to Select Tasks';
    } else if (status == 1) {
      status = '1 Selected Task';
    } else {
      status = count + ' Selected Tasks';
    }
    JX.DOM.setContent(JX.$(config.status), status);

    var submit = JX.$(config.submit);
    var disable = (count === 0);
    submit.disabled = disable;
    JX.DOM.alterClass(submit, 'disabled', disable);
  };

  // When he user shift-clicks the task, update the rest of the application
  // state.

  JX.Stratcom.listen(
    'click',
    'maniphest-task',
    function(e) {
      var raw = e.getRawEvent();
      if (!raw.shiftKey) {
        return;
      }

      if (raw.ctrlKey || raw.altKey || raw.metaKey || e.isRightButton()) {
        return;
      }

      if (JX.Stratcom.pass(e)) {
        return;
      }

      e.kill();
      change(e.getNode('maniphest-task'));

      clear_selection();
    });


  // When the user clicks "Select All", select all tasks.

  JX.DOM.listen(
    JX.$(config.selectNone),
    'click',
    null,
    function(e) {
      changeall(false);
      e.kill();
    });


  // When the user clicks "Clear Selection", clear the selection.

  JX.DOM.listen(
    JX.$(config.selectAll),
    'click',
    null,
    function(e) {
      changeall(true);
      e.kill();
    });

  // When the user submits the form, dump selected state into it.

  JX.DOM.listen(
    JX.$(config.formID),
    'submit',
    null,
    function() {
      var ids = [];
      for (var k in selected) {
        ids.push(k);
      }
      ids = ids.join(',');

      var input = JX.$N('input', {type: 'hidden', name: 'ids', value: ids});

      JX.DOM.setContent(JX.$(config.idContainer), input);
    });

  update();

});
