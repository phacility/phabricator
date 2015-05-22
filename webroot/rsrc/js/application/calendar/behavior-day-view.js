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

    events.forEach(function(e){
      var destination_cluster_index = null;
      var event_start = e.eventStartEpoch - (30*60);
      var event_end = e.eventEndEpoch + (30*60);

      clusters.forEach(function(cluster, index){
        for(var i=0; i < cluster.length; i++) {
          var clustered_event = cluster[i];
          var compare_event_start = clustered_event.eventStartEpoch;
          var compare_event_end = clustered_event.eventEndEpoch;

          if (event_start < compare_event_end &&
            event_end > compare_event_start) {
            destination_cluster_index = index;
            break;
          }
        }
      });

      if (destination_cluster_index !== null) {
        clusters[destination_cluster_index].push(e);
        destination_cluster_index = null;
      } else {
        var next_cluster = [];
        next_cluster.push(e);
        clusters.push(next_cluster);
      }
    });

    return clusters;
  }

  var today_clusters = findTodayClusters();
});
