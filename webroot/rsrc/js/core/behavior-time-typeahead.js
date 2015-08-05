/**
 * @provides javelin-behavior-time-typeahead
 * @requires javelin-behavior
 *           javelin-util
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-typeahead-static-source
 */

JX.behavior('time-typeahead', function(config) {
  var start_date_control = JX.$(config.startTimeID);
  var end_date_control = config.endTimeID ? JX.$(config.endTimeID) : null;
  var format = config.format;

  var end_date_tampered = false;

  var datasource = new JX.TypeaheadStaticSource(config.timeValues);
  datasource.setTransformer(function(v) {
    var attributes = {'className' : 'phui-time-typeahead-value'};
    var display = JX.$N('div', attributes, v[1]);
    var object = {
      'id' : v[0],
      'name' : v[1],
      'display' : display,
      'uri' : null
    };
    return object;
  });
  datasource.setSortHandler(function(value, list) {
    list.sort(function(u,v){
      return (u.id > v.id) ? 1 : -1;
    });
  });
  datasource.setMaximumResultCount(24);
  var typeahead = new JX.Typeahead(
    start_date_control,
    JX.DOM.find(start_date_control, 'input', null));
  typeahead.setDatasource(datasource);

  if (!end_date_control) {
    typeahead.start();
    return;
  }

  var start_time_control = JX.DOM.find(
    start_date_control,
    'input',
    'time-input');
  var end_time_control = JX.DOM.find(
    end_date_control,
    'input',
    'time-input');

  JX.DOM.listen(start_time_control, 'input', null, function() {
    if (end_date_tampered) {
      return;
    }
    var time = start_time_control.value;
    var end_value = getNewValue(time);
    if (end_value) {
      end_time_control.value = end_value;
    }
  });

  typeahead.listen('choose', function(e) {
    if (end_date_tampered) {
      return;
    }
    var time = e.name;
    var end_value = getNewValue(time);
    if (end_value) {
      end_time_control.value = end_value;
    }
  });

  JX.DOM.listen(end_date_control, 'input', null, function() {
    end_date_tampered = true;
  });


  function getNewValue(time) {
    var regex = /^([0-2]?\d)(?::([0-5]\d))?\s*((am|pm))?$/i;

    if (!regex.test(time)) {
      return null;
    }

    var results = regex.exec(time);
    var hours = parseInt(results[1], 10);
    var minutes = parseInt(results[2], 10) ? parseInt(results[2], 10) : 0;

    var real_time = 0;
    var end_value = '';

    var end_hours;
    var end_minutes;

    if (format === 'H:i' && hours < 23) {
      end_hours = hours + 1;

      if (end_hours > 9) {
        end_hours = end_hours.toString();
      } else {
        end_hours = '0' + end_hours.toString();
      }

      if (minutes > 9) {
        end_minutes = minutes.toString();
      } else {
        end_minutes = '0' + minutes.toString();
      }

      end_value = end_hours + ':' + end_minutes;
    } else if (format === 'g:i A') {
      if (/pm/i.test(results[3])) {
        real_time = 12*60;
      } else if (/am/i.test(results[3]) && hours == 12) {
        hours = 0;
      }

      real_time = real_time + (hours * 60) + minutes;

      var end_time = real_time + 60;

      var end_meridian = 'AM';
      end_hours = Math.floor(end_time / 60);

      if (end_hours == 12) {
        end_meridian = 'PM';
      } else if (end_hours > 12 && end_hours < 24) {
        end_hours = end_hours - 12;
        end_meridian = 'PM';
      } else if (end_hours == 24) {
        end_hours = end_hours - 12;
      }

      end_minutes = end_time%60;
      end_minutes = (end_minutes < 9) ? end_minutes : ('0' + end_minutes);
      end_value = end_hours + ':' + end_minutes + ' ' + end_meridian;
    }


    return end_value;
  }

  typeahead.start();
});
