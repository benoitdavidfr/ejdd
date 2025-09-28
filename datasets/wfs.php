<?php
/** Wfs - Catégorie des Dataset WFS.
 * Fonctionne en articulation avec WfsNs qui définit un jeu de données correspondant aux FeatureTypes d'un espace de noms d'un serveur WFS.
 *
 * @package Dataset
 */
namespace Dataset;

/** Actions à réaliser. */
const A_FAIRE_WFS = [
<<<'EOT'
Actions à réaliser:
  - ajouter la possibilité d'obtenir le nbre de features d'une collection. Ce pourrait être optionnel.
  - quand je récupère du GML, je pourrais ne pas imposer de srs et demander à ogr2ogr de faire la conversion de srs
  - pb de synchro entre la pagination et le début de la plage demandée
    - si dans getItems() start ne correspond pas à un début de page, les caches sont multipliés
EOT
];

const BUG_WFS = [
  <<<'EOT'
Pour effectuer les tests:
  IgnWfs:          Wfs 2.0.0 aGeoJSON
  BDTopage2025:    WfsNsUrl 2.0.0 aGeoJSON
  SextantBiologie: WfsNsUrl 2.0.0 ssGeoJSON
  SextantDCE:      WfsNsUrl 1.1.0 SsNs aGeoJSON
  SextantEnvMarin: Wfs 1.1.0 SsNs aGeoJSON
  GéoLittoral:     WfsNsUrl 2.0.0 ssGeoJSON

Tests à effectuer:
  - properties
  - getFeaturesOnBBox
  - skip
EOT
];

const NOTE_ESPACES_DE_NOMS_WFS = [
<<<'EOT'
L'utilisation d'espaces de noms XML avec SimpleXML est compliquée car son affichage ne permet pas de visualiser les sous-éléments
dans les différents espaces.
Par ailleurs, les espaces sont différents en WFS 1.1.0 et en WFS 2.0.0.
J'utilise donc un contournement un peu approximatif.
Dans le XML, soit je remplace les ':' séparant le préfixe par '__', soit je supprime ces préfixes.
Cette 2ème solution ne fonctionne pas quand il existe des attributs utilisant un espace de nom comme 'xlink:href'.

Cette transformation et ce décodage XML sont cantonnés dans les classes:
  - WfsCap qui analyse les capacités fournies en XML
  - WfsProperties qui analyse le XML issu de DescribeFeatureType et qui est utilisé par WfsNs.

Je pourrais utiliser des XPath avec SimpleXml, ce qui serait plus fiable.

EOT
];

const NOTE_SRS_WFS = [
  <<<'EOT'
La définition des identifiants pour les srs est particulièrement chaotique.
Le SRS est nécessaire à 2 endroits:
  1) dans la requête GetFeature pour indiquer dans quel SRS la géométrie est demandée
     il est cependant possible que le format GeoJSON impose un SRS CRS:84
     et par ailleurs qd on récupère du GML la conversion en GeoJSON peut gérer la conversion des coordonnées
  2) en "5° coordonnée" dans le bbox passé dans GetFeature
ChatGPT indiquait que de nombreux serveurs sont mal configurés et exposent des SRS incorrects
EOT
];

require_once __DIR__.'/dataset.inc.php';
require_once __DIR__.'/../geom/geojson.inc.php';
require_once __DIR__.'/../ogr/ogr2ogr.php';

//use GeoJSON\Feed;
use GeoJSON\Geometry;
use BBox\GBox as BBox;
use Ogr\Ogr2ogr;

/** Gère un cache des appels Http GET pour Wfs.
 * Les fichiers sont stockés dans WfsCache::PATH.
 */
class WfsCache {
  //const NOCACHE = true; // suspension du cache notamment pour des tests
  const NOCACHE = false; // activation du cache en fonctionnement normal
  const PATH = __DIR__.'/wfscache/';
  
  /** Extrait du $http_response_header le code de retout Http.
   * @param list<string> $http_response_header
   */ 
  static function httpResponseCode(array $http_response_header): ?string {
    if (!preg_match('!^HTTP/1\.. (\d{3}) !', $http_response_header[0], $matches))
      return null;
    else
      return $matches[1];
  }
  
  /** Retourne le chemin du répertoire contenant le fichier. */
  static function dirPath(string $filePath): string {
    $exploded = explode('/', $filePath);
    array_pop($exploded);
    return implode('/', $exploded);
  }
  
  /** Teste si le répertoire du filePath existe et si non le crée, de manière récursive. */
  static function createDir(string $filePath): void {
    $dirPath = self::dirPath($filePath);
    if (!is_dir($dirPath)) {
      self::createDir($dirPath);
      //echo "Création de $dirPath<br>\n";
      mkdir($dirPath);
    }
  }
  
  /** Lecture d'un flux.
   * @param ?string $filePath - chemin du fichier du cache relatif à PATH ou null si pas de cache
   * @param string $url - URL du flux à lire
   */
  static function get(?string $filePath, string $url): string {
    if (self::NOCACHE)    // @phpstan-ignore if.alwaysFalse (Si le cache est suspendu)
      $filePath = null;   // alors $filePath = null ce qui rend impossible l'utilisation du cache
    if ($filePath) {
      $filePath = self::PATH.$filePath;
      if (is_file($filePath))
        return file_get_contents($filePath);
    }

    $context = stream_context_create([
      'http'=> [
        'ignore_errors'=> true,
      ],
    ]);
    $string = file_get_contents($url, false, $context);
    if (!$http_response_header) {
      echo "<pre>$url -> $string\n";
      echo "http_response_header non défini\n";
      throw new \Exception("Ouverture $url impossible");
    }
    if (self::httpResponseCode($http_response_header) <> '200') {
      echo "<pre>$url -> $string\n";
      echo '$http_response_header='; print_r($http_response_header);
      throw new \Exception("Ouverture $url impossible");
    }
    
    if ($string && $filePath) {
      self::createDir($filePath);
      file_put_contents($filePath, $string);
    }
    return $string;
  }
  
  /** Efface le contenu du répertoire dont le path est passé en paramètre. */
  static function delete(string $dirPath): void {
    $dir = dir(self::PATH.$dirPath);
    while ($entry = $dir->read()){
      if (in_array($entry, ['.','..'])) continue;
      if (is_file(self::PATH."$dirPath/$entry")) {
        echo "$entry is a file<br>\n";
        unlink(self::PATH."$dirPath/$entry");
      }
      elseif (is_dir(self::PATH."$dirPath/$entry")) {
        echo "$entry is a dir<br>\n";
        self::delete("$dirPath/$entry");
        rmdir(self::PATH."$dirPath/$entry");
      }
      else {
        echo "$entry is neiher a dir nor a file<br>\n";
      }
    } 
  }
};

/** Choix du paramètre outputFormat pour GetFeature.
 * Les différents serveurs WFS n'utilisent pas le même paramètre outputFormat par exemple pour GeoJSON:
 *  - IgnWfs -> application/json
 *  - BDTopage2025Wfs -> application/json; subtype=geojson
 *  - DCE -> application/vnd.geo+json
 * Cette classe gère les différents libellés de format et choisit le meilleur format pour GetFeature.
 */
class WfsOutputFormat {
  /** Liste de type MIME GML fréquemment utilisés en précisant GML3 ou GML2. */
  const GML_MIME = [
    'application/gml+xml; version=3.2'=> 'GML3',
    'text/xml; subtype=gml/2.1.2'=> 'GML2',
    'text/xml; subtype=gml/3.1.1'=> 'GML3',
    'text/xml; subtype=gml/3.2'=> 'GML3',
    'text/xml; subtype=gml/3.2.1'=> 'GML3',
  ];
  
  /** Un objet est créé avec le format tel qu'il est indiqué dans les capacités du serveur WFS. */
  function __construct(readonly string $fmt) {}
  
  function __toString(): string { return $this->fmt; }
  
  /** Indique si le format correspond à GeoJSON ou GML ou autre (null).
   * @return 'GeoJSON'|'GML'|null */
  function type(): ?string {
    if (substr($this->fmt, 0, strlen('application/json')) == 'application/json')
      return 'GeoJSON';
    elseif ($this->fmt == 'application/vnd.geo+json')
      return 'GeoJSON';
    elseif (substr($this->fmt, 0, strlen('application/gml+xml')) == 'application/gml+xml')
      return 'GML';
    elseif (substr($this->fmt, 0, strlen('text/xml; subtype=gml')) == 'text/xml; subtype=gml')
      return 'GML';
    else
      return null;
  }
  
  /** Cherche à distinguer GML3 de GML2.
   * @return 'GeoJSON'|'GML'|'GML3'|'GML2'|null */
  function subType(): ?string {
    if (substr($this->fmt, 0, strlen('application/json')) == 'application/json')
      return 'GeoJSON';
    elseif ($this->fmt == 'application/vnd.geo+json')
      return 'GeoJSON';
    elseif (substr($this->fmt, 0, strlen('application/gml+xml')) == 'application/gml+xml')
      return self::GML_MIME[$this->fmt] ?? 'GML';
    elseif (substr($this->fmt, 0, strlen('text/xml; subtype=gml')) == 'text/xml; subtype=gml')
      return self::GML_MIME[$this->fmt] ?? 'GML';
    else
      return null;
  }

  /** Retourne l'extension de nom de fichier correspondant au format. */
  function ext(): string {
    return match($this->type()) {
      'GeoJSON' => '.json',
      'GML' => '.gml',
      default => throw new \Exception("Pas d'extension pour $this->fmt"),
    };
  }
    
  /** Choisit le meilleur format pour GetFeature.
   * Si possible utilise GeoJSON, sinon GML3, sinon GML2, sinon GML, sinon null.
   */
  static function bestForGetFeature(WfsCap $wfsCap): ?self {
    $best = [];
    foreach ($wfsCap->outputFormatsForGetFeature() as $of) {
      if ($stype = $of->subType())
        $best[$stype] = $of;
    }
    if (isset($best['GeoJSON']))
      return $best['GeoJSON'];
    elseif (isset($best['GML3']))
      return $best['GML3'];
    elseif (isset($best['GML2']))
      return $best['GML2'];
    elseif (isset($best['GML']))
      return $best['GML'];
    else
      return null;
  }
};

/** Exploite le XML des capacités d'un serveur WFS pour en déduire notamment le schéma JSON du JdD. */
class WfsCap {
  readonly \SimpleXMLElement $elt;
  
  function __construct(readonly string $dsName, string $xml) {
    // Remplace les ':' des préfixes des espaces de noms par '__' 
    $xml = preg_replace('!<(/)?(([^:]+):)!', '<$1$3__', $xml);
    // remise de 'http: qui n'est pas un prefixe d'espace de noms
    $xml = preg_replace('!http__!', 'http:', $xml);
    //echo "<pre>Contenu XML:\n",htmlentities($xml),"</pre>\n"; die();

    //header('Content-Type: application/xml'); die($cap);
    if (!($elt = simplexml_load_string($xml))) {
      echo "<b>Erreur simplexml_load_string sur les capacités de $this->dsName</b><br>\n";
      echo "<pre>Contenu XML:\n",htmlentities($xml),"</pre>\n";
      throw new \Exception("Les capacités ne peuvent pas être transformées par simplexml_load_string()");
    }
    
    //echo '<pre>$elt='; print_r($elt);
    $this->elt = $elt;
  }
  
  /** Retourne la version du protocole WFS. */
  function version(): string {
    return (string)$this->elt->ows__ServiceIdentification->ows__ServiceTypeVersion;
  }
  
  /** Convertit un coin d'un WGS84BoundingBox en Position.
   * Retourne [] ssi $WGS84BoundingBox vide
   * @return TPos */
  private static function corner2Pos(?\SimpleXMLElement $corner): array {
    if (!$corner)
      return [];
    elseif (preg_match('!^([-\d\.E]+) ([-\d\.E]+)$!', $corner, $matches))
      return [floatval($matches[1]), floatval($matches[2])];
    else
      throw new \Exception("No match sur '$corner'");
  }
  
  /** Convertit un WGS84BoundingBox en 4 coordonnées xmin,ymin,xmax,ymax.
   * Retourne [] si $WGS84BoundingBox n'est pas défini.
   * @return array<int,number>
   */
  private static function WGS84BoundingBoxTo4Coordinates(\SimpleXMLElement $WGS84BoundingBox): array {
    $lc = self::corner2Pos($WGS84BoundingBox->ows__LowerCorner);
    $uc = self::corner2Pos($WGS84BoundingBox->ows__UpperCorner);
    if (!$lc)
      return [];
    $coords = [$lc[0], $lc[1], $uc[0], $uc[1]];
    //echo '<pre>WGS84BoundingBoxTo4Coordinates() returns '; print_r($coords); echo "</pre>\n";
    return $coords;
  }
  
  /** Dict. des FeatureTypes Name => différentes infos.
   * @return array<string,mixed> */
  function featureTypes(): array {
    $featureTypes = [];
    foreach ($this->elt->FeatureTypeList->FeatureType as $featureType) {
      $ftname = str_replace('__', ':', (string)$featureType->Name);
      $featureTypes[$ftname] = [
        'title'=> str_replace('__', ':', (string)$featureType->Title),
        'abstract'=> (string)$featureType->Abstract,
        'DefaultCRS'=> (string)$featureType->DefaultCRS,
        'WGS84BoundingBox'=> self::WGS84BoundingBoxTo4Coordinates($featureType->ows__WGS84BoundingBox),
      ];
    }
    return $featureTypes;
  }
  
  /** Liste des espaces de noms des FeatureTypeNames.
   * @return list<string> */
  function namespaces(): array {
    $namespaces = [];
    foreach (array_keys($this->featureTypes()) as $ftName) {
      if (preg_match('!^([^:]+):!', $ftName, $matches))
        $namespaces[$matches[1]] = 1;
      else
        $namespaces[''] = 1;
    }
    return array_keys($namespaces);
  }
  
  /** Construit le schema JSON à partir des capacités.
   * @return array<mixed> */
  function jsonSchemaOfTheDs(): array {
    $collections = [
      '$schema'=> ['description'=> "Le schéma du JdD", 'type'=> 'object'],
    ];
    //echo '$elt='; print_r($this->elt);
    //echo 'FeatureTypeList='; print_r($this->elt->FeatureTypeList->FeatureType);
    foreach ($this->elt->FeatureTypeList->FeatureType as $featureType) {
      //print_r($featureType);
      $ftname = str_replace('__', ':', (string)$featureType->Name);
      /*$ftNames = [
        'ADMINEXPRESS-COG-CARTO-PE.2025:region', // polygones en EPSG:4326
        'ADMINEXPRESS-COG-CARTO-PE.2025:chef_lieu_de_region', // points en EPSG:4326
        'patrinat_pn:parc_national', // polygones en EPSG:3857
      ];*/ // sélection de noms de FT 
      //if (!in_array($ftname, $ftNames)) continue;
      $collections[$ftname] = [
        'title'=> str_replace('__', ':', (string)$featureType->Title),
        'description'=> 'Abstract: '.(string)$featureType->Abstract
          ."\nDefaultCRS: ".(string)$featureType->DefaultCRS
          ."\nWGS84BoundingBox:"
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;LowerCorner:".$featureType->ows__WGS84BoundingBox->ows__LowerCorner
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;UpperCorner:".$featureType->ows__WGS84BoundingBox->ows__UpperCorner,
        //'defaultCRS'=> str_replace('__',':', $featureType->DefaultCRS),
        'bbox'=> self::WGS84BoundingBoxTo4Coordinates($featureType->ows__WGS84BoundingBox),
        'type'=> 'object',
        'patternProperties'=> [''=> ['type'=> 'object']],
      ];
      //if (count($collections) > 15) break; // limitation du nbre de FeatureType pour le développement
    }
    ksort($collections);
    return [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> (string)$this->elt->ows__ServiceIdentification->ows__Title,
      'description'=> (string)$this->elt->ows__ServiceIdentification->ows__Abstract,
      'type'=> 'object',
      'required'=> array_keys($collections),
      'additionalProperties'=> false,
      'properties'=> $collections,
    ];
  }

  /** Retourne la liste des paramètres outputFormat possibles pour GetFeature.
   * Fonctionne en WFS version 2.0.0 ou 1.1.0
   * @return list<WfsOutputFormat> */
  function outputFormatsForGetFeature(): array {
    $outputFormats = [];
    //echo "version=",$this->version(),"<br>\n";
    switch ($version = $this->version()) {
      case '2.0.0': {
        foreach ($this->elt->ows__OperationsMetadata->ows__Operation as $op) {
          if ($op['name'] == 'GetFeature') {
            //echo "opération $op[name]:\n";
            //print_r($op);
            foreach ($op->ows__Parameter as $param) {
              if ($param['name'] == 'outputFormat') {
                //echo "  paramètre $param[name]:\n";
                foreach ($param->ows__AllowedValues->ows__Value as $value) {
                  $outputFormats[] = new WfsOutputFormat((string)$value);
                }
              }
            }
          }
        }
        return $outputFormats;
      }
      case '1.1.0': {
        //echo '<pre>';
        //echo 'elt='; print_r($this->elt);
        foreach ($this->elt->ows__OperationsMetadata->ows__Operation as $op) {
          if ($op['name'] == 'GetFeature') {
            //print_r($op);
            foreach ($op->ows__Parameter as $param) {
              if ($param['name'] == 'outputFormat') {
                foreach ($param->ows__Value as $outputFormat) {
                  //echo "outputFormat=$outputFormat<br>\n";
                  $outputFormats[] = new WfsOutputFormat((string)$outputFormat);
                }
              }
            }
          }
        }
        return $outputFormats;
      }
      default: throw new \Exception("WfsCap::outputFormatsForGetFeature() non implémenté pour version=$version");
    }
  }
};

/** Exploite le XML issu d'une requête describeFeatureType pour en déduire les propriétés de schema JSON. */
class WfsProperties {
  readonly \SimpleXMLElement $ftds;
  
  function __construct(readonly string $dsName, readonly string $namespace, string $xml) {
    //echo '<pre>',htmlentities($xml);
    if (1) { // @phpstan-ignore if.alwaysTrue 
      // Supprime les prefixes, fonctionne pour AdminExpress-COG-Carto-PE mais pas pour BDTopage2025WfsNs
      //$ftds = preg_replace('!<(/)?[^:]+:!', '<$1', $ftds);
      // Fonctionne pour AdminExpress-COG-Carto-PE et BDTopage2025WfsNs
      $xml = preg_replace('!<(/)?[a-zA-Z0-9]+:!', '<$1', $xml);
    }
    else {
      // Ne fonctionne plus pour aucun WfsNs
      // Remplace les ':' des préfixes des espaces de noms par '__' 
      $xml = preg_replace('!<(/)?(([^:]+):)!', '<$1$3__', $xml);
      // remise de 'http: qui n'est pas un prefixe d'espace de noms
      $xml = preg_replace('!http__!', 'http:', $xml);
      //echo "<pre>Contenu XML:\n",htmlentities($xml),"</pre>\n"; die();
    }
    
    /*if ($this->dsName == 'BDTopage2025Wfs')
      echo '<pre>',htmlentities($xml);*/
    $this->ftds = simplexml_load_string($xml);
  }
    
  /** Convertit le type d'un champ de GML en GeoJSON.
   * @return array<string,mixed>
   */
  private function fieldType(string $type): array {
    return match($type) {
      'xsd:string'=> ['type'=> ['string', 'null']],
      'xsd:boolean'=> ['type'=>'boolean'],
      'xsd:int'=> ['type'=> ['integer', 'null']],
      'long'=> ['type'=> ['integer', 'null']],
      'xsd:double'=> ['type'=> ['number', 'null']],
      'double'=> ['type'=> ['number', 'null']],
      'xsd:date'=> [
        'type'=> 'string',
        'pattern'=> '^\d{4}-\d{2}-\d{2}Z$',
      ],
      'xsd:dateTime'=> ['type'=> $type],
      'gml:MultiSurfacePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['MultiPolygon'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:SurfacePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['Polygon'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:CurvePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['LineString'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:MultiCurvePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['MultiLineString'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:PointPropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['Point'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:GeometryPropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['Polygon','MultiPolygon','LineString','MultiLineString','Point','MultiPoint'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      //default=>  throw new \Exception("type=$type"),
      default=> ['type'=> $type],
    };
  }
  
  /** Construit les propriétés de chaque champ de chaque collection du JdD sous la forme [{collName}=< [{fieldName} => ['type'=> ...]]].
   * Traitement spécifique pour les WFS 1.1.0 sans namespace car le type peut avoir parfois un namespace
   * @return array<string,array<string,mixed>>
   */
  function properties(): array {
    //echo '<pre>$ftds='; print_r($this->ftds);
    $eltNameFromTypes = [];
    foreach ($this->ftds->element as $element) {
      //echo '<pre>$element='; print_r($element);
      //echo $element['type'];
      if ($this->namespace)
        $eltNameFromTypes[substr((string)$element['type'], strlen($this->namespace)+1)] = (string)$element['name'];
      else {
        // Quand le name n'a pas de namespace, il arrive que le type en est un !!!
        if (preg_match('!^[^:]*:(.*)$!', $element['type'], $matches))
          $eltNameFromTypes[$matches[1]] = (string)$element['name'];
        else
          $eltNameFromTypes[(string)$element['type']] = (string)$element['name'];
      }
    }
    //echo '<pre>$eltNameFromTypes='; print_r($eltNameFromTypes);
    
    $props = [];
    foreach ($this->ftds->complexType as $ftd) {
      //echo '<pre>$ftd='; print_r($ftd);
      $typeName = (string)$ftd['name'];
      //echo "typeName=$typeName<br>\n";
      if (!($eltName = $eltNameFromTypes[$typeName] ?? null))
        throw new \Exception("$typeName absent de eltNameFromTypes");
      
      //echo '<pre>xx='; print_r($ftd->complexContent);
      foreach ($ftd->complexContent->extension->sequence->element as $fieldDescription) {
        //echo '<pre>$fieldDescription='; print_r($fieldDescription);
        $fieldName = (string)$fieldDescription['name'];
        if (in_array($fieldName, ['geometrie','the_geom ']))
          $fieldName = 'geometry';
        $fieldType = $this->fieldType((string)$fieldDescription['type']);
        $props[$eltName][$fieldName] = $fieldType;
      }
      //echo "<pre>props[$eltName]="; print_r($props[$eltName]);
    }
    //echo "<pre>properties()="; print_r($props);
    return $props;
  }
};

/** Exécute les requêtes GET au serveur.
 * A sa construction, la prop. version n'étant pas définie, seule le GetCapabilities est possible.
 * Une fois cette prop. renseignée, grâce au GetCapabilities, les autres requêtes sont possibles.
 */
class WfsGetRequest {
  /** La version WFS du serveur, initialement à null, peut être défini après le GetCapabilities qui ne l'utilise pas. */
  protected ?string $version=null;
  
  function __construct(readonly string $name, readonly string $url) {}
  
  /** Retourne le SimpleXMLElement correspondant aux capabilities.
   * Les préfixes des espaces de noms sont remplacés par {prefix}__
   */
  function getCapabilities(): WfsCap {
    $xml = WfsCache::get(
      $this->name.'/cap.xml',
      $this->url.'?service=WFS&request=GetCapabilities'
    );
    return new WfsCap($this->name, $xml);
  }
  
  /** Défini la version du protocole WFS du serveur, ce qui autorise les requêtes autres que GetCapabilities. */
  function setVersion(string $version): void { $this->version = $version; }
  
  /** Retourne le DescribeFeatureType des FeatureTypes de l'espace de noms conerti en WfsProperties.
   * @param list<string> $ftNames - les noms des FeatureTypes avec leur espace de noms
   */
  function describeFeatureType(string $namespace, array $ftNames): WfsProperties {
    //echo "Appel de WfsGetRequest::describeFeatureType@$this->name(namespace=$namespace)<br>\n";
    $xml = WfsCache::get(
      $this->name.'/ft/'.($namespace ? $namespace : 'nonamespace').'.xml',
      $this->url."?SERVICE=WFS&VERSION=".$this->version."&REQUEST=DescribeFeatureType&TYPENAMES=".implode(',', $ftNames)
    );
    return new WfsProperties($this->name, $namespace, $xml);
  }
  
  /** Retourne l'extrait de collection sous la forme d'une FeatureCollection GeoJSON décodée.
   * Testé sur WFS 2.0.0 et 1.1.0, avec ou sans GeoJSON
   * @return TGeoJsonFeatureCollection
   */
  function getFeatures(string $ftName, int $start, int $count, WfsOutputFormat $outputFormat, ?BBox $bbox): array {
    //echo "Appel de WfsGetRequest::getFeatures(ftName=$ftName, start=$start, count=$count, bbox=$bbox)<br>\n";
    // Expérimentalement, c'est la meilleure forme de SRS à utiliser !
    if ($bbox) {
      $bboxAs4Coords = match($this->version) {
        '2.0.0' => $bbox->as4CoordsLatLon(), // en WFS 2.0.0, les coordonnées doivent être fournies en LatLon
        '1.1.0' => $bbox->as4Coords(), // en WFS 1.1.0 expérimentalement, je constate qu'il faut utiliser LonLat (au moins sur SextantDCE)
        default => throw new \Exception("version $this->version non acceptée"),
      };
      // ajout du SRS utilisé pour le BBox
      $bboxAs4Coords[] = match($this->version) {
        '2.0.0' => 'urn:ogc:def:crs:EPSG::4326', // en WFS 2.0.0, 'EPSG:4326' ne fonctionne pas, par contre l'urn fonctionne
        '1.1.0' => 'EPSG:4326', // en WFS 1.1.0 expérimentalement, c'est l'inverse
        default => throw new \Exception("version $this->version non acceptée"),
      };
    }
    $cachePath = $bbox ? null : "$this->name/features/".str_replace(':','/', $ftName)."/$start-$count".$outputFormat->ext();
    $fcoll = WfsCache::get(
      $cachePath,
      $this->url
        ."?service=WFS&version=".$this->version."&request=GetFeature&"
        .match($this->version) {
          '2.0.0' => "typeNames=$ftName",
          '1.1.0' => "typeName=$ftName",
          default => throw new \Exception("version $this->version non acceptée"),
         }
        ."&srsName=EPSG:4326"
        .($bbox ? "&bbox=".implode(',',$bboxAs4Coords) : '')
        ."&outputFormat=".urlencode($outputFormat)
        ."&startIndex=$start&"
        .match($this->version) {
          '2.0.0' => "count=$count",
          '1.1.0' => "maxFeatures=$count",
          default => throw new \Exception("version $this->version non acceptée"),
        }
    );
    // Si GetFeature a retourné du GML, il est converti en GeoJSON 
    if ($outputFormat->type() == 'GML') {
      if ($cachePath && is_file(WfsCache::PATH.$cachePath)) {
        $fcoll = Ogr2ogr::convertGmlToGeoJson(WfsCache::PATH.$cachePath);
      }
      else {
        if (!is_dir(WfsCache::PATH."$this->name/ogr"))
          mkdir(WfsCache::PATH."$this->name/ogr");
        $path = WfsCache::PATH."$this->name/ogr/".md5($fcoll).".gml";
        file_put_contents($path, $fcoll);
        $fcoll = Ogr2ogr::convertGmlToGeoJson($path);
        unlink($path);
        //echo "unlink(",substr($path, 0, -3).'json',")<br>\n";
        unlink(substr($path, 0, -3).'json');
      }
    }
    return json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
  }
  
  /** Retourne le Feature ayant cet id ou null si aucun ne l'a.
   * @return ?TGeoJsonFeature
   */
  function getFeature(string $ftName, string $id, WfsOutputFormat $outputFormat): ?array {
    $ftNameCache = str_replace(':','/', $ftName);
    $cachePath = "$this->name/features/$ftNameCache/id-$id".$outputFormat->ext();
    $fcoll = WfsCache::get(
      $cachePath,
      $this->url
        ."?service=WFS&version=".$this->version."&request=GetFeature&typeNames=$ftName"
        .'&srsName=EPSG:4326'
        ."&outputFormat=".urlencode($outputFormat)
        ."&featureID=$id"
    );
    // Si GetFeature a retourné du GML, il est converti en GeoJSON 
    if ($outputFormat->type() == 'GML') {
      $fcoll = Ogr2ogr::convertGmlToGeoJson(WfsCache::PATH.$cachePath);
    }
    $fcoll = json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
    if (count($fcoll['features']) == 0) {
      //echo "Aucun résultat retourné<br>\n";
      return null;
    }
    else {
      return $fcoll['features'][0];
    }
  }
};

/** Wfs - Gabarit des Dataset WFS, chaque JdD correspond à un serveur WFS.
 * L'URL du serveur est défini en paramètre lors de la création du Wfs et est stocké dans le registre de Dataset.
 * Le schéma ne fournit pas les propriétés de chaque collection car certains ont beaucoup de collections et ce serait peu efficace ;
 * pour avoir ces propriéts utiliser WfsNs qui se limite à un espace de noms.
 * Les coordonnées sont toujours retournées en WGS84 LonLat.
 */
class Wfs extends Dataset {
  /** Nbre de features par page pour getItems(). */
  const COUNT = 20;
  /** Le gestionnaire de requêtes. */
  readonly WfsGetRequest $wfsReq;
  /** Les capacités du serveur WFS. */
  readonly WfsCap $cap;
  
  /** Initialisation.
   * @param array{'class'?:string,'url':string,'dsName':string} $params
   */
  function __construct(array $params) {
    $this->wfsReq = new WfsGetRequest($params['dsName'], $params['url']);
    $this->cap = $this->wfsReq->getCapabilities();
    $this->wfsReq->setVersion($this->cap->version());
    if (!in_array($version = $this->cap->version(), ['2.0.0','1.1.0'])) {
      throw new \Exception("Version WFS==$version, seules les versions 2.0.0 et 1.1.1 sont gérées");
    }
    parent::__construct($params['dsName'], $this->cap->jsonSchemaOfTheDs(), true);
  }
  
  /** Crée un Wfs à partir de son nom. Utile pour que PhpStan comprenne le type de l'objet retourné. */
  static function get(string $dsName): self {
    if (!($def = Dataset::definitionOfADataset($dsName)) || !isset($def['url']))
      throw new \Exception("Définition de $dsName ne correspond pas à un Wfs");
    return new self(['dsName'=> $dsName, 'url'=> $def['url']]);
  }
  
  /** Retourne les caractéristiques principales du serveurs pour des tests.
   * @return array<string,mixed> */
  function characteristics(): array {
    $namespaces = $this->cap->namespaces();
    if (!$namespaces)
      $espacesDeNoms = "Aucun espace";
    elseif ($namespaces == [''])
      $espacesDeNoms = "1 espace ''";
    elseif (count($namespaces) == 1)
      $espacesDeNoms = "1 espace ".$namespaces[0];
    else
      $espacesDeNoms = count($namespaces)." espaces";
    return [
      'version'=> $this->cap->version(),
      'GeoJSON'=> (WfsOutputFormat::bestForGetFeature($this->cap)->type() == 'GeoJSON') ? 'oui' : 'non',
      'espacesDeNoms'=> $espacesDeNoms,
    ];
  }
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string> */
  function implementedFilters(string $collName): array { return ['skip', 'bbox']; }
  
  /** L'accès aux items d'une collection du JdD par un Generator.
   * Pour chaque Feature, le serveur WFS retourne l'id qui est utilisé comme clé de l'item ;
   * si l'id du feature n'est pas défini alors
   *   si aucun filtre autre que skip est utilisé alors l'identifiant est le no en séquence
   *   sinon la valeur null est retournée comme identifiant.
   * Une pagination est utilisée définie par COUNT pour requêter les features.
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - bbox: BBox - rectangle de sélection des n-uplets
   * Lorsqu'un filtre bbox est défini, il est passé au serveur WFS dans la requête.
   * @param string $collName - nom de la collection
   * @param array{'skip'?:int,'bbox'?:BBox} $filters - filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string|null,array<mixed>>
   */
  function getItems(string $collName, array $filters=[]): \Generator {
    //echo "Appel de Wfs::getItems(collName=$collName, filters)<br>\n";
    $start = $filters['skip'] ?? 0;
    if (!($outputFormat = WfsOutputFormat::bestForGetFeature($this->cap))) {
      throw new \Exception("Le serveur ne permet de fournir ni GeoJSON, ni GML");
    }
    
    while (true) {
      $fcoll = $this->wfsReq->getFeatures($collName, $start, self::COUNT, $outputFormat, $filters['bbox'] ?? null);
      //echo "<pre>fcoll="; print_r($fcoll); echo "</pre>\n";
      if (count($fcoll['features']) == 0) {
        //echo "Aucun résultat retourné<br>\n";
        return;
      }
      //echo "<pre>fcoll="; print_r($fcoll); echo "</pre>\n";
      foreach ($fcoll['features'] as $feature) {
        if (!($id = $feature['id'] ?? null)) { // si id n'est pas défini
          if ((array_keys($filters) == ['skip']) || (array_keys($filters) == [])) { // si pas d'autre filtre que skip
            $id = $start; // alors id est le no en sequence
          }
        }
        $geometry = $feature['geometry'];
        if (isset($feature['bbox'])) {
          $geometry['bbox'] = $feature['bbox'];
        }
        $tuple = array_merge(
          $feature['properties'],
          ['geometry'=> $geometry]
        );
        //echo "Wfs::getItems(collName=$collName, filters) yield $id<br>\n";
        yield $id => $tuple;
        $start++;
      }
      // Si getFeatures() a retourné moins de COUNT features alors c'est qu'il n'y en a plus
      // Ce test fonctionne quand le serveur a retourné du GML et que numberMatched n'est pas défini
      if (count($fcoll['features']) < self::COUNT)
        return;
      unset($fcoll['features']);
      //echo '<pre>$fcoll='; print_r($fcoll); echo "</pre>\n";
      if ($numberMatched = $fcoll['numberMatched'] ?? null) {
        if ($start >= $numberMatched)
          return;
      }
    }
  }
  
  /** Retourne l'item ayant l'id fourni.
   * @return array<mixed>|null
   */ 
  function getOneItemByKey(string $collName, string|int $id): array|null {
    //echo "getOneItemByKey(id=$id)<br>\n";
    if (is_int($id) || is_numeric($id)) { // c'est un numéro en séquence
      $start = intval(floor($id/self::COUNT)*self::COUNT); // j'utilise getItems() pour avoir la bonne page
      echo "start=$start<br>\n";
      foreach ($this->getItems($collName, ['skip'=> $start]) as $id2 => $feature) {
        if ($id2 == $id)
          return $feature;
      }
      return null;
    }
    else { // c'est un vrai id alors GetFeature par id
      if (!($outputFormat = WfsOutputFormat::bestForGetFeature($this->cap))) {
        throw new \Exception("Le serveur ne permet de fournir ni GeoJSON, ni GML");
      }
      $feature = $this->wfsReq->getFeature($collName, $id, $outputFormat);
      if (!$feature)
        return null;

      //echo "<pre>fcoll="; print_r($fcoll); echo "</pre>\n";
      $geometry = $feature['geometry'];
      if (isset($feature['bbox'])) {
        $geometry['bbox'] = $feature['bbox'];
      }
      $tuple = array_merge(
        $feature['properties'],
        ['geometry'=> $geometry]
      );
      return $tuple;
    }
  }

  /** Retourne le DescribeFeatureType des FeatureTypes de l'espace de noms converti en WfsProperties. */
  function describeFeatureTypes(string $namespace): WfsProperties {
    $ftNames = [];
    foreach (array_keys($this->collections) as $ftName) {
      if (substr($ftName, 0, strlen($namespace)+1) == "$namespace:")
        $ftNames[] = $ftName;
    }
    return $this->wfsReq->describeFeatureType($namespace, $ftNames);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


require_once __DIR__.'/wfsns.php';

use Algebra\CollectionOfDs;
use Symfony\Component\Yaml\Yaml;

/** Test de Wfs.
 * Dans la mesure du possible, peut s'exécuter aussi pour un dataset qui est un WfsNs.
 */
class WfsBuild {
  static function main(): void {
    if (isset($_GET['dataset']))
      echo "<title>$_GET[dataset]</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: { // Menu 
        echo "<a href='?dataset=$_GET[dataset]&action=nameSpaces'>Accès aux données par les espaces de noms</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=cap'>Affiche les capacités WFS de $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=version'>Affiche la version du WFS de $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=outputFormatsForGetFeature'>",
          "Affiche les formats possibles de sortie de GetFeature du WFS de $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=colls'>Affiche les collections de l'objet $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=getItemsOnBbox'>Test getItems sur bbox</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=listCRS'>Liste les CRS</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=delCache'>Efface le cache de $_GET[dataset]</a><br>\n";
        echo "<a href='?action=listOutputFormats'>Liste les outputFormats des différents serveurs pour GetFeature</a><br>\n";
        echo "<a href='?action=characteristics'>Liste les caractéristiques des différents serveurs</a><br>\n";
        echo "<a href='?action=testGetItemsOnBBoxOnDifferentServers'>Teste getItems sur bbox sur différents serveurs</a><br>\n";
        break;
      }
      case 'nameSpaces': { // Accède aux données par les espaces de noms, fonctionne sur Wfs|WfsNs
        $def = Dataset::dictOfDatasets()[$_GET['dataset']];
        $wfs = ($def['class'] == 'Wfs') ? Wfs::get($_GET['dataset']) : WfsNs::get($_GET['dataset'])->wfs;
        if (!isset($_GET['ns'])) { // affiche les espaces de noms de $_GET['dataset']
          echo "<h2>Liste des espaces de noms</h2>\n";
          if (!($namespaces = $wfs->cap->namespaces())) {
            echo "Il n'y a aucun espace de noms<br>\n";
            die();
          }
          if (in_array('', $namespaces)) {
            echo "Attention, certains FeatureTypes n'ont pas d'espace de noms<br>\n";
          }
          echo "<table border=1>\n",
               implode("\n", array_map(
                 function($ns) {
                   return "<tr><td><a href='?dataset=$_GET[dataset]&action=$_GET[action]&ns=$ns'>".($ns ? $ns : "noNS")."</a></td>"
                         ."<td><a href='?dataset=$_GET[dataset]&action=describeFeatureTypes&ns=$ns'>describeFeatureTypes</a></td>"
                         ."<td><a href='?dataset=$_GET[dataset]&action=properties&ns=$ns'>properties</a></td>"
                           ."<td><a href='?dataset=$_GET[dataset]&action=WfsNs&ns=$ns'>WfsNs</a></td>"
                         ."</tr>\n";
                 },
                 $namespaces
               )),
               "</table>\n";
          die();
        }
        else { // affiche les collections de l'espace de nom $_GET[ns]
          echo "<h2>Liste des collections ",$_GET['ns'] ? "de l'espace $_GET[ns]" : 'sans espace de noms',"</h2>\n";
          foreach (array_keys($wfs->cap->featureTypes()) as $collName) {
            if (preg_match('!^([^:]+):(.*)$!', $collName, $matches)) {
              if ($matches[1] == $_GET['ns']) {
                echo "<a href='?action=display&collection=$_GET[dataset].$collName'>$matches[2]</a><br>\n";
              }
            }
            else { // le nom ne comporte pas d'espace de noms
              if (!$_GET['ns']) {
                echo "<a href='?action=display&collection=$_GET[dataset].$collName'>$collName</a><br>\n";
              }
            }
          }
        }
        break;
      }
      case 'display': {
        if (isset($_GET['key'])) {
          CollectionOfDs::get($_GET['collection'])->displayItem($_GET['key']);
        }
        else {
          CollectionOfDs::get($_GET['collection'])->display(isset($_GET['skip']) ? ['skip'=> $_GET['skip']] : []);
        }
        break;
      }
      case 'cap': { // Affiche les capacités WFS de $_GET[dataset]
        $wfs = Wfs::get($_GET['dataset']);
        echo '<pre>cap='; print_r($wfs->cap->elt); echo "</pre>\n";
        break;
      }
      case 'version': {
        $wfs = Wfs::get($_GET['dataset']);
        echo "version=",$wfs->cap->version(),"<br>\n";
        break;
      }
      case 'outputFormatsForGetFeature': { // "Affiche les formats possibles de sortie de GetFeature du WFS de $_GET[dataset] 
        $wfs = Wfs::get($_GET['dataset']);
        echo '<pre>outputFormatsForGetFeature='; print_r($wfs->cap->outputFormatsForGetFeature());
        echo "bestOutputFormatForGetFeature=",WfsOutputFormat::bestForGetFeature($wfs->cap) ?? 'null',"\n";
        break;
      }
      case 'colls': { // Affiche les collections de l'objet $_GET[dataset]
        $wfs = Dataset::get($_GET['dataset']);
        //echo '<pre>$fs='; print_r($fs); echo "</pre>\n";
        echo "<h2>FeatureTypes</h2>\n";
        foreach ($wfs->collections as $collName => $coll) {
          echo "<a href='?action=getItems&dataset=$_GET[dataset]&ft=$collName'>$coll->title</a><br>\n";
        }
        break;
      }
      case 'describeFeatureTypes': { // affiche le retour de describeFeatureTypes() pour un espace de noms 
        $wfs = Wfs::get($_GET['dataset']);
        echo'<pre>describeFeatureTypes='; print_r($wfs->describeFeatureTypes($_GET['ns']));
        break;
      }
      case 'properties': { // affiche les propriétés des FeatureType d'un espace de noms du Wfs 
        $wfs = Wfs::get($_GET['dataset']);
        $wfsProperties = $wfs->describeFeatureTypes($_GET['ns']);
        echo "<pre>properties:\n"; echo Yaml::dump($wfsProperties->properties());
        break;
      }
      case 'WfsNs': { // Crée un WfsNs à partir d'un Wfs et d'un espace de noms 
        require_once __DIR__.'/wfsns.php';
        $wfs = Wfs::get($_GET['dataset']);
        $wfsNs = new WfsNs(['dsName'=> $_GET['dataset'], 'url'=> $wfs->wfsReq->url, 'namespace'=> $_GET['ns']]);
        echo "<pre>wfsNs:\n"; print_r($wfsNs);
        break;
      }
      case 'getItems': { // affiche les items d'un FeatureType du Wfs 
        $wfs = Wfs::get($_GET['dataset']);
        foreach ($wfs->getItems($_GET['ft']) as $item) {
          echo '<pre>$item='; print_r($item); echo "</pre>\n";
        }
        break;
      }
      case 'getItemsOnBbox': { // getItems avec BBox 
        echo "<h2>getItemsOnBbox</h2>\n";
        $BBOXES = [
          'LaRéunion'=> [55, -21, 56, -20],
          "PACA"=> [4.23, 42.98, 7.72, 45.13],
          "Bretagne"=> [-5.14, 47.28, -1.02, 48.87],
        ];
        if (!isset($_GET['bbox'])) {
          echo "<h3>Choix d'un bbox</h3>\n";
          foreach ($BBOXES as $title => $bbox) {
            echo "<a href='?dataset=$_GET[dataset]&action=$_GET[action]&bbox=$title'>$title</a><br>\n";
          }
          die();
        }
        $def = Dataset::dictOfDatasets()[$_GET['dataset']];
        $wfs = ($def['class'] == 'Wfs') ? Dataset::get($_GET['dataset']) : WfsNs::get($_GET['dataset'])->wfs;
        if (!isset($_GET['collName'])) {
          echo "<h3>Choix d'une collection</h3>\n";
          foreach ($wfs->collections as $collName => $coll) {
            echo "sur <a href='?dataset=$_GET[dataset]&action=$_GET[action]&bbox=$_GET[bbox]&collName=$collName'>$collName</a><br>\n";
          }
          die();
        }
        else {
          $bbox = BBox::from4Coords($BBOXES[$_GET['bbox']]);
          $nbItems = 0;
          foreach ($wfs->getItems($_GET['collName'], ['bbox'=> $bbox]) as $item) {
            $item['geometry']['coordinates'] = [];
            echo '<pre>$item='; print_r($item); echo "</pre>\n";
            if (++$nbItems >= 3) {
              echo "Arrêt après 3 items.<br>\n";
              break;
            }
          }
        }
        break;
      }
      case 'listCRS': {
        $wfs = Wfs::get($_GET['dataset']);
        $crs = [];
        foreach ($wfs->cap->elt->FeatureTypeList->FeatureType as $featureType) {
          //echo '<pre>$featureType='; print_r($featureType);
          $defaultCRS = (string)$featureType->DefaultCRS;
          $ftName = (string)$featureType->Name;
          //echo "ftName=$ftName -> defaultCRS=$defaultCRS<br>\n";
          $crs[$defaultCRS][] = $ftName;
        }
        echo '<pre>$defaultCRS='; print_r($crs);
        break;
      }
      case 'delCache': {
        WfsCache::delete($_GET['dataset']);
        break;
      }
      case 'listOutputFormats': {
        echo "<pre>\n";
        $outputFormats = [];
        foreach (Dataset::dictOfDatasets() as $dsName => $dsDef) {
          if (is_array($dsDef) && in_array($dsDef['class'], ['Wfs','WfsNs']) && isset($dsDef['url'])) {
            echo "dataset: $dsName\n";
            if ($dsDef['class'] == 'Wfs')
              $wfs = Wfs::get($dsName);
            else
              $wfs = WfsNs::get($dsName)->wfs;
            foreach ($wfs->cap->outputFormatsForGetFeature() as $outputFormat) {
              $outputFormats[(string)$outputFormat] = 1;
            }
          }
        }
        ksort($outputFormats);
        print_r($outputFormats);
        break;
      }
      case 'characteristics': {
        echo "<h2>Caractéristiques des serveurs WFS</h2><pre>\n";
        foreach (Dataset::dictOfDatasets() as $dsName => $dsDef) {
          if (is_array($dsDef) && in_array($dsDef['class'], ['Wfs','WfsNs']) && isset($dsDef['url'])) {
            //echo "dataset: $dsName\n";
            if ($dsDef['class'] == 'Wfs')
              $wfs = Wfs::get($dsName);
            else
              $wfs = WfsNs::get($dsName)->wfs;
            //print_r($wfs->characteristics());
            echo Yaml::dump([$dsName => array_merge(['class'=> $dsDef['class']], $wfs->characteristics())]);
          }
        }
        break;
      }
      case 'testGetItemsOnBBoxOnDifferentServers': { // Teste getItems sur bbox pour différents serveurs
        echo "<h1>Teste getItems sur bbox pour différents serveurs</h1>\n";
        $BBOXES = [
          'LaRéunion'=> [55, -21, 56, -20],
          "PACA"=> [4.23, 42.98, 7.72, 45.13],
          "Bretagne"=> [-5.14, 47.28, -1.02, 48.87],
          "Belley"=> [5.66, 45.71, 5.73, 45.78],
          "MtStMichel"=> [-1.5337, 48.6164, -1.4904, 48.6436],
        ];
        $jeuxDeTest = [
          //*
          'AE-COG-Carto-ME.departement X PACA'=> [
            'collection'=> 'AdminExpress-COG-Carto-ME.departement',
            'bbox'=> 'PACA',
            'expected'=> 12,
          ],
          'ShomGeoTiff30-300 X PACA'=> [
            'collection'=> 'ShomWfs.CARTES_MARINES_GRILLE:grille_geotiff_30_300',
            'bbox'=> 'PACA',
            'expected'=> 11,
          ], //*/
          'ShomGeoTiff30-300 X LaRéunion'=> [
            'collection'=> 'ShomWfs.CARTES_MARINES_GRILLE:grille_geotiff_30_300',
            'bbox'=> 'LaRéunion',
            'expected'=> 3,
          ],
          //*
          'BDTopage2025.CoursEau X Belley'=> [
            'collection'=> 'BDTopage2025.CoursEau_FXX_Topage2025',
            'bbox'=> 'Belley',
            'expected'=> 9,
          ],
          'EtatChimiqueMdO X Bretagne'=> [
            'collection'=> 'SextantDCE.ATLAS_DCE_LOIRE_BRETAGNE_ME_ETAT_CHIMIQUE_2010_P',
            'bbox'=> 'Bretagne',
            'expected'=> 55,
          ],
          'GéoLittoral.n_carte_vocations_s X MtStMichel'=> [
            'collection'=> 'GéoLittoral.n_carte_vocations_s',
            'bbox'=> 'MtStMichel',
            'expected'=> 1,
          ],//*/
        ];
        foreach ($jeuxDeTest as $title => $jeu) {
          echo "<h2>$title</h2>\n";
          $coll = CollectionOfDs::get($jeu['collection']);
          $bbox = BBox::from4Coords($BBOXES[$jeu['bbox']]);
          $nbItems = 0;
          foreach ($coll->getItems(['bbox'=> $bbox]) as $item) {
            $item['geometry']['coordinates'] = [];
            //echo '$item='; print_r($item); echo "<br>\n";
            $nbItems++;
          }
          echo "nbItems=$nbItems / ",$jeu['expected'] ?? '??',"<br>\n"; // @phpstan-ignore nullCoalesce.offset
          preg_match('!^([^.]+)!', $jeu['collection'], $matches);
          $dsName = $matches[1];
          //echo "dataset=$dsName<br>\n";
          $dsDef = Dataset::dictOfDatasets()[$dsName];
          if ($dsDef['class'] == 'Wfs')
            $wfs = Wfs::get($dsName);
          else
            $wfs = WfsNs::get($dsName)->wfs;
          echo '<pre>',Yaml::dump([$dsName => array_merge(['class'=> $dsDef['class']], $wfs->characteristics())]),"</pre>\n";
          if (isset($jeu['expected']) && ($nbItems <> $jeu['expected']))  // @phpstan-ignore isset.offset
            throw new \Exception("Erreur pour $title");
        }
        echo "<b>Tous ok<br>\n";
        break;
      }
      default: throw new \Exception("Action $_GET[action] inconnue");
    }
  }
};
WfsBuild::main();
