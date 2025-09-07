<?php
/** Dessin d'une carte avec Leaflet.
 * Ce package a été conçu pour exploiter des données stockées dans le JdD MapDataset.
 * Il peut être utilisé avec des cartes créées à la volée en fournissant des données conformes au schéma du JdD.
 * Pour dessiner une carte, il faut:
 *  - créer un objet Map avec sa définition respectant le schéma de Map
 *  - créer les couches constituant la carte et les stocker dans Layer::$all en les créant avec Layer::create()
 *  - appeler Map::draw() qui génère le code JS adhoc et afficher ce code dans le navigateur
 * Un exemple est fourni ci-dessous.
 * Attention l'appel de draw() modifie le dossier de travail courant
 * @package Map
 */
namespace LLMap;

require_once __DIR__.'/datasets/dataset.inc.php';
require_once __DIR__.'/vendor/autoload.php';

use Dataset\Dataset;
use Lib\RecArray;
use Symfony\Component\Yaml\Yaml;
use JsonSchema\Validator;

/** Classe abstraite des couches pour générer le code JS correspondant à la couche */
abstract class Layer {
  readonly string $lyrId;
  readonly string $title;
  /** @var array<mixed> $params Les paramètres de l'appel. */
  readonly array $params;
  
  /** @var array<string,Layer> $all le dictionnaire des couches indexées par leur id */
  static array $all = [];
  
  /** Création d'une couche dans la bonne classe en fonction des paramètres.
   * L'intégrité des couches est vérifiée par checkIntegrity().
   * @param array<mixed> $def La définition de la couche respectant le schéma de la couche
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
      default => throw new \Exception("Cas $kind non prévu"),
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
  
  /** Retourne le code JS pour afficher la couche. */
  abstract function toJS(): string;
  
  /** Retourne une Layer comme un array avec son id pour affichage avec Yaml::dump().
   * @return array<string,mixed>
   */
  function asArray(): array {
    return [$this->lyrId => [
      substr(get_class($this), 4) => [
        'title'=> $this->title,
        'params'=> $this->params,
      ]
    ]];
  }
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
    // Le paramètre endpoint doit correspondre à un JdD et une collection de ce JdD
    // ex:       endpoint: '{gjsurl}NE110mPhysical/collections/ne_110m_coastline/items'
    if (!preg_match('!^{gjsurl}([^/]+)/collections/([^/]+)/items$!', $this->params['endpoint'], $matches)) {
      throw new \Exception("params[endpoint]=".$this->params['endpoint']." don't match");
    }
    $dsName = $matches[1];
    $cName = $matches[2];
    $ds = Dataset::get($dsName);
    if (!array_key_exists($cName, $ds->collections))
      throw new \Exception("Erreur, la collection $cName n'existe pas dans dans le JdD $dsName pour la couche $this->lyrId");
  }
  
  function toJS(): string {
    $urlOftheDir = Map::urlOftheDir();
    $params = $this->params;
    $params['endpoint'] = str_replace('{gjsurl}', "$urlOftheDir/geojson.php/", $params['endpoint']);
    $params = json_encode($params, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
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

/** Le code JavaScript paramétré de la carte utilisé par Map::draw(). */
define('JS_SRCE', [
<<<'EOT'
<!DOCTYPE HTML>
<html><head>
  <title>{title}</title>
  <meta charset="UTF-8">
  <!-- meta nécessaire pour le mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <!-- styles nécessaires pour le mobile -->
  <link rel='stylesheet' href='{leaflet}/llmap.css'>
  <!-- styles et src de Leaflet -->
  <link rel="stylesheet" href='{leaflet}/leaflet.css'/>
  <script src='{leaflet}/leaflet.js'></script>
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
  <script src="{leaflet}/leaflet.edgebuffer.js"></script>
  <!-- Include the Control.Coordinates plugin -->
  <link rel='stylesheet' href='{leaflet}/Control.Coordinates.css'>
  <script src='{leaflet}/Control.Coordinates.js'></script>
  <!-- Include the uGeoJSON plugin -->
  <script src="{leaflet}/leaflet.uGeoJSON.js"></script>
  <!-- plug-in d'appel des GeoJSON en AJAX -->
  <script src='{leaflet}/leaflet-ajax.js'></script>
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

/** Prend une carte définie conformément au schéma d'une carte et génère le code JS Leaflet la dessinant.
 */
class Map {
  /** @param array<mixed> $def La définition de la carte respectant le schéma de la carte. */
  function __construct(readonly array $def) {}
  
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
   * @param array<string,Layer> $layers - le dict. des Layers qui doit au moins contenir les Layer citées dans la carte
   * @param list<string> $layerNames La liste des noms des couches. */
  function drawLayers(array $layers, string $pattern, array $layerNames, string $jsCode): string {
    foreach ($layerNames as $layerName) {
      if (!($layer = $layers[$layerName] ?? null))
        throw new \Exception("Erreur baseLayer '$layerName' non définie");
      $jsCode = str_replace(
        $pattern,
        $layer->toJS().$pattern,
        $jsCode);
    }
    $jsCode = str_replace(",\n".$pattern, "\n", $jsCode);
    return $jsCode;
  }
  
  /** Construit l'URL du répertoire contenant ce fichier indépendamment de celle du script appelant. */
  static function urlOftheDir(): string {
    $dir = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']));
    //echo "dir=$dir<br>\n";
    $url = "$_SERVER[REQUEST_SCHEME]://$_SERVER[SERVER_NAME]$dir";
    //echo "url=$url<br>\n";
    return $url;
  }
  
  /** Génère le code JS de dessin de la carte.
   * @param array<string,Layer> $layers - le dict. des Layers qui doit au moins contenir les Layer citées dans la carte
   */
  function draw(array $layers): string {
    //echo "urlOftheDir()=",self::urlOftheDir(),"<br>\n";
    $urlOftheDir = self::urlOftheDir();
    // le chemin du répertoire leaflet doit être défini indépendamment du script appelant cette méthode
    $jsCode = str_replace('{leaflet}', "$urlOftheDir/leaflet", JS_SRCE[0]);
    $jsCode = str_replace('{title}', $this->def['title'], $jsCode);

    // les variables
    foreach ($this->def['vars'] as $varName => $varValue) {
      $jsCode = str_replace(
        "    var {varName} = {varValue};\n",
        "    var $varName = '$varValue';\n"."    var {varName} = {varValue};\n",
        $jsCode );
    }
    $jsCode = str_replace("    var {varName} = {varValue};\n", '', $jsCode );
      
    // les baseLeyrs
    $jsCode = $this->drawLayers($layers, "  {baseLayers}\n", $this->def['baseLayers'], $jsCode);
    
    // affichage par défaut de la baseLayer
    $defaultBaseLayer = $this->def['defaultBaseLayer'];
    if (!($layers[$defaultBaseLayer] ?? null))
      throw new \Exception("Erreur defaultBaseLayer '$defaultBaseLayer' non définie");
    $jsCode = str_replace('{defaultBaseLayer}', $defaultBaseLayer, $jsCode);
    
    // la déf. des overlays
    $jsCode = $this->drawLayers($layers, "  {overlays}\n", $this->def['overlays'], $jsCode);
    
    // les affichage par défaut des overlays
    foreach ($this->def['defaultOverlays'] as $defaultOverlay) {
      if (!($layers[$defaultOverlay] ?? null))
        throw new \Exception("Erreur defaultOverlay '$defaultOverlay' non définie");
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
  
  /** Affiche une carte.
   * @param array<string,Layer> $layers - le dict. des Layers qui doit au moins contenir les Layer citées dans la carte
   */
  function display(array $layers): void {
    echo "<h2>",$this->def['title'],"</h2>\n";
    echo '<pre>',Yaml::dump($this->def, 9, 2),"</pre>\n";
    $layersAsArray = [];
    foreach (['baseLayers','overlays'] as $lyrKind) {
      foreach ($this->def[$lyrKind] as $lyrId) {
        $layersAsArray[$lyrKind] = array_merge($layersAsArray[$lyrKind] ?? [], $layers[$lyrId]->asArray());
      }
    }
    echo "<h3>Couches</h3>\n";
    echo '<pre>',Yaml::dump($layersAsArray, 5, 2),"</pre>\n";
  }
};

class SchemaOfAMapAndItsLayers {
  /** Chemin du JdD MapDataset. */
  const YAML_FILE_PATH = __DIR__.'/datasets/mapdataset.yaml';
  
  /** @var array<mixed> $def - définition du schéma de AMapAndItsLayers construit à partir de celui de MapDataset. */
  readonly array $def;
  
  /** Construit la définition du schéma de AMapAndItsLayers construit à partir de celui de MapDataset. */
  function __construct() {
    $mapdataset = Yaml::parseFile(self::YAML_FILE_PATH);
    $schemaOfMapDataset = $mapdataset['$schema'];
    $this->def = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'definitions'=> $schemaOfMapDataset['definitions'],
      'type'=> 'object',
      'required'=> ['map','layers'],
      'additionalProperties'=> false,
      'properties'=> [
        'map'=> ['$ref'=> '#/definitions/schemaOfAMap'],
        'layers'=> ['$ref'=> '#/definitions/schemaOfLayers'],
      ],
    ];
  }
  
  /** Retourne le validateur de la déf d'une AMapAndItsLayers / son schéma.
   * @param array<mixed> $mapAndItsLayers - déf. de la AMapAndItsLayers */
  function validator(array $mapAndItsLayers): Validator {
    $validator = new Validator;
    $mapAndItsLayers = RecArray::toStdObject($mapAndItsLayers);
    $validator->validate($mapAndItsLayers, $this->def);
    return $validator;
  }
};

/** Prend la déf. d'une carte et de ses couches et construit un objet Map et un ens. d'objets Layer.
 */
class AMapAndItsLayers {
  readonly Map $map;
  /** @var array<string,Layer> $layers */
  readonly array $layers; 
  
  /** Valide la déf d'une AMapAndItsLayers / son schéma, renvoie null si Ok et sinon le Validator.
   * @param array<mixed> $def - déf. de la AMapAndItsLayers */
  static function isInValid(array $def): ?Validator {
    $schemaOfAMapAndItsLayers = new SchemaOfAMapAndItsLayers;
    $validator = $schemaOfAMapAndItsLayers->validator($def);
    return $validator->isValid() ? null : $validator;
  }
  
  /** Affiche les erreurs de non conformité de la définition / son schéma. */
  static function displayErrors(Validator $validator): void {
    if (!($errors = $validator->getErrors())) {
      echo "La définition est conforme à son schéma.<br>\n";
    }
    else {
      echo "<pre>La définition n'est pas conforme à son schéma. Violations:<br>\n";
      foreach ($errors as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }
  }
  
  /** @param array<mixed> $def La définition de la carte et des layers. */
  function __construct(readonly array $def) {
    if ($validator = self::isInValid($def)) {
      self::displayErrors($validator);
      throw new \Exception("La définition de AMapAndItsLayers n'est pas conforme à son schéma");
    }
    
    $layers = [];
    foreach ($def['layers'] as $lyrId => $lyrDef) {
      $layers[$lyrId] = Layer::create($lyrId, $lyrDef);
    }
    $this->layers = $layers;
    $this->map = new Map($def['map']);
  }
  
  function draw(): string { return $this->map->draw($this->layers); }

  function display(): void { $this->map->display($this->layers); }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // séparateur


$mapAilYamlDef = [
  <<<'EOT'
map:
  title: Carte NaturalEarth stylée
  vars:
    userverdir: 'http://localhost/gexplor/visu/'
  baseLayers:
    - OSM
    - FondBlanc
  defaultBaseLayer: OSM
  overlays:
    - NECoastlinesAndBoundaries
    - NEMappingUnits
    - NEMappingSubUnits
    - antimeridien
    - debug
  defaultOverlays:
    - NECoastlinesAndBoundaries
    - antimeridien
layers:
  OSM:
    title: OSM
    L.TileLayer:
      - 'https://{s}.tile.osm.org/{z}/{x}/{y}.png'
      - attribution: "© <a href='https://www.openstreetmap.org/copyright' target='_blank'>les contributeurs d’OpenStreetMap</a>"
  FondBlanc:
    title: 'Fond blanc'
    L.TileLayer:
      - '{userverdir}utilityserver.php/whiteimg/{z}/{x}/{y}.jpg'
      - { format: image/png, minZoom: 0, maxZoom: 21, detectRetina: false}
  NECoastlinesAndBoundaries:
    title: Couche NaturalEarth côtes et limites de pays stylée
    L.UGeoJSONLayer:
      endpoint: '{gjsurl}NaturalEarth/collections/coastlinesAndBoundaries/items'
      minZoom: 0
      maxZoom: 18
      usebbox: true
      onEachFeature: onEachFeature
      style: 'function(feature) { return feature.style; }'
  NEMappingUnits:
    title: Couche NaturalEarth unités carto. stylée
    L.UGeoJSONLayer:
      endpoint: '{gjsurl}NaturalEarth/collections/mappingUnits/items'
      minZoom: 0
      maxZoom: 18
      usebbox: true
      onEachFeature: onEachFeature
      style: 'function(feature) { return feature.style; }'
  NEMappingSubUnits:
    title: Couche NaturalEarth sous-unités carto. stylée
    L.UGeoJSONLayer:
      endpoint: '{gjsurl}NaturalEarth/collections/mappingSubUnits/items'
      minZoom: 0
      maxZoom: 18
      usebbox: true
      onEachFeature: onEachFeature
      style: 'function(feature) { return feature.style; }'
  Région:
    title: 'Région de AE COG PE'
    L.UGeoJSONLayer:
      endpoint: '{gjsurl}AeCogPe/collections/region/items'
      minZoom: 0
      maxZoom: 7
      usebbox: true
      onEachFeature: onEachFeature
  Département:
    title: 'Département de AE COG PE'
    L.UGeoJSONLayer:
      endpoint: '{gjsurl}AeCogPe/collections/departement/items'
      minZoom: 7
      maxZoom: 8
      usebbox: true
      onEachFeature: onEachFeature
  EPCI:
    title: 'EPCI de AE COG PE'
    L.UGeoJSONLayer:
      endpoint: '{gjsurl}AeCogPe/collections/epci/items'
      minZoom: 8
      maxZoom: 10
      usebbox: true
      onEachFeature: onEachFeature
  Commune:
    title: 'Commune de AE COG PE'
    L.UGeoJSONLayer:
      endpoint: '{gjsurl}AeCogPe/collections/commune/items'
      minZoom: 10
      maxZoom: 18
      usebbox: true
      onEachFeature: onEachFeature
  antimeridien:
    title: antimeridien
    L.geoJSON:
      - type: MultiPolygon
        coordinates:
          - [[[ 180.0,-90.0],[ 180.1,-90.0],[ 180.1,90.0],[ 180.0,90.0],[ 180.0,-90.0]]]
          - [[[-180.0,-90.0],[-180.1,-90.0],[-180.1,90.0],[-180.0,90.0],[-180.0,-90.0]]]
      - style:
          color: red
          weight: 2
          opacity: 0.65
  debug:
    title: debug
    L.TileLayer:
      - '{userverdir}utilityserver.php/debug/{z}/{x}/{y}.png'
      - { format: image/png, minZoom: 0, maxZoom: 21, detectRetina: false}
EOT
];
$mapAil = new AMapAndItsLayers(Yaml::parse($mapAilYamlDef[0]));

switch ($action = $_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=display'>display</a><br>\n";
    echo "<a href='?action=draw'>draw</a><br>\n";
    break;
  }
  case 'display': {
    $mapAil->display();
    break;
  }
  case 'draw': {
    echo $mapAil->draw();
    break;
  }
}
