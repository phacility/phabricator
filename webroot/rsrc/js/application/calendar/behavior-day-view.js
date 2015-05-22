/**
 * @provides javelin-behavior-day-view
 */


JX.behavior('day-view', function(config) {
  var hourly_events = config.hourlyEvents;
  var today_events = config.todayEvents;


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

  var today_clusters = findTodayClusters();
  for(var i=0; i < today_clusters.length; i++) {
    hourly_events = updateEventsFromCluster(today_clusters[i], hourly_events);
  }
});
