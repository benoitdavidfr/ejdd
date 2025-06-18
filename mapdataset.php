<?php
/** JdD des cartes. */

require_once 'dataset.inc.php';
require_once 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

class MapDataset extends Dataset {
  const YAML_FILE_PATH = 'mapdataset.yaml';
  /** @var array<string,mixed> $data Les données des différentes sections du jeu */
  readonly array $data;
  
  function __construct() {
    $dataset = Yaml::parseFile(self::YAML_FILE_PATH);
    parent::__construct($dataset['title'], $dataset['description'], $dataset['$schema']);
    $data = [];
    foreach ($dataset as $key => $value) {
      if (!in_array($key, ['title', 'description', '$schema']))
        $data[$key] = $value;
    }
    $this->data = $data;
  }
  
  function getData(string $section, mixed $filtre=null): array { return $this->data[$section]; }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 


/** Classe abstraite des couvches pour générer le code JS correspondant à la couche */
abstract class Layer {
  readonly string $lyrId;
  readonly string $title;
  /** @var array<mixed> $params Les paramètres de l'appel. */
  readonly array $params;
  
  /** @var array<string,Layer> $all le dictionnaire des couches indexées par leur id */
  static array $all = [];
  
  /** Création d'une couche dans la bonne classe en focntion des paramètres.
   * @param array<mixed> $def La définition de la couche
   */
  static function create(string $lyrId, array $def): self {
    $title = $def['title'];
    unset($def['title']);
    $kind = array_keys($def)[0];
    $params = $def[$kind];
    switch ($kind) {
      case 'L.TileLayer': return new L_TileLayer($lyrId, $title, $params);
      case 'L.UGeoJSONLayer': return new L_UGeoJSONLayer($lyrId, $title, $params);
      case 'L.geoJSON': return new L_geoJSON($lyrId, $title, $params);
      default: throw new Exception("Cas $kind non prévu");
    }
  }
  
  /** Création d'une couche.
   * @param array<mixed> $params Les paramètres d'appel
   */
  function __construct(string $lyrId, string $title, array $params) {
    $this->lyrId = $lyrId;
    $this->title = $title;
    $this->params = $params;
  }
  
  abstract function toJS(): string;
};

/** Classe concrète des couches L_TileLayer*/
class L_TileLayer extends Layer {
  const JS_CODE = "  // affichage de la couche {id}\n"
                 ."    '{id}' : new L.TileLayer(\n"
                 ."      '{url}',\n"
                 ."      {options}\n"
                 ."    ),\n";

  /** Création d'une couche.
   * @param array<mixed> $params Les paramètres d'appel
   */
  function __construct(string $lyrId, string $title, array $params) {
    parent::__construct($lyrId, $title, $params);
  }
  
  function toJS(): string {
    //echo '<pre>L_TileLayer $params='; print_r($this->params); echo "</pre>\n";
    return str_replace([
      '{id}','{url}','{options}'],
      [ $this->lyrId,
        $this->params[0],
        json_encode($this->params[1],  JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)
      ],
      self::JS_CODE
    );
  }
};

/** Classe concrète des couches L_UGeoJSONLayer*/
class L_UGeoJSONLayer extends Layer {
  const JS_CODE = "  // affichage de la couche {id}\n"
                 ."    '{id}' : new L.UGeoJSONLayer({params}),\n";

  /** Création d'une couche.
   * @param array<mixed> $params Les paramètres d'appel
   */
  function __construct(string $lyrId, string $title, array $params) {
    parent::__construct($lyrId, $title, $params);
  }
  
  function toJS(): string {
    $params = json_encode($this->params, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    /** la valeur fournie par le champ onEachFeature est un nom de fonction qui ne doit pas être entre "" */
    $params = preg_replace('!"(onEachFeature)":"([^"]+)"!', '"$1":$2', $params);
    //echo '<pre>L_UGeoJSONLayer $params='; print_r($this->params); echo " -> $params</pre>\n";
    return str_replace(
      ['{id}','{params}'],
      [$this->lyrId, $params],
      self::JS_CODE
    );
  }
};

/** Classe concrète des couches L_geoJSON*/
class L_geoJSON extends Layer {
  const JS_CODE = "  // affichage de {id}\n"
                 ."    '{id}' : L.geoJSON(\n"
                 ."      {geoJsonGeometry},\n"
                 ."      {style}\n"
                 ."    ),\n";

  /** Création d'une couche.
   * @param array<mixed> $params Les paramètres d'appel
   */
  function __construct(string $lyrId, string $title, array $params) {
    parent::__construct($lyrId, $title, $params);
  }
  
  function toJS(): string {
    //echo '<pre>L_geoJSON $params='; print_r($this->params); echo "</pre>\n";
    return str_replace(
      ['{id}', '{geoJsonGeometry}', '{style}'],
      [ $this->lyrId,
        json_encode($this->params[0],  JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        json_encode($this->params[1],  JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)
      ],
      self::JS_CODE
    );
  }
};

/** Le code JavaScript paramétré de la carte */
define('JS_SRCE', [
<<<'EOT'
  <!DOCTYPE HTML>
  <html><head>
    <title>{title}</title>
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
      var {varName} = {varValue};

  // affiche les caractéristiques de chaque feature
  var onEachFeature = function (feature, layer) {
    layer.bindPopup(
      '<b>Feature</b><br>'
      +'<pre>'+JSON.stringify(feature.properties,null,' ')+'</pre>'
    );
    var name = 'undef';
    if (typeof feature.properties.nom !== 'undefined') {
      name = feature.properties.nom;
    }
    else if (typeof feature.properties.name !== 'undefined') {
      name = feature.properties.name;
    }
    layer.bindTooltip(name);
  }

  var map = L.map('map').setView([46.5,3],6);  // view pour la zone
  L.control.scale({position:'bottomleft', metric:true, imperial:false}).addTo(map);

  // activation du plug-in Control.Coordinates
  var c = new L.Control.Coordinates();
  c.addTo(map);
  map.on('click', function(e) { c.setCoordinates(e); });

  var baseLayers = {
    {baseLayers}
  };
  map.addLayer(baseLayers["{defaultBaseLayer}"]);

  var overlays = {
    {overlays}
  };
  map.addLayer(overlays["{defaultOverlay}"]);

  L.control.layers(baseLayers, overlays).addTo(map);
      </script>
    </body>
  </html>
EOT
]
);
/** Classe de la carte ; prend une carte définie dans mapdataset.yaml et génère le code JS Leaflet pour la dessinner. */
class Map {
  /** @var array<mixed> $def La définition de la carte issue du JdD. */
  readonly array $def;
  
  /** @param array<mixed> $def La définition de la carte issue du JdD. */
  function __construct(array $def) { $this->def = $def; }
  
  /** Génère le code JS pour les couches.
   * @param list<string> $layerNames La liste des noms des couches. */
  function drawLayers(string $pattern, array $layerNames, string $jsCode): string {
    foreach ($layerNames as $layerName) {
      if (!($layer = Layer::$all[$layerName] ?? null))
        throw new Exception("Erreur baseLayer '$layerName' non définie");
      $jsCode = str_replace(
        $pattern,
        $layer->toJS().$pattern,
        $jsCode);
    }
    $jsCode = str_replace(",\n".$pattern, "\n", $jsCode);
    return $jsCode;
  }
  
  /** génère le code JS de dessin de la carte. */
  function draw(): string {
    $jsCode = str_replace(['{title}'], [$this->def['title']], JS_SRCE[0]);

    // les variables
    foreach ($this->def['vars'] as $varName => $varValue) {
      $jsCode = str_replace(
        "      var {varName} = {varValue};\n",
        "      var $varName = '$varValue';\n"."      var {varName} = {varValue};\n",
        $jsCode );
    }
    $jsCode = str_replace("      var {varName} = {varValue};\n", '', $jsCode );
    
    // les baseLeyrs
    $jsCode = $this->drawLayers("    {baseLayers}\n", $this->def['baseLayers'], $jsCode);
    
    // affichage par défaut de la baseLayer
    $jsCode = str_replace('{defaultBaseLayer}', $this->def['defaultBaseLayer'], $jsCode);
    
    // la déf. des overlays
    $jsCode = $this->drawLayers("    {overlays}\n", $this->def['overlays'], $jsCode);
    
    // les affichage par défaut des overlays
    foreach ($this->def['defaultOverlays'] as $defaultOverlay) {
      $jsCode = str_replace(
        "  map.addLayer(overlays[\"{defaultOverlay}\"]);\n",
        "  map.addLayer(overlays[\"{defaultOverlay}\"]);\n"
        ."  map.addLayer(overlays[\"$defaultOverlay\"]);\n",
        $jsCode
      );
    }
    $jsCode = str_replace("  map.addLayer(overlays[\"{defaultOverlay}\"]);\n", '', $jsCode);
    
    $jsCode = str_replace('"{gjsurl}', 'gjsurl+"', $jsCode);
    
    return $jsCode;
  }
};

switch ($_GET['action'] ?? null) {
  case null: {
    echo "Rien à faire pour construire le JdD<br>\n";
    echo "<a href='?action=test'>Test du code</a><br>\n";
    echo "<a href='index.php?action=validate&dataset=MapDataset'>Vérifier la conformité des données</a><br>\n";
    echo "<a href='?action=listMaps'>Liste les cartes</a><br>\n";
    break;
  }
  case 'test': {
    $mapDataset = new MapDataset;
    echo '<pre>maps='; print_r($mapDataset->getData('maps'));
    break;
  }
  case 'listMaps': {
    echo "<h2>Liste des cartes</h2>\n";
    $mapDataset = Dataset::get('MapDataset');
    $maps = $mapDataset->getData('maps');
    foreach ($maps as $mapKey => $map)
      echo "<a href='?action=draw&map=$mapKey'>$map[title]</a><br>\n";
    break;
  }
  case 'draw': {
    $mapDataset = Dataset::get('MapDataset');
    $map = new Map($mapDataset->getData('maps')[$_GET['map']]);
    foreach ($mapDataset->getData('layers') as $lyrId => $layer) {
      Layer::$all[$lyrId] = Layer::create($lyrId, $layer);
    }
    echo $map->draw();
    break;
  }
  default: {
    echo "Action $_GET[action] inconnue<br>\n";
    break;
  }
}
