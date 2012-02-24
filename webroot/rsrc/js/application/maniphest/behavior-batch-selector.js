/**
 * @provides javelin-behavior-maniphest-batch-selector
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('maniphest-batch-selector', function(config) {

  // When a task row's selection state is changed, this issues updates to other
  // parts of the application.

  var onchange = function(task) {
    var input = JX.DOM.find(task, 'input', 'maniphest-batch');
    var state = input.checked;

    JX.DOM.alterClass(task, 'maniphest-batch-selected', state);

    JX.Stratcom.invoke(
      (state ? 'maniphest-batch-task-add' : 'maniphest-batch-task-rem'),
      null,
      {id: input.value})
  };


  // Change the selected state of a task.
  // If 'to' is undefined, toggle. Otherwise, set to true or false.

  var change = function(task, to) {

    var input = JX.DOM.find(task, 'input', 'maniphest-batch');
    var state = input.checked;
    if (to === undefined) {
      input.checked = !input.checked;
    } else {
      input.checked = to;
    }
    onchange(task);
  };


  // Change all tasks to some state (used by "select all" / "clear selection"
  // buttons).

  var changeall = function(to) {
    var inputs = JX.DOM.scry(document.body, 'table', 'maniphest-task');
    for (var ii = 0; ii < inputs.length; ii++) {
      change(inputs[ii], to);
    }
  }


  // Update the status text showing how many tasks are selected, and the button
  // state.

  var selected = {};
  var selected_count = 0;

  var update = function() {
    var status = (selected_count == 1)
      ? '1 Selected Task'
      : selected_count + ' Selected Tasks';
    JX.DOM.setContent(JX.$(config.status), status);

    var submit = JX.$(config.submit);
    var disable = (selected_count == 0);
    submit.disabled = disable;
    JX.DOM.alterClass(submit, 'disabled', disable);
  };


  // When the user clicks the entire <td /> surrounding the checkbox, count it
  // as a checkbox click.

  JX.Stratcom.listen(
    'click',
    'maniphest-task',
    function(e) {
      if (!JX.DOM.isNode(e.getTarget(), 'td')) {
        // Only count clicks in the <td />, not (e.g.) the table border.
        return;
      }

      // Check if the clicked <td /> contains a checkbox.
      var inputs = JX.DOM.scry(e.getTarget(), 'input', 'maniphest-batch');
      if (!inputs.length) {
        return;
      }

      change(e.getNode('maniphest-task'));
  });


  // When he user clicks the <input />, update the rest of the application
  // state.

  JX.Stratcom.listen(
    ['click', 'onchange'],
    'maniphest-batch',
    function(e) {
      onchange(e.getNode('maniphest-task'));
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


  JX.Stratcom.listen(
    'maniphest-batch-task-add',
    null,
    function(e) {
      var id = e.getData().id;
      if (!(id in selected)) {
        selected[id] = true;
        selected_count++;
        update();
      }
    });


  JX.Stratcom.listen(
    'maniphest-batch-task-rem',
    null,
    function(e) {
      var id = e.getData().id;
      if (id in selected) {
        delete selected[id];
        selected_count--;
        update();
      }
    });

});
