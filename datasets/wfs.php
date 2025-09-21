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
- voir les WFS
  - Atlas Sandre - https://www.sandre.eaufrance.fr/atlas/srv/fre/catalog.search#/home
  - Sextant - https://sextant.ifremer.fr/Services/Inspire/Services-WFS
  - GéoLittoral - https://geolittoral.din.developpement-durable.gouv.fr/wxs
  
EOT
];

require_once __DIR__.'/dataset.inc.php';
require_once __DIR__.'/../geom/geojson.inc.php';

use GeoJSON\Geometry;
use BBox\GBox as BBox;

/** Gère un cache des appels Http pour Wfs.
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
      self::createDir($filePath);
      file_put_contents($filePath, $string);
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

/** Gère les Capabilities d'un serveur WFS.
 * Construit notamment le schéma du JdD.
 */
class WfsCap {
  function __construct(readonly \SimpleXMLElement $elt) {}
  
  /** Convertit un coin d'un WGS84BoundingBox en Position.
   * @return TPos */
  static function corner2Pos(\SimpleXMLElement $corner): array {
    if (!preg_match('!^([-\d\.E]+) ([-\d\.E]+)$!', $corner, $matches)) {
      throw new \Exception("No match sur '$corner'");
    }
    return [floatval($matches[1]), floatval($matches[2])];
  }
  
  /** Convertit un WGS84BoundingBox en 4 coordonnées xmin,ymin,xmax,ymax.
   * Attention, les WGS84BoundingBox sont souvent trop grands pour tenir dans un BBox.
   * @return array<int,number>
   */
  static function WGS84BoundingBoxTo4Coordinates(\SimpleXMLElement $WGS84BoundingBox): array {
    $lc = self::corner2Pos($WGS84BoundingBox->ows__LowerCorner);
    $uc = self::corner2Pos($WGS84BoundingBox->ows__UpperCorner);
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
        'patternProperties'=> [
          ''=> [
            'type'=> 'object',
          ],
        ],
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
};

/** Gère les requêtes GET au serveur. */
class WfsGetRequest {
  function __construct(readonly string $name, readonly string $url) {}
  
  /** Retourne le SimpleXMLElement correspondant aux capabilities */
  function getCapabilities(): \SimpleXMLElement {
    $cap = WfsCache::get(
      $this->name.'/cap.xml',
      $this->url.'?service=WFS&version=2.0.0&request=GetCapabilities'
    );
    // Remplace les ':' des espaces de noms par '__' 
    $cap = preg_replace('!<(/)?(([^:]+):)?!', '<$1$3__', $cap);
    //header('Content-Type: application/xml'); die($cap);
    $elt = simplexml_load_string($cap);
    //echo '<pre>$elt='; print_r($elt);
    return $elt;
  }
  
  /** Retourne le DescribeFeatureType des FeatureTypes de l'espace de noms conerti en SimpleXMLElement.
   * @param list<string> $ftNames - les noms des FeatureTypes avec leur espace de nom
   */
  function describeFeatureType(string $namespace, array $ftNames): \SimpleXMLElement {
    $ftds = WfsCache::get(
      $this->name.'/ft/'.$namespace.'.xml',
      $this->url."?SERVICE=WFS&VERSION=2.0.0&REQUEST=DescribeFeatureType&TYPENAMES=".implode(',', $ftNames)
    );
    //echo '<pre>',htmlentities($ftds);
    $ftds = preg_replace('!<(/)?[^:]+:!', '<$1', $ftds);
    $ftds = simplexml_load_string($ftds);
    return $ftds;
  }
  
  /** Retourne l'extrait de collection sous la forme d'une FeatureCollection GeoJSON décodée.
   * @return TGeoJsonFeatureCollection
   */
  function getFeatures(string $ftName, int $start, int $count, ?BBox $bbox): array {
    //echo "Appel de WfsGetRequest::getFeatures(ftName=$ftName, start=$start, count=$count, bbox=$bbox)<br>\n";
    if ($bbox) {
      // En WFS on précise le CRS du BBox qui doit être fourni en LatLon
      $bboxLatLon = $bbox->as4CoordsLatLon();
      $qbboxLatLon[] = 'urn:ogc:def:crs:EPSG::4326';
      //echo "bboxLatLon=[".implode(',',$bboxLatLon)."]<br>\n";
    }
    $ftNameCache = str_replace(':','/', $ftName);
    $fcoll = WfsCache::get(
      "$this->name/features/$ftNameCache".($bbox?"/bbox$bbox":'')."/$start-$count.json",
      $this->url
        ."?service=WFS&version=2.0.0&request=GetFeature&typeNames=$ftName"
        .'&srsName=urn:ogc:def:crs:EPSG::4326'
        .($bbox ? "&bbox=".implode(',',$bboxLatLon) : '')
        ."&outputFormat=".urlencode('application/json')
        ."&startIndex=$start&count=$count"
    );
    return json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
  }
  
  /** Retourne le Feature ayant cet id ou null si aucun ne l'a.
   * @return ?TGeoJsonFeature
   */
  function getFeature(string $ftName, string $id): ?array {
    $fcoll = WfsCache::get(
      "$this->name/features/$ftName/id-$id.json",
      $this->url
        ."?service=WFS&version=2.0.0&request=GetFeature&typeNames=$ftName"
        .'&srsName=urn:ogc:def:crs:EPSG::4326'
        ."&outputFormat=".urlencode('application/json')
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

/** Wfs - Catégorie des Dataset WFS, chaque JdD correspond à un serveur WFS.
 * L'URL du serveur est défini en paramètre lors de la création du Wfs et est stocké dans le REGISTRE de Dataset.
 * Les coordonnées sont toujours retournées en WGS84 LonLat.
 * Lorsqu'un filtre bbox est défini, ce bbox est passé au serveur WFS dans la requête.
 * Pour chaque Feature, le serveur WFS retourne un id qui est utilisé comme clé de l'item.
 * Utilisation d'une pagination pour requêter les features définie par COUNT.
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
    $this->cap = new WfsCap($this->wfsReq->getCapabilities());
    parent::__construct($params['dsName'], $this->cap->jsonSchemaOfTheDs(), true);
  }
  
  /** Crée un Wfs à partir de son nom. Utile pour que PhpStan comprenne le type de l'objet retourné. */
  static function get(string $dsName): self {
    $url = Dataset::REGISTRE[$dsName]['url'];
    return new self(['dsName'=> $dsName, 'url'=> $url]);
  }
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
  function implementedFilters(string $collName): array { return ['skip', 'bbox']; }
  
  /** L'accès aux items d'une collection du JdD par un Generator. A REVOIR pour descendre le bbox dans la geometry !!!
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - bbox: BBox - rectangle de sélection des n-uplets
   * @param string $collName - nom de la collection
   * @param array{'skip'?:int,'bbox'?:BBox} $filters - filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $collName, array $filters=[]): \Generator {
    //echo "Appel de Wfs::getItems(collName=$collName, filters)<br>\n";
    $start = $filters['skip'] ?? 0;
    while (true) {
      $fcoll = $this->wfsReq->getFeatures($collName, $start, self::COUNT, $filters['bbox'] ?? null);
      if ($fcoll['numberMatched'] == 0) {
        //echo "Aucun résultat retourné<br>\n";
        return;
      }
      //echo "<pre>fcoll="; print_r($fcoll); echo "</pre>\n";
      foreach ($fcoll['features'] as $feature) {
        $id = $feature['id'];
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
      $numberMatched = $fcoll['numberMatched'];
      if ($start >= $numberMatched)
        return;
    }
  }
  
  /** Retourne l'item ayant l'id fourni.
   * @return array<mixed>|null
   */ 
  function getOneItemByKey(string $collName, string|int $id): array|null {
    $feature = $this->wfsReq->getFeature($collName, $id);
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

  /** Retourne le DescribeFeatureType des FeatureTypes de l'espace de noms conerti en SimpleXMLElement. */
  function describeFeatureTypes(string $namespace): \SimpleXMLElement {
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
        $fs = Wfs::get($_GET['dataset']);
        echo '<pre>cap='; print_r($fs->cap->elt); echo "</pre>\n";
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
