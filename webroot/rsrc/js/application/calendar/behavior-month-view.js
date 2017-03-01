/**
 * @provides javelin-behavior-calendar-month-view
 */
JX.behavior('calendar-month-view', function() {

  var hover_nodes = [];

  function get_info(e) {
    var week_body = e.getNode('calendar-week-body');
    if (!week_body) {
      week_body = e.getNode('calendar-week-foot').previousSibling;
    }

    var week_foot = week_body.nextSibling;
    var day_id = JX.Stratcom.getData(e.getNode('tag:td')).dayID;

    var day_body;
    var day_foot;
    var body_nodes = JX.DOM.scry(week_body, 'td');
    var foot_nodes = JX.DOM.scry(week_foot, 'td');
    for (var ii = 0; ii < body_nodes.length; ii++) {
      if (JX.Stratcom.getData(body_nodes[ii]).dayID == day_id) {
        day_body = body_nodes[ii];
        day_foot = foot_nodes[ii];
        break;
      }
    }

    return {
      data: JX.Stratcom.getData(week_body),
      dayID: day_id,
      nodes: {
        week: {
          body: week_body,
          foot: week_foot
        },
        day: {
          body: day_body,
          foot: day_foot
        }
      }
    };
  }

  function alter_hover(enable) {
    for (var ii = 0; ii < hover_nodes.length; ii++) {
      JX.DOM.alterClass(hover_nodes[ii], 'calendar-hover', enable);
    }
  }

  JX.enableDispatch(document.body, 'mouseover');
  JX.enableDispatch(document.body, 'mouseout');

  JX.Stratcom.listen('mouseover', ['calendar-week', 'tag:td'], function(e) {
    if (e.getNode('calendar-event-list')) {
      alter_hover(false);
      hover_nodes = [];
      return;
    }

    var info = get_info(e);
    hover_nodes = [
      info.nodes.day.body,
      info.nodes.day.foot
    ];

    alter_hover(true);
  });

  JX.Stratcom.listen('mouseout', ['calendar-week', 'tag:td'], function() {
    alter_hover(false);
  });

  JX.Stratcom.listen('click', ['calendar-week', 'tag:td'], function(e) {
    if (!e.isNormalClick()) {
      return;
    }

    // If this is a click in the event list or on a link, ignore it. This
    // allows users to follow links to events and select text.
    if (e.getNode('calendar-event-list') || e.getNode('tag:a')) {
      return;
    }

    var info = get_info(e);
    JX.$U(info.data.actionMap[info.dayID].dayURI).go();
  });

});
