/**
 * @provides show-graph
 */
		
function showGraph(data) {
    var name = [];
    var marks = [];

    for (var i in data) {
        name.push(data[i].dateWhenTrackedFor);
        marks.push(data[i].numMinutes / 60);
    }

    var chartdata = {
        labels: name,
        datasets: [
            {
                label: 'Number of hours',
                backgroundColor: '#00b33c',
                borderColor: '#004d1a',
                hoverBackgroundColor: '#CCCCCC',
                hoverBorderColor: '#666666',
                data: marks
            }
        ]
    };

    var graphTarget = $("#graphCanvas");

    var barGraph = new Chart(graphTarget, {
        type: 'bar',
        data: chartdata
    });    
};