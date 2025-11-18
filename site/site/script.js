$(document).ready(function() {
    // Define the map
    var map = new google.maps.Map(document.getElementById('map'), {
        zoom: 10,
        center: {lat: 37.7749, lng: -122.4194}
    });

    // Fetch data from REST API using AJAX
    $.ajax({
        url: 'https://gas-station-maps-production.up.railway.app',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            // Loop through the data and add markers to the map
            $.each(data, function(index, gasStation) {
                var marker = new google.maps.Marker({
                    position: {lat: parseFloat(gasStation.gasStationLat), lng: parseFloat(gasStation.gasStationLong)},
                    map: map
                });
                // Add a listener to the marker to show info window on click
                var contentString = '<div id="content">'+
                  '<div id="siteNotice">'+
                  '</div>'+
                  '<h1 id="firstHeading" class="firstHeading">' + gasStation.gasStationNormalName + '</h1>'+
                  '<div id="bodyContent">'+
                  '<p>' + gasStation.gasStationAddress + '</p>'+
                  '</div>'+
                  '</div>';
                var infowindow = new google.maps.InfoWindow({
                    content: contentString
                });
                marker.addListener('click', function() {
                    infowindow.open(map, marker);
                });
            });
        },
        error: function() {
            alert('Error fetching data from server.');
        }
    });
});