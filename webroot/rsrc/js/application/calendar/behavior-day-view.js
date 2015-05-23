/**
 * @provides javelin-behavior-day-view
 */


JX.behavior('day-view', function(config) {
  var hours = config.hours;
  var first_event_hour = config.firstEventHour;
  var hourly_events = config.hourlyEvents;
  var today_events = config.todayEvents;
  var today_all_day_events = config.allDayEvents;
  var table_wrapper = JX.$(config.tableID);


  function findTodayClusters() {
    var events = today_events.sort(function(x, y){
      return (x.eventStartEpoch - y.eventStartEpoch);
    });

    var clusters = [];

    for (var i=0; i < events.length; i++) {
      var today_event = events[i];

      var destination_cluster_index = null;
      var event_start = today_event.eventStartEpoch - (30*60);
      var event_end = today_event.eventEndEpoch + (30*60);

      for (var j=0; j < clusters.length; j++) {
        var cluster = clusters[j];

        for(var k=0; k < cluster.length; k++) {
          var clustered_event = cluster[k];
          var compare_event_start = clustered_event.eventStartEpoch;
          var compare_event_end = clustered_event.eventEndEpoch;

          if (event_start < compare_event_end &&
            event_end > compare_event_start) {
            destination_cluster_index = j;
            break;
          }
        }

        if (destination_cluster_index !== null) {
          break;
        }
      }

      if (destination_cluster_index !== null) {
        clusters[destination_cluster_index].push(today_event);
        destination_cluster_index = null;
      } else {
        var next_cluster = [];
        next_cluster.push(today_event);
        clusters.push(next_cluster);
      }
    }

    return clusters;
  }

  function updateEventsFromCluster(cluster, hourly_events) {
    var cluster_size = cluster.length;
    var n = 0;
    for(var i=0; i < cluster.length; i++) {
      var cluster_member = cluster[i];

      var event_id = cluster_member.eventID;
      var offset = ((n / cluster_size) * 100) + '%';
      var width = ((1 / cluster_size) * 100) + '%';

      for (var j=0; j < hourly_events.length; j++) {
        if (hourly_events[j].eventID == event_id) {

          hourly_events[j]['offset'] = offset;
          hourly_events[j]['width'] = width;
        }
      }
      n++;
    }

    return hourly_events;
  }

  function drawEvent(hourly_event) {
    var name = hourly_event['eventName'];
    var viewerIsInvited = hourly_event['viewerIsInvited'];
    var offset = hourly_event['offset'];
    var width = hourly_event['width'];
    var top = hourly_event['top'];
    var height = hourly_event['height'];
    var uri = hourly_events['uri'];

    var link_class = 'phui-calendar-day-event-link';

    if (viewerIsInvited) {
      link_class = link_class + ' viewer-invited-day-event';
    }

    var name_link = JX.$N(
      'a',
      {
        className : link_class,
        href: uri
      },
      name);

    var div = JX.$N(
      'div',
      {
        className: 'phui-calendar-day-event',
        style: {
          left: offset,
          width: width,
          top: top,
          height: height
        }
      },
      name_link);

    return div;
  }

  function drawAllDayEvent(
    viewerIsInvited,
    uri,
    name) {
    var class_name = 'day-view-all-day';
    if (viewerIsInvited) {
      class_name = class_name + ' viewer-invited-day-event';
    }

    name = JX.$N(
      'a',
      {
        className: class_name,
        href: uri
      },
      name);

    var all_day_label = JX.$N(
      'span',
      {className: 'phui-calendar-all-day-label'},
      'All Day');

    var div_all_day = JX.$N(
      'div',
      {className: 'phui-calendar-day-event'},
      [all_day_label, name]);

    return div_all_day;
  }

  function drawRows() {
    var rows = [];
    var early_hours = [8];
    if (first_event_hour) {
      early_hours.push(first_event_hour);
    }
    var min_early_hour = Math.min(early_hours[0], early_hours[1]);


    for(var i=0; i < hours.length; i++) {
      if (hours[i]['hour'] < min_early_hour) {
        continue;
      }
      var drawn_hourly_events = [];
      var cell_time = JX.$N(
        'td',
        {className: 'phui-calendar-day-hour'},
        hours[i]['hour_meridian']);

      for (var j=0; j < hourly_events.length; j++) {
        if (hourly_events[j]['hour'] == hours[i]['hour']) {
          drawn_hourly_events.push(drawEvent(hourly_events[j]));
        }
      }

      var cell_event = JX.$N(
        'td',
        {
          className: 'phui-calendar-day-events'
        },
        drawn_hourly_events);
      var row = JX.$N(
        'tr',
        {},
        [cell_time, cell_event]);
      rows.push(row);
    }
    return rows;
  }

  var today_clusters = findTodayClusters();
  for(var i=0; i < today_clusters.length; i++) {
    hourly_events = updateEventsFromCluster(today_clusters[i], hourly_events);
  }
  var rows = drawRows();

  var all_day_events = [];
  for(i=0; i < today_all_day_events.length; i++) {
    var all_day_event = today_all_day_events[i];
    all_day_events.push(drawAllDayEvent(
      all_day_event['viewerIsInvited'],
      all_day_event['uri'],
      all_day_event['name']));
  }

  var table = JX.$N(
    'table',
    {className: 'phui-calendar-day-view'},
    rows);

  JX.DOM.setContent(table_wrapper, [all_day_events, table]);
});
