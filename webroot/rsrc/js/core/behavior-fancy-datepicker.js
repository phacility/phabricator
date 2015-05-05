/**
 * @provides javelin-behavior-fancy-datepicker
 * @requires javelin-behavior
 *           javelin-util
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-vector
 */

JX.behavior('fancy-datepicker', function() {

  var picker;
  var root;

  var value_y;
  var value_m;
  var value_d;

  var onopen = function(e) {
    e.kill();

    // If you click the calendar icon while the date picker is open, close it
    // without writing the change.

    if (picker) {
      if (root == e.getNode('phabricator-date-control')) {
        // If the user clicked the same control, just close it.
        onclose(e);
        return;
      } else {
        // If the user clicked a different control, close the old one but then
        // open the new one.
        onclose(e);
      }
    }


    root = e.getNode('phabricator-date-control');

    picker = JX.$N(
      'div',
      {className: 'fancy-datepicker', sigil: 'phabricator-datepicker'},
      JX.$N('div', {className: 'fancy-datepicker-core'}));
    document.body.appendChild(picker);

    var button = e.getNode('calendar-button');
    var p = JX.$V(button);
    var d = JX.Vector.getDim(picker);

    picker.style.left = (p.x - d.x - 2) + 'px';
    picker.style.top = (p.y) + 'px';

    JX.DOM.alterClass(root, 'picker-open', true);

    read_date();
    render();
  };

  var onclose = function(e) {
    if (!picker) {
      return;
    }

    JX.DOM.remove(picker);
    picker = null;
    JX.DOM.alterClass(root, 'picker-open', false);
    e.kill();

    root = null;
  };

  var ontoggle = function(e) {
    var box = e.getTarget();
    root = e.getNode('phabricator-date-control');
    JX.Stratcom.getData(root).disabled = !box.checked;
    redraw_inputs();
  };

  var get_inputs = function() {
    return {
      y: JX.DOM.find(root, 'select', 'year-input'),
      m: JX.DOM.find(root, 'select', 'month-input'),
      d: JX.DOM.find(root, 'select', 'day-input'),
      t: JX.DOM.find(root, 'input', 'time-input')
    };
  };

  var read_date = function() {
    var i = get_inputs();
    value_y = +i.y.value;
    value_m = +i.m.value;
    value_d = +i.d.value;
  };

  var write_date = function() {
    var i = get_inputs();
    i.y.value = value_y;
    i.m.value = value_m;
    i.d.value = value_d;
  };

  var render = function() {
    JX.DOM.setContent(
      picker.firstChild,
      [
        render_month(),
        render_day()
      ]);
  };

  var redraw_inputs = function() {
    var disabled = JX.Stratcom.getData(root).disabled;
    JX.DOM.alterClass(root, 'datepicker-disabled', disabled);

    var box = JX.DOM.scry(root, 'input', 'calendar-enable');
    if (box.length) {
      box[0].checked = !disabled;
    }
  };

  // Render a cell for the date picker.
  var cell = function(label, value, selected, class_name) {

    class_name = class_name || '';

    if (selected) {
      class_name += ' datepicker-selected';
    }
    if (!value) {
      class_name += ' novalue';
    }

    return JX.$N('td', {meta: {value: value}, className: class_name}, label);
  };


  // Render the top bar which allows you to pick a month and year.
  var render_month = function() {
    var months = [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December'];

    var buttons = [
      cell('\u25C0', 'm:-1', false, 'lrbutton'),
      cell(months[value_m - 1] + ' ' + value_y, null),
      cell('\u25B6', 'm:1', false, 'lrbutton')];

    return JX.$N(
      'table',
      {className: 'month-table'},
      JX.$N('tr', {}, buttons));
  };


  // Render the day-of-week and calendar views.
  var render_day = function() {
    var weeks = [];

    // First, render the weekday names.
    var weekdays = 'SMTWTFS';
    var weekday_names = [];
    var ii;
    for (ii = 0; ii < weekdays.length; ii++) {
      weekday_names.push(cell(weekdays.charAt(ii), null, false, 'day-name'));
    }
    weeks.push(JX.$N('tr', {}, weekday_names));


    // Render the calendar itself. NOTE: Javascript uses 0-based month indexes
    // while we use 1-based month indexes, so we have to adjust for that.
    var days = [];
    var start = new Date(value_y, value_m - 1, 1).getDay();
    while (start--) {
      days.push(cell('', null, false, 'day-placeholder'));
    }

    var today = new Date();

    for (ii = 1; ii <= 31; ii++) {
      var date = new Date(value_y, value_m - 1, ii);
      if (date.getMonth() != (value_m - 1)) {
        // We've spilled over into the next month, so stop rendering.
        break;
      }

      var is_today = (today.getYear() == date.getYear() &&
                      today.getMonth() == date.getMonth() &&
                      today.getDate() == date.getDate());

      var classes = [];
      classes.push('day');
      if (is_today) {
        classes.push('today');
      }
      if (date.getDay() === 0 || date.getDay() == 6) {
        classes.push('weekend');
      }

      days.push(cell(ii, 'd:'+ii, value_d == ii, classes.join(' ')));
    }

    // Slice the days into weeks.
    for (ii = 0; ii < days.length; ii += 7) {
      weeks.push(JX.$N('tr', {}, days.slice(ii, ii + 7)));
    }

    return JX.$N('table', {className: 'day-table'}, weeks);
  };


  JX.Stratcom.listen('click', 'calendar-button', onopen);
  JX.Stratcom.listen('change', 'calendar-enable', ontoggle);

  JX.Stratcom.listen(
    'click',
    ['phabricator-datepicker', 'tag:td'],
    function(e) {
      e.kill();

      var data = e.getNodeData('tag:td');
      if (!data.value) {
        return;
      }

      var p = data.value.split(':');
      switch (p[0]) {
        case 'm':
          // User clicked left or right month selection buttons.
          value_m = value_m + parseInt(p[1], 10);
          if (value_m > 12) {
            value_m -= 12;
            value_y++;
          } else if (value_m <= 0) {
            value_m += 12;
            value_y--;
          }
          break;
        case 'd':
          // User clicked a day.
          value_d = parseInt(p[1], 10);
          write_date();

          // Wait a moment to close the selector so they can see the effect
          // of their action.
          setTimeout(JX.bind(null, onclose, e), 150);
          break;
      }

      // Enable the control.
      JX.Stratcom.getData(root).disabled = false;
      redraw_inputs();

      render();
    });

});
