<?php

use Livewire\Volt\Component;
use Noerd\Website\Traits\NoerdElement;
use function Livewire\Volt\{state};

new class extends Component {
    use NoerdElement;


    public function getComponentId(): string
    {
        return $this->getId();
    }
}; ?>

<div>
    <div id="map-{{ $this->getComponentId() }}" class="aspect-[1280/600]"></div>

    <script type="text/javascript"
            src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAI68-3ss71KRMJDLvGDtIGAW97i5RuE7E"></script>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            // When the window has finished loading create our google map below
            google.maps.event.addDomListener(window, 'load', init{{ $this->getComponentId() }})

            function init{{ $this->getComponentId() }}() {
                // Basic options for a simple Google Map
                var mapOptions = {
                    // How zoomed in you want the map to start at (always required)
                    zoom: 12,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                    // The latitude and longitude to center the map (always required)
                    center: new google.maps.LatLng(47.7579609, 11.7397999, 1275),

                    // How you would like to style the map.
                    styles: [{
                        'elementType': 'geometry',
                        'stylers': [{
                            'color': '#f3f4f6'
                        }]
                    },
                        {
                            'elementType': 'labels.icon',
                            'stylers': [{
                                'visibility': 'off'
                            }]
                        },
                        {
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#616161'
                            }]
                        },
                        {
                            'elementType': 'labels.text.stroke',
                            'stylers': [{
                                'color': '#f3f4f6'
                            }]
                        },
                        {
                            'featureType': 'administrative.land_parcel',
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#bdbdbd'
                            }]
                        },
                        {
                            'featureType': 'poi',
                            'elementType': 'geometry',
                            'stylers': [{
                                'color': '#eeeeee'
                            }]
                        },
                        {
                            'featureType': 'poi',
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#757575'
                            }]
                        },
                        {
                            'featureType': 'poi.park',
                            'elementType': 'geometry',
                            'stylers': [{
                                'color': '#e5e5e5'
                            }]
                        },
                        {
                            'featureType': 'poi.park',
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#9e9e9e'
                            }]
                        },
                        {
                            'featureType': 'road',
                            'elementType': 'geometry',
                            'stylers': [{
                                'color': '#ffffff'
                            }]
                        },
                        {
                            'featureType': 'road.arterial',
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#757575'
                            }]
                        },
                        {
                            'featureType': 'road.highway',
                            'elementType': 'geometry',
                            'stylers': [{
                                'color': '#dadada'
                            }]
                        },
                        {
                            'featureType': 'road.highway',
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#616161'
                            }]
                        },
                        {
                            'featureType': 'road.local',
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#9e9e9e'
                            }]
                        },
                        {
                            'featureType': 'transit.line',
                            'elementType': 'geometry',
                            'stylers': [{
                                'color': '#e5e5e5'
                            }]
                        },
                        {
                            'featureType': 'transit.station',
                            'elementType': 'geometry',
                            'stylers': [{
                                'color': '#eeeeee'
                            }]
                        },
                        {
                            'featureType': 'water',
                            'elementType': 'geometry',
                            'stylers': [{
                                'color': '#c9c9c9'
                            }]
                        },
                        {
                            'featureType': 'water',
                            'elementType': 'labels.text.fill',
                            'stylers': [{
                                'color': '#9e9e9e'
                            }]
                        }
                    ]
                }

                // Get the HTML DOM element that will contain your map
                var mapElement = document.getElementById('map-{{ $this->getComponentId() }}')

                // Create the Google Map using our element and options defined above
                var map = new google.maps.Map(mapElement, mapOptions)

                // Let's also add a marker while we're at it
                var marker = new google.maps.Marker({
                    position: new google.maps.LatLng(47.7579609, 11.7397999, 1275),
                    map: map,
                    title: 'Auswall'
                })
            }
        });
    </script>
    <style>
        #map-{{ $this->getComponentId() }} {
            width: 100%;
        }
    </style>
</div>