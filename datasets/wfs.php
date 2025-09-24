<?php
/** Wfs - Catégorie des Dataset WFS.
 *
 * @package Dataset
 */
namespace Dataset;

/** Actions à réaliser. */
const A_FAIRE_WFS = [
<<<'EOT'
Actions à réaliser:
  - pb de synchro entre la pagination et le début de la plage demandée
    - si dans getItems() start ne correspond pas à un début de page, les caches sont multipliés
  - dans les properties, la geometry ne porte pas geometry comme nom !
  - la classe fonctionne t'elle avec des services WFS version 1.1 ?
    - adapter WfsCap::outputFormatsForGetFeature() pour qu'elle fonctionne en WFS 1.1, ex SextantDCE
  - certains WFS ne sont pas capables de fournir du GeoJSON -> prévoir une conversion de GML en GeoJSON ?
  - éviter les requêtes pour un feature sur identifiant et réutiliser le résultat des requêtes paginées
    - -> pas évident
EOT
];

const NOTE_ESPACES_DE_NOMS_WFS = [
<<<'EOT'
L'utilisation d'espaces de noms XML avec SimpleXML est compliquée car son affichage ne permet pas de visualiser les sous-éléments
dans les différents espaces.
J'utilise donc un contournement un peu approximatif.
Dans le XML, soit je remplace les ':' séparant le préfixe par '__', soit je supprime ces préfixes.
Cette 2ème solution ne fonctionne pas quand il existe des attributs utilisant un espace de nom comm 'xlink:href'.

Cette transformation et ce décodage XML sont cantonnés dans les classes:
  - WfsCap qui analyse les capacités fournies en XML
  - WfsProperties qui analyse le XML issu de DescribeFeatureType et qui est utilisé par WfsNs.

Je pourrais utiliser des XPath avec SimpleXml, ce qui serait plus fiable.

EOT
];

require_once __DIR__.'/dataset.inc.php';
require_once __DIR__.'/../geom/geojson.inc.php';

use GeoJSON\Feed;
use GeoJSON\Geometry;
use BBox\GBox as BBox;

class ConvertGmlToGeoJson {
  /** Convertit un fichier GML d'une FeatureCollection en une FeatureCollection GeoJSON décodée.
   * @return TGeoJsonFeatureCollection
   */
  static function convert(string $srcPath): array {
    echo "ConvertGmlToGeoJson::convert(path=$srcPath)<br>\n";
    
    $destPath = substr($srcPath, 0, -4).'.json';
    if (!is_file($destPath)) {
      $options = "-lco WRITE_BBOX=YES"
                ." -lco COORDINATE_PRECISION=5"; // résolution 1m
      $cmde = "ogr2ogr -f 'GeoJSON' $options $destPath $srcPath";
      echo "$cmde<br>\n";
      $ret = exec($cmde, $output, $result_code);
      if ($result_code <> 0) {
        echo '$ret='; var_dump($ret);
        echo "result_code=$result_code<br>\n";
        echo '<pre>$output'; print_r($output); echo "</pre>\n";
      }
    }
    $json = file_get_contents($destPath);
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
  }
};

/** Gère un cache des appels Http GET pour Wfs.
 * Les fichiers sont stockés dans WfsCache::PATH.
 */
class WfsCache {
  const PATH = __DIR__.'/wfscache/';
  
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
   * @param string $filePath - chemin du fichier du cache relatif à PATH
   * @param string $url - URL du flux à lire
   */
  static function get(string $filePath, string $url): string {
    $filePath = self::PATH.$filePath;
    if (is_file($filePath)) {
      return file_get_contents($filePath);
    }
    else {
      $string = file_get_contents($url);
      if ($string === false)
        throw new \Exception("Ouverture $url impossible");
      if ($string) {
        self::createDir($filePath);
        file_put_contents($filePath, $string);
      }
      return $string;
    }
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

/** Créé à partir du XML des capacités d'un serveur WFS et les exploite pour en déduire notamment le schéma JSON du JdD. */
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
  private static function corner2Pos(\SimpleXMLElement $corner): array {
    if (preg_match('!^([-\d\.E]+) ([-\d\.E]+)$!', $corner, $matches))
      return [floatval($matches[1]), floatval($matches[2])];
    elseif (!$corner)
      return [];
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
        'title'=> (string)str_replace('__', ':', (string)$featureType->Title),
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

/** Retourne le paramètre outputFormat à utiliser dans GetFeature pour obtenir du GeoJSON.
 * Retourne null si GetFeature ne permet pas d'obtenir du GeoJSON.
 * Les différents serveurs WFS n'utilisent pas le même paramètre outputFormat:
 *  - IgnWfs -> application/json
 *  - BDTopage2025Wfs -> application/json; subtype=geojson
 *  - DCE -> application/vnd.geo+json
 */
class WfsOutputFormat {
  function __construct(readonly string $fmt) {}
  
  function __toString(): string { return $this->fmt; }
  
  /** @return 'GeoJSON'|'GML'|null */
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

  /** Choisi le meilleur format pour GetFeature. */
  static function bestForGetFeature(WfsCap $wfsCap): ?self {
    $best = [];
    foreach ($wfsCap->outputFormatsForGetFeature() as $of) {
      if ($type = $of->type())
        $best[$type] = $of;
    }
    if (isset($best['GeoJSON']))
      return $best['GeoJSON'];
    elseif (isset($best['GML']))
      return $best['GML'];
    else
      return null;
  }
};

/** Créé à partir du XML issu d'une requête describeFeatureType et les exploite pour en déduire les propriétés de schema JSON. */
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
   * @return array<string,array<string,mixed>>
   */
  function properties(): array {
    //echo '<pre>$ftds='; print_r($this->ftds);
    $eltNameFromTypes = [];
    foreach ($this->ftds->element as $element) {
      //echo '<pre>$element='; print_r($element);
      //echo $element['type'];
      $eltNameFromTypes[substr((string)$element['type'], strlen($this->namespace)+1)] = (string)$element['name'];
    }
    //echo '<pre>$eltNameFromTypes='; print_r($eltNameFromTypes);
    
    $props = [];
    foreach ($this->ftds->complexType as $ftd) {
      //echo '<pre>$ftd='; print_r($ftd);
      $typeName = (string)$ftd['name'];
      $eltName = $eltNameFromTypes[$typeName];
      
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

/** Exécute les requêtes GET au serveur. */
class WfsGetRequest {
  function __construct(readonly string $name, readonly string $url) {}
  
  /** Retourne le SimpleXMLElement correspondant aux capabilities.
   * Les préfixes des espaces de noms sont remplacés par {prefix}__
   */
  function getCapabilities(): WfsCap {
    $xml = WfsCache::get(
      $this->name.'/cap.xml',
      $this->url.'?service=WFS&version=2.0.0&request=GetCapabilities'
    );
    return new WfsCap($this->name, $xml);
  }
  
  /** Retourne le DescribeFeatureType des FeatureTypes de l'espace de noms conerti en WfsProperties.
   * @param list<string> $ftNames - les noms des FeatureTypes avec leur espace de nom
   */
  function describeFeatureType(string $namespace, array $ftNames): WfsProperties {
    $xml = WfsCache::get(
      $this->name.'/ft/'.$namespace.'.xml',
      $this->url."?SERVICE=WFS&VERSION=2.0.0&REQUEST=DescribeFeatureType&TYPENAMES=".implode(',', $ftNames)
    );
    return new WfsProperties($this->name, $namespace, $xml);
  }
  
  /** Retourne l'extrait de collection sous la forme d'une FeatureCollection GeoJSON décodée.
   * @return TGeoJsonFeatureCollection
   */
  function getFeatures(string $ftName, int $start, int $count, WfsOutputFormat $outputFormat, ?BBox $bbox): array {
    //echo "Appel de WfsGetRequest::getFeatures(ftName=$ftName, start=$start, count=$count, bbox=$bbox)<br>\n";
    if ($bbox) {
      // En WFS on précise le CRS du BBox qui doit être fourni en LatLon
      $bboxLatLon = $bbox->as4CoordsLatLon();
      $bboxLatLon[] = 'urn:ogc:def:crs:EPSG::4326';
      //echo "bboxLatLon=[".implode(',',$bboxLatLon)."]<br>\n";
      Feed::log("bboxLatLon=[".implode(',',$bboxLatLon)."]\n");
    }
    $outputFormatType = $outputFormat->type();
    $ext = match($outputFormatType) {
      'GeoJSON' => '.json',
      'GML' => '.gml',
      default => throw new \Exception("Cas interdit"),
    };
    $ftNameCache = str_replace(':','/', $ftName);
    $cachePath = "$this->name/features/$ftNameCache".($bbox?"/bbox$bbox":'/nobbox')."/$start-$count$ext";
    $fcoll = WfsCache::get(
      $cachePath,
      $this->url
        ."?service=WFS&version=2.0.0&request=GetFeature&typeNames=$ftName"
        .'&srsName=urn:ogc:def:crs:EPSG::4326'
        .($bbox ? "&bbox=".implode(',',$bboxLatLon) : '')
        ."&outputFormat=".urlencode($outputFormat)
        ."&startIndex=$start&count=$count"
    );
    return match($outputFormatType) {
      'GeoJSON' => json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR),
      'GML' => ConvertGmlToGeoJson::convert(WfsCache::PATH.$cachePath),
      default => throw new \Exception("Cas interdit"),
    };
  }
  
  /** Retourne le Feature ayant cet id ou null si aucun ne l'a.
   * @return ?TGeoJsonFeature
   */
  function getFeature(string $ftName, string $id, string $outputFormat): ?array {
    $ftNameCache = str_replace(':','/', $ftName);
    $fcoll = WfsCache::get(
      "$this->name/features/$ftNameCache/id-$id.json",
      $this->url
        ."?service=WFS&version=2.0.0&request=GetFeature&typeNames=$ftName"
        .'&srsName=urn:ogc:def:crs:EPSG::4326'
        ."&outputFormat=".urlencode($outputFormat)
        ."&featureID=$id"
    );
    $fcoll = json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
    if ($fcoll['numberMatched'] == 0) {
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
    /*if (($version = $this->cap->version()) <> '2.0.0') {
      throw new \Exception("Version WFS==$version, seule la version 2.0.0 est gérée");
    }*/
    parent::__construct($params['dsName'], $this->cap->jsonSchemaOfTheDs(), true);
  }
  
  /** Crée un Wfs à partir de son nom. Utile pour que PhpStan comprenne le type de l'objet retourné. */
  static function get(string $dsName): self {
    if (!($def = Dataset::definitionOfADataset($dsName)))
      throw new \Exception("Définition de $dsName ne correspond pas à un Wfs");
    return new self(['dsName'=> $dsName, 'url'=> $def['url']]);
  }
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
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
    echo "getOneItemByKey(id=$id)<br>\n";
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


use Algebra\CollectionOfDs;

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
        break;
      }
      case 'nameSpaces': { // Accède aux données par les espaces de noms
        $wfs = Dataset::get($_GET['dataset']);
        if (!isset($_GET['ns'])) { // affiche les espaces de noms de $_GET['dataset']
          echo "<h2>Liste des espaces de noms</h2>\n";
          $namespaces = [];
          foreach (array_keys($wfs->collections) as $collName) {
            if (!preg_match('!^([^:]+):!', $collName, $matches)) {
              throw new \Exception("No match on '$collName'");
            }
            $namespaces[$matches[1]] = 1;
          }
          echo "<table border=1>\n",
               implode("\n", array_map(
                 function($namespace) {
                   return "<tr><td><a href='?dataset=$_GET[dataset]&action=$_GET[action]&ns=$namespace'>$namespace</a></td>"
                         ."<td><a href='?dataset=$_GET[dataset]&action=describeFeatureTypes&ns=$namespace'>describeFeatureTypes</a></td>"
                         ."</tr>\n";
                 },
                 array_keys($namespaces)
               )),
               "</table>\n";
        }
        else { // affiche les collections de l'espace de nom $_GET[ns]
          echo "<h2>Liste des collections de l'espace $_GET[ns]</h2>\n";
          foreach (array_keys($wfs->collections) as $collName) {
            if (!preg_match('!^([^:]+):(.*)$!', $collName, $matches)) {
              throw new \Exception("No match on '$collName'");
            }
            if ($matches[1] == $_GET['ns']) {
              echo "<a href='?action=display&collection=$_GET[dataset].$collName'>$matches[2]</a><br>\n";
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
      case 'describeFeatureTypes': {
        $wfs = Wfs::get($_GET['dataset']);
        echo'<pre>describeFeatureTypes='; print_r($wfs->describeFeatureTypes($_GET['ns']));
        break;
      }
      case 'getItems': {
        $wfs = Wfs::get($_GET['dataset']);
        foreach ($wfs->getItems($_GET['ft']) as $item) {
          echo '<pre>$item='; print_r($item); echo "</pre>\n";
        }
        break;
      }
      case 'getItemsOnBbox': {
        echo "<h2>getItemsOnBbox</h2>\n";
        $wfs = Wfs::get($_GET['dataset']);
        if (!isset($_GET['collName'])) {
          foreach ($wfs->collections as $collName => $coll) {
            echo "sur <a href='?dataset=$_GET[dataset]&action=$_GET[action]&collName=$collName'>$collName</a><br>\n";
          }
        }
        else {
          $bbox = BBox::from4Coords([55, -21, 56, -20]);
          foreach ($wfs->getItems($_GET['collName'], ['bbox'=> $bbox]) as $item) {
            $item['geometry']['coordinates'] = [];
            echo '<pre>$item='; print_r($item); echo "</pre>\n";
          }
        }
        break;
      }
      case 'listCRS': {
        {/* CRS utilisés dans wfs-fr-ign-gpf
            [urn:ogc:def:crs:EPSG::4326]
            [urn:ogc:def:crs:EPSG::2154]
            [urn:ogc:def:crs:EPSG::3857]
            [urn:ogc:def:crs:EPSG::4471] => Array(
                    [0] => IGNF_CARTO-FORMATIONS-VEGETALES_2016:formations_vegetales_d976_2016
                    [1] => IGNF_CARTO-FORMATIONS-VEGETALES_2023:formations_vegetales_d976_2023
                    [2] => batiment_gpkg_19-12-2024_wfs:batiment_mayotte
            )
            [urn:ogc:def:crs:EPSG::32620] => Array(
                    [0] => PRSF_BDD_GLP_2023:prs_glp
            )
            [urn:ogc:def:crs:EPSG::2972] => Array(
                    [0] => PRSF_BDD_GUF_2023:prs_guf
                    [1] => ste_fr_carte_d973_gpkg_07-10-2024_wfs:geom_ste_guyane
            )
            [urn:ogc:def:crs:EPSG::2975] => Array(
                    [0] => PRSF_BDD_REU_2023:prs_reu
                    [1] => ste_fr_carte_d974_gpkg_07-10-2024_wfs:geom_ste_reunion
            )
            [urn:ogc:def:crs:EPSG::5490] => Array(
                    [0] => communes_972:commune972
                    [1] => ste_fr_carte_d971_972_gpkg_07-10-2024_wfs:geom_ste_guadeloupe
                    [2] => ste_fr_carte_d971_972_gpkg_07-10-2024_wfs:geom_ste_martinique
                    [3] => unesco_pelee_wfs:unesco_bien
                    [4] => zonealertesecheresse972_gpkg_27-09-2024_wfs:972_Alerte_Secheresse
            )
            [urn:ogc:def:crs:EPSG::3944] => Array(
                    [0] => points_altimetriques_mamp_v1_wfs:points_altimetriques
                    [1] => points_altimetriques_mamp_v2_echantillons:points_altimetriques
            )
        */}
        $fs = new Wfs($_GET['dataset']);
        $crs = [];
        foreach ($fs->collections as $ftName => $ft) {
          //echo "ftName=$ftName<br>\n";
          //echo '<pre>'; print_r($ft);
          $defaultCRS = $ft->schema->schema['defaultCRS'];
          //echo "ftName=$ftName -> defaultCRS=$defaultCRS<br>\n";
          $crs[$defaultCRS][] = $ftName;
        }
        echo '<pre>'; print_r($crs);
        break;
      }
      case 'delCache': {
        WfsCache::delete($_GET['dataset']);
        break;
      }
      default: throw new \Exception("Action $_GET[action] inconnue");
    }
  }
};
WfsBuild::main();
