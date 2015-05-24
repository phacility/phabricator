/**
 * @provides javelin-behavior-day-view
 */


JX.behavior('day-view', function(config) {

  function findTodayClusters() {
    var events = today_events.sort(function(x, y){
      return (x.eventStartEpoch - y.eventStartEpoch);
    });

    var clusters = [];

    for (var i=0; i < events.length; i++) {
      var today_event = events[i];

      var destination_cluster_index = null;
      var event_start = today_event.eventStartEpoch - (60);
      var event_end = today_event.eventEndEpoch + (60);

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

  function updateEventsFromCluster(cluster) {
    var cluster_size = cluster.length;
    var n = 0;
    for(var i=0; i < cluster.length; i++) {
      var cluster_member = cluster[i];

      var event_id = cluster_member.eventID;
      var offset = ((n / cluster_size) * 100) + '%';
      var width = ((1 / cluster_size) * 100) + '%';

      for (var j=0; j < today_events.length; j++) {
        if (today_events[j].eventID == event_id) {

          today_events[j]['offset'] = offset;
          today_events[j]['width'] = width;
        }
      }
      n++;
    }

    return today_events;
  }

  function drawEvent(e) {
    var name = e['eventName'];
    var eventID = e['eventID'];
    var viewerIsInvited = e['viewerIsInvited'];
    var offset = e['offset'];
    var width = e['width'];
    var top = e['top'];
    var height = e['height'];
    var uri = e['uri'];

    var sigil = 'phui-calendar-day-event';
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

    var class_name = 'phui-calendar-day-event';
    if (e.canEdit) {
      class_name = class_name + ' can-drag';
    }

    var div = JX.$N(
      'div',
      {
        className: class_name,
        sigil: sigil,
        meta: {eventID: eventID, record: e, uri: uri},
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
      {className: 'phui-calendar-day-event all-day'},
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
      var cell_time = JX.$N(
        'td',
        {className: 'phui-calendar-day-hour'},
        hours[i]['hour_meridian']);

      var cell_event = JX.$N(
        'td',
        {
          meta: {
            time: hours[i]['hour_meridian']
          },
          className: 'phui-calendar-day-events',
          sigil: 'phui-calendar-day-event-cell'
        });

      var row = JX.$N(
        'tr',
        {},
        [cell_time, cell_event]);
      rows.push(row);
    }
    return rows;
  }

  function clusterAndDrawEvents() {
    var today_clusters = findTodayClusters();
    for(var i=0; i < today_clusters.length; i++) {
      today_events = updateEventsFromCluster(today_clusters[i]);
    }
    var drawn_hourly_events = [];
    for (i=0; i < today_events.length; i++) {
      drawn_hourly_events.push(drawEvent(today_events[i]));
    }

    JX.DOM.setContent(hourly_events_wrapper, drawn_hourly_events);

  }

  var year = config.year;
  var month = config.month;
  var day = config.day;
  var query = config.query;

  var hours = config.hours;
  var first_event_hour = config.firstEventHour;
  var first_event_hour_epoch = parseInt(config.firstEventHourEpoch, 10);
  var today_events = config.todayEvents;
  var today_all_day_events = config.allDayEvents;
  var table_wrapper = JX.$(config.tableID);
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

  var dragging = false;
  var origin = null;

  var offset_top = null;
  var new_top = null;

  var click_time = null;

  JX.DOM.listen(
    table_wrapper,
    'mousedown',
    'phui-calendar-day-event',
    function(e){

    if (!e.isNormalMouseEvent()) {
      return;
    }
    var data = e.getNodeData('phui-calendar-day-event');
    if (!data.record.canEdit) {
      return;
    }
    e.kill();
    dragging = e.getNode('phui-calendar-day-event');
    JX.DOM.alterClass(dragging, 'phui-drag', true);

    click_time = new Date();

    origin = JX.$V(e);

    var outer = JX.Vector.getPos(table);
    var inner = JX.Vector.getPos(dragging);

    offset_top = inner.y - outer.y;
    new_top = offset_top;

    dragging.style.top = offset_top + 'px';
  });
  JX.Stratcom.listen('mousemove', null, function(e){
    if (!dragging) {
      return;
    }
    var cursor = JX.$V(e);

    new_top = cursor.y - origin.y + offset_top;
    new_top = Math.min(new_top, 1320);
    new_top = Math.max(new_top, 0);
    new_top = Math.floor(new_top/15) * 15;

    dragging.style.top = new_top + 'px';
  });
  JX.Stratcom.listen('mouseup', null, function(){
    if (!dragging) {
      return;
    }

    var data = JX.Stratcom.getData(dragging);
    var record = data.record;

    if (new_top == offset_top) {
      var now = new Date();
      if (now.getTime() - click_time.getTime() < 250) {
        JX.$U(record.uri).go();
      }

      JX.DOM.alterClass(dragging, 'phui-drag', false);
      dragging = false;
      return;
    }
    var new_time = first_event_hour_epoch + (new_top * 60);
    var id = data.eventID;
    var duration = record.eventEndEpoch - record.eventStartEpoch;
    record.eventStartEpoch = new_time;
    record.eventEndEpoch = new_time + duration;
    record.top = new_top + 'px';

    new JX.Workflow(
      '/calendar/event/drag/' + id + '/',
      {start: new_time})
      .start();

    JX.DOM.alterClass(dragging, 'phui-drag', false);
    dragging = false;

    clusterAndDrawEvents();
  });

  JX.DOM.listen(table_wrapper, 'click', 'phui-calendar-day-event', function(e){
    if (e.isNormalClick()) {
      e.kill();
    }
  });

  JX.DOM.listen(table, 'click', 'phui-calendar-day-event-cell', function(e){
    if (!e.isNormalClick()) {
      return;
    }
    var data = e.getNodeData('phui-calendar-day-event-cell');
    var time = data.time;
    new JX.Workflow(
      '/calendar/event/create/',
      {
        year: year,
        month: month,
        day: day,
        time: time,
        next: 'day',
        query: query
      })
      .start();
  });

  var hourly_events_wrapper = JX.$N(
    'div',
    {style: {
      position: 'absolute',
      left: '69px',
      right: 0
    }});

  clusterAndDrawEvents();

  var daily_wrapper = JX.$N(
    'div',
    {style: {position: 'relative'}},
    [hourly_events_wrapper, table]);

  JX.DOM.setContent(table_wrapper, [all_day_events, daily_wrapper]);

});
