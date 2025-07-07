<?php
/** JdD des cartes.
 * Ce JdD définit des cartes dessinables en Leaflet sans avoir à éditer le code JS correspondant.
 * La définition des cartes est stockée dans le fichier mapdataset.yaml.
 * Une carte est principalement composée de couches de base (baseLayers) et de couches de superposition (overlays),
 * chacune définie dans la section layer notamment par un type et des paramètres.
 * Les cartes peuvent être dessinées à partir de l'IHM définie dans ce script.
 */

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
  
  function getTuples(string $section, mixed $filtre=null): Generator {
    foreach ($this->data[$section] as $key => $tuple)
      yield $key => $tuple;
    return;
  }
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
  
  /** Création d'une couche dans la bonne classe en fonction des paramètres.
   * L'intégrité des couches est vérifiée par checkIntegrity().
   * @param array<mixed> $def La définition de la couche
   */
  static function create(string $lyrId, array $def): self {
    $title = $def['title'];
    unset($def['title']);
    $kind = array_keys($def)[0];
    $params = $def[$kind];
    $layer = match ($kind) {
      'L.TileLayer' => new L_TileLayer($lyrId, $title, $params),
      'L.UGeoJSONLayer' => new L_UGeoJSONLayer($lyrId, $title, $params),
      'L.geoJSON' => new L_geoJSON($lyrId, $title, $params),
      default => throw new Exception("Cas $kind non prévu"),
    };
    $layer->checkIntegrity();
    return $layer;
  }
  
  /** Création d'une couche.
   * @param array<mixed> $params Les paramètres d'appel
   */
  function __construct(string $lyrId, string $title, array $params) {
    $this->lyrId = $lyrId;
    $this->title = $title;
    $this->params = $params;
  }
  
  /** Les erreurs d'intégité soulèvent des exceptions. */
  abstract function checkIntegrity(): void;
  
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
  
  /** Les erreurs d'intégité soulèvent des exceptions. */
  function checkIntegrity(): void {}
  
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
  
  /** Les erreurs d'intégité soulèvent des exceptions. */
  function checkIntegrity(): void {
    // Le paramètre endpoint doit correspondre à un JdD et une section de ce JdD
    // ex:       endpoint: '{gjsurl}NE110mPhysical/collections/ne_110m_coastline/items'
    if (!preg_match('!^{gjsurl}([^/]+)/collections/([^/]+)/items$!', $this->params['endpoint'], $matches)) {
      throw new Exception("params[endpoint]=".$this->params['endpoint']." don't match");
    }
    $dsName = $matches[1];
    $sectName = $matches[2];
    $ds = Dataset::get($dsName);
    if (!array_key_exists($sectName, $ds->sections))
      throw new Exception("Erreur, la section $sectName n'existe pas dans dans le JdD $dsName pour la couche $this->lyrId");
  }
  
  function toJS(): string {
    $params = json_encode($this->params, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    /** la valeur fournie par le champ onEachFeature est un nom de fonction qui ne doit pas être entre "" */
    $params = preg_replace('!"(onEachFeature)":"([^"]+)"!', '"$1":$2', $params);
    //echo '<pre>L_UGeoJSONLayer $params='; print_r($this->params); echo " -> $params</pre>\n";
    $params = preg_replace('!"(style)":"([^"]+)"!', '"$1":$2', $params);
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
  
  /** Les erreurs d'intégité soulèvent des exceptions. */
  function checkIntegrity(): void {}
  
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
  
  /** Retourne la liste des erreurs d'intégrité de la définition de la carte.
   * @return list<string>
   */
  function integrityErrors(string $mapId): array {
    $errors = [];
    // baseLayers
    foreach ($this->def['baseLayers'] as $baseLyr) {
      if (!isset(Layer::$all[$baseLyr]))
        $errors[] = "Erreur: la baseLayer $baseLyr de la carte $mapId n'est pas définie comme couche.<br>\n";
    }
    // defaultBaseLayer
    if (!in_array($this->def['defaultBaseLayer'], $this->def['baseLayers']))
      $errors[] = "Erreur: la defaultBaseLayer ".$this->def['defaultBaseLayer']
        ." de la carte $mapId n'est pas définie comme baseLayer.<br>\n";
    // overlays
    foreach ($this->def['overlays'] as $overlay) {
      if (!isset(Layer::$all[$overlay]))
        $errors[] = "Erreur: l'overlay $overlay de la carte $mapId n'est pas définie comme couche.<br>\n";
    }
    // defaultOverlays
    foreach ($this->def['defaultOverlays'] as $overlay) {
      if (!in_array($overlay, $this->def['overlays']))
        $errors[] = "Erreur: le defaultOverlay $overlay de la carte $mapId n'est pas définie comme overlay.<br>\n";
    }
    return $errors;
  }
  
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
        "    var {varName} = {varValue};\n",
        "    var $varName = '$varValue';\n"."    var {varName} = {varValue};\n",
        $jsCode );
    }
    $jsCode = str_replace("    var {varName} = {varValue};\n", '', $jsCode );
      
    // les baseLeyrs
    $jsCode = $this->drawLayers("  {baseLayers}\n", $this->def['baseLayers'], $jsCode);
    
    // affichage par défaut de la baseLayer
    $defaultBaseLayer = $this->def['defaultBaseLayer'];
    if (!(Layer::$all[$defaultBaseLayer] ?? null))
      throw new Exception("Erreur defaultBaseLayer '$defaultBaseLayer' non définie");
    $jsCode = str_replace('{defaultBaseLayer}', $defaultBaseLayer, $jsCode);
    
    // la déf. des overlays
    $jsCode = $this->drawLayers("  {overlays}\n", $this->def['overlays'], $jsCode);
    
    // les affichage par défaut des overlays
    foreach ($this->def['defaultOverlays'] as $defaultOverlay) {
      if (!(Layer::$all[$defaultOverlay] ?? null))
        throw new Exception("Erreur defaultOverlay '$defaultOverlay' non définie");
      $jsCode = str_replace(
        "map.addLayer(overlays[\"{defaultOverlay}\"]);\n",
        "map.addLayer(overlays[\"$defaultOverlay\"]);\n"."map.addLayer(overlays[\"{defaultOverlay}\"]);\n",
        $jsCode
      );
    }
    $jsCode = str_replace("map.addLayer(overlays[\"{defaultOverlay}\"]);\n", '', $jsCode);
    
    foreach ($this->def['vars'] as $varName => $varValue) {
      // Attention les accolades dans une chaine ont une signification particulière en Php
      $varNameWithAcc = '{'.$varName.'}';
      //echo "Remp $varName: : \"$varNameWithAcc -> $varName+\" + '$varNameWithAcc -> $varName+'<br>\n";
      $jsCode = str_replace(["\"$varNameWithAcc", "'$varNameWithAcc"], ["$varName+\"", "$varName+'"], $jsCode);
    }
      
    return $jsCode;
  }
};

switch ($_GET['action'] ?? null) {
  case null: {
    echo "Rien à faire pour construire le JdD<br>\n";
    //echo "<a href='index.php?action=validate&dataset=MapDataset'>Vérifier la conformité des données</a><br>\n";
    echo "<a href='?action=validate&dataset=MapDataset'>Vérifier la conformité du JdD.</a><br>\n";
    echo "<a href='?action=refIntegrity'>Vérifier les contraintes d'intégrité entre cartes et couches</a><br>\n";
    echo "<a href='?action=listMaps'>Liste les cartes à dessiner</a><br>\n";
    break;
  }
  case 'refIntegrity': {
    echo "<h2>Contraintes d'intégrité</h2>\n";
    $mapDataset = Dataset::get('MapDataset');
    foreach ($mapDataset->getTuples('layers') as $lyrId => $layer) {
      Layer::$all[$lyrId] = Layer::create($lyrId, $layer);
    }
    foreach ($mapDataset->getTuples('maps') as $mapId => $map) {
      $map = new Map($map);
      if ($errors = $map->integrityErrors($mapId)) {
        echo "<pre>errors="; print_r($errors); echo "</pre>\n";
      }
      else {
        echo "Aucune erreur d'intégrité détectée sur $mapId.<br>\n";
      }
    }
    break;
  }
  case 'validate': {
    $dataset = Dataset::get($_GET['dataset']);
    if ($dataset->schemaIsValid()) {
      echo "Le schéma du JdD est conforme au méta-schéma JSON Schema et au méta-schéma des JdD.<br>\n";
    }
    else {
      $dataset->displaySchemaErrors();
    }

    if ($dataset->isValid(false)) {
      echo "Le JdD est conforme à son schéma.<br>\n";
    }
    else {
      $dataset->displayErrors();
    }
    break;
  }
  case 'listMaps': {
    echo "<h2>Liste des cartes à dessiner</h2>\n";
    $mapDataset = Dataset::get('MapDataset');
    foreach ($mapDataset->getTuples('maps') as $mapKey => $map)
      echo "<a href='?action=draw&map=$mapKey'>Dessiner $map[title]</a><br>\n";
    break;
  }
  case 'draw': {
    // Avant de dessiner une carte, je vérifie:
    //  1) que la définition des cartes est correcte du point de vue schéma
    //  2) que la définition de la carte à dessiner ne présente pas d'erreurs d'intégrité
    $mapDataset = Dataset::get('MapDataset');
    
    if (!$mapDataset->schemaIsValid() || !$mapDataset->isValid(false)) {
      echo "Erreur, le schéma du JdD des cartes est invalide ou certaines cartes ne sont pas conformes au schéma du JdD.<br>\n",
           "Dessin de la carte impossible.<br>\n",
           "<a href='?action=validate&dataset=MapDataset'>Vérifier la conformité du JdD.</a><br>\n";
      die();
    }
    $map = new Map($mapDataset->getOneTupleByKey('maps', $_GET['map']));
    foreach ($mapDataset->getTuples('layers') as $lyrId => $layer) {
      Layer::$all[$lyrId] = Layer::create($lyrId, $layer);
    }
    if ($errors = $map->integrityErrors($_GET['map'])) {
      echo "Erreur, la définition de la carte $_GET[map] présente des erreurs d'intégrité. Dessin impossible.<br>\n";
      echo "<pre>errors="; print_r($errors); echo "</pre>\n";
      die();
    }
    //echo '<pre>$map='; print_r($map);
    echo $map->draw();
    break;
  }
  default: {
    echo "Action $_GET[action] inconnue<br>\n";
    break;
  }
}
