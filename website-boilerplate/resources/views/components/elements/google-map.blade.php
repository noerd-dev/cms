<?php

use Livewire\Component;
use Noerd\Website\Traits\NoerdElement;

new class extends Component {
    use NoerdElement;


    public function getComponentId(): string
    {
        return $this->getId();
    }
}; ?>

<div>
    <div id="map-{{ $this->getComponentId() }}" class="aspect-[1280/600]"></div>

    <script type="text/javascript">
        (function () {
            if (!window.__googleMapsBootstrapped) {
                window.__googleMapsBootstrapped = true;
                ((g) => { var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = window; b = b[c] || (b[c] = {}); var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams, u = () => h || (h = new Promise(async (f, n) => { await (a = m.createElement("script")); e.set("libraries", [...r] + ""); for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]); e.set("callback", c + ".maps." + q); a.src = `https://maps.${c}apis.com/maps/api/js?` + e; d[q] = f; a.onerror = () => h = n(Error(p + " could not load.")); a.nonce = m.querySelector("script[nonce]")?.nonce || ""; m.head.append(a); })); d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n)); })({
                    key: @js(config('website.google_maps_key')),
                    v: 'weekly'
                });
            }

            (async () => {
                const { Map } = await google.maps.importLibrary('maps');

                // Coordinates come from the element's "Coordinates" field ("lat, lng").
                const rawLatLng = @js((string) ($element->latLng ?? ''));
                const parts = rawLatLng.split(',').map((value) => parseFloat(value.trim()));
                const position = parts.length === 2 && parts.every((value) => !isNaN(value))
                    ? { lat: parts[0], lng: parts[1] }
                    : { lat: 52.520008, lng: 13.404954 };

                const warmMapStyles = [
                    { elementType: 'geometry', stylers: [{ color: '#f5efe6' }] },
                    { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
                    { elementType: 'labels.text.fill', stylers: [{ color: '#5e574b' }] },
                    { elementType: 'labels.text.stroke', stylers: [{ color: '#f5efe6' }] },
                    { featureType: 'administrative.land_parcel', stylers: [{ visibility: 'off' }] },
                    { featureType: 'administrative.neighborhood', stylers: [{ visibility: 'off' }] },
                    { featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#ebe3d3' }] },
                    { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#7a6f5c' }] },
                    { featureType: 'poi.business', stylers: [{ visibility: 'off' }] },
                    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#d4c8b0' }] },
                    { featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#7a6f5c' }] },
                    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
                    { featureType: 'road', elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
                    { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
                    { featureType: 'road.arterial', elementType: 'labels.text.fill', stylers: [{ color: '#5e574b' }] },
                    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#dcccbb' }] },
                    { featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#3d362c' }] },
                    { featureType: 'road.local', elementType: 'geometry', stylers: [{ color: '#fdf9f2' }] },
                    { featureType: 'road.local', elementType: 'labels.text.fill', stylers: [{ color: '#7a6f5c' }] },
                    { featureType: 'transit', stylers: [{ visibility: 'off' }] },
                    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c8baa4' }] },
                    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#3d362c' }] }
                ];

                const mapElement = document.getElementById('map-{{ $this->getComponentId() }}');
                const map = new Map(mapElement, {
                    zoom: 12,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                    center: position,
                    styles: warmMapStyles
                });

                const markerIcon = {
                    path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5z',
                    fillColor: '#0c0a09',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                    scale: 1.8,
                    anchor: new google.maps.Point(12, 22)
                };

                new google.maps.Marker({
                    position: position,
                    map: map,
                    icon: markerIcon
                });
            })();
        })();
    </script>
    <style>
        #map-{{ $this->getComponentId() }} {
            width: 100%;
        }
    </style>
</div>
