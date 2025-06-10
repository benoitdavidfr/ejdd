<?php
/** Produit des carte Leaflet */
?>
<!DOCTYPE HTML>
<html><head>
  <title>carte</title>
  <meta charset="UTF-8">
  <!-- meta nécessaire pour le mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <!-- styles nécessaires pour le mobile -->
  <link rel='stylesheet' href='leaflet/llmap.css'>
  <!-- styles et src de Leaflet -->
  <link rel="stylesheet" href='leaflet/leaflet.css'/>
  <script src='leaflet/leaflet.js'></script>
  <!-- chgt du curseur -->
  <style>
  .leaflet-grab {
     cursor: auto;
  }
  .leaflet-dragging .leaflet-grab{
     cursor: move;
  }
  </style> 
  <!-- Include the edgebuffer plugin -->
  <script src="leaflet/leaflet.edgebuffer.js"></script>
  <!-- Include the Control.Coordinates plugin -->
  <link rel='stylesheet' href='leaflet/Control.Coordinates.css'>
  <script src='leaflet/Control.Coordinates.js'></script>
  <!-- Include the uGeoJSON plugin -->
  <script src="leaflet/leaflet.uGeoJSON.js"></script>
  <!-- plug-in d'appel des GeoJSON en AJAX -->
  <script src='leaflet/leaflet-ajax.js'></script>
</head>
<body>
  <div id="map" style="height: 100%; width: 100%"></div>
  <script>
    var gjsurl = 'http://localhost/dataset/geojson.php/';

// affiche les caractéristiques de chaque feature
var onEachFeature = function (feature, layer) {
  layer.bindPopup(
    '<b>Feature</b><br>'
    +'<pre>'+JSON.stringify(feature.properties,null,' ')+'</pre>'
  );
  layer.bindTooltip(feature.properties.nom);
}

var map = L.map('map').setView([46.5,3],6);  // view pour la zone
L.control.scale({position:'bottomleft', metric:true, imperial:false}).addTo(map);

// activation du plug-in Control.Coordinates
var c = new L.Control.Coordinates();
c.addTo(map);
map.on('click', function(e) { c.setCoordinates(e); });

var baseLayers = {
  // OSM
  "OSM" : new L.TileLayer(
    'https://{s}.tile.osm.org/{z}/{x}/{y}.png',
    {"attribution":"&copy; <a href='https://www.openstreetmap.org/copyright' target='_blank'>les contributeurs d’OpenStreetMap</a>"}
  ),
  // Fond blanc
  "Fond blanc" : new L.TileLayer(
    'https://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.jpg',
    { format: 'image/jpeg', minZoom: 0, maxZoom: 21, detectRetina: false}
  )
};
map.addLayer(baseLayers["OSM"]);

var overlays = {
// Région
  "Région" : new L.UGeoJSONLayer({
    endpoint: gjsurl+'ae2025/collections/region/items',
    minZoom: 0, maxZoom: 18, usebbox: true, onEachFeature: onEachFeature
  }),
// Département
  "Département" : new L.UGeoJSONLayer({
    endpoint: gjsurl+'ae2025/collections/departement/items',
    minZoom: 0, maxZoom: 18, usebbox: true, onEachFeature: onEachFeature
  }),
// EPCI
  "EPCI" : new L.UGeoJSONLayer({
    endpoint: gjsurl+'ae2025/collections/epci/items',
    minZoom: 0, maxZoom: 18, usebbox: true, onEachFeature: onEachFeature
  }),
// Commune
  "Commune" : new L.UGeoJSONLayer({
    endpoint: gjsurl+'ae2025/collections/commune/items',
    minZoom: 0, maxZoom: 18, usebbox: true, onEachFeature: onEachFeature
  }),

// affichage de l'antimeridien
  "antimeridien" : L.geoJSON(
    { "type": "MultiPolygon",
      "coordinates": [
         [[[ 180.0,-90.0 ],[ 180.1,-90.0 ],[ 180.1,90.0],[ 180.0,90.0 ],[ 180.0,-90.0 ] ] ],
         [[[-180.0,-90.0 ],[-180.1,-90.0 ],[-180.1,90.0],[-180.0,90.0 ],[-180.0,-90.0 ] ] ]
      ]
    },
    { style: { "color": "red", "weight": 2, "opacity": 0.65 } }
  ),
    
// affichage d'une couche debug
  "debug" : new L.TileLayer(
    'https://visu.gexplor.fr/utilityserver.php/debug/{z}/{x}/{y}.png',
    {"format":"image/png","minZoom":0,"maxZoom":21,"detectRetina":false}
  )
};
map.addLayer(overlays["antimeridien"]);

L.control.layers(baseLayers, overlays).addTo(map);
    </script>
  </body>
</html>