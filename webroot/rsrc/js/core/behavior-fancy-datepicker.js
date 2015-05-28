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
    if (e) {
      e.kill();
    }

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
      d: JX.DOM.find(root, 'input', 'date-input'),
      t: JX.DOM.find(root, 'input', 'time-input')
    };
  };

  var read_date = function() {
    var i = get_inputs();
    var date = i.d.value;
    var parts = date.split('/');
    value_y = +parts[2];
    value_m = +parts[0];
    value_d = +parts[1];
  };

  var write_date = function() {
    var i = get_inputs();
    i.d.value = value_m + '/' + value_d + '/' + value_y;
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
    var valid_date = getValidDate();
    var month = valid_date.getMonth();
    var year = valid_date.getYear() + 1900;

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
      cell(months[month] + ' ' + year, null),
      cell('\u25B6', 'm:1', false, 'lrbutton')];

    return JX.$N(
      'table',
      {className: 'month-table'},
      JX.$N('tr', {}, buttons));
  };

  function getValidDate() {
    var written_date = new Date(value_y, value_m-1, value_d);
    if (isNaN(written_date.getTime())) {
      return new Date();
    } else {
      //year 01 should be 2001, not 1901
      if (written_date.getYear() < 70) {
        value_y += 2000;
        written_date = new Date(value_y, value_m-1, value_d);
      }
      return written_date;
    }
  }


  // Render the day-of-week and calendar views.
  var render_day = function() {
    var today = new Date();
    var valid_date = getValidDate();

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
    var start = new Date(
      valid_date.getYear() + 1900,
      valid_date.getMonth(),
      1).getDay();

    while (start--) {
      days.push(cell('', null, false, 'day-placeholder'));
    }

    for (ii = 1; ii <= 31; ii++) {
      var date = new Date(
        valid_date.getYear() + 1900,
        valid_date.getMonth(),
        ii);
      if (date.getMonth() != (valid_date.getMonth())) {
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

      days.push(cell(
        ii,
        'd:'+ii,
        valid_date.getDate() == ii,
        classes.join(' ')));
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

      var valid_date = getValidDate();
      value_y = valid_date.getYear() + 1900;
      value_m = valid_date.getMonth() + 1;
      value_d = valid_date.getDate();

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

  JX.Stratcom.listen('click', null, function(e){
    if (e.getNode('phabricator-datepicker')) {
      return;
    }
    onclose();
  });

});
