<?php
/** FeatureServer - Catégorie des Dataset WFS.
 *
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/dataset.inc.php';
require_once __DIR__.'/../geojson.inc.php';

use GeoJSON\Geometry;
use BBox\BBox;

/** Gère un cache des appels Http pour FeatureServer.
 * Les fichiers sont stockés dans cache::PATH.
 */
class Cache {
  const PATH = __DIR__.'/featureserver/';
  
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
      echo "Création de $dirPath<br>\n";
      mkdir($dirPath);
    }
  }
  
  /** Lecture d'un flux.
   * @param string $filePath - chemin du fichier du cache relatif à __DIR__."/featureserver/"
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
  /** Capacités dans lesquelles les espaces de noms sont supprimés. */
  readonly \SimpleXMLElement $elt;
  
  /** Retourne le string correspondant aux capabilities */
  static function getCapabilities(string $name): string {
    if (!($registre = FeatureServer::REGISTRE[$name] ?? null))
      throw new \Exception("Nom '$name' inconnu dans FeatureServer::REGISTRE");
    return Cache::get(
      "$name/cap.xml",
      $registre['url'].'?service=WFS&version=2.0.0&request=GetCapabilities'
    );
  }
  
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
  
  /** Lit les capacités du serveur et les stoke dans l'objet. */
  function __construct(string $name) {
    $cap = self::getCapabilities($name);
    $cap = preg_replace('!<(/)?(([^:]+):)?!', '<$1$3__', $cap);
    //header('Content-Type: application/xml'); die($cap);
    $this->elt = simplexml_load_string($cap);
    //echo '<pre>$elt='; print_r($this->elt);
  }
  
  /** Construit le schema JSON à partir des capacités.
   * @return array<mixed> */
  function jsonSchemaOfTheDs(string $fsName): array {
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
      ];
      //if (count($collections) > 15) break; // limitation du nbre de FeatureType pour le développement
    }
    ksort($collections);
    return [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> FeatureServer::REGISTRE[$fsName]['title'],
      'description'=> FeatureServer::REGISTRE[$fsName]['description'],
      'type'=> 'object',
      'required'=> array_keys($collections),
      'additionalProperties'=> false,
      'properties'=> $collections,
    ];
  }
  
  /** Utilisé en test. Apporte peu. */
  function describeFeatureType(string $fsname, string $ftname): string {
    return Cache::get(
      "$fsname/ft/$ftname.xml",
      FeatureServer::REGISTRE[$fsname]['url']
        ."?SERVICE=WFS&VERSION=2.0.0&REQUEST=DescribeFeatureType&TYPENAMES=$ftname"
        ."&OUTPUTFORMAT=".urlencode('application/gml+xml; version=3.2')
    );
  }
};

/** FeatureServer - Catégorie des Dataset WFS, chaque JdD correspond à un serveur WFS.
 * Le mapping nom -> url est fait dans REGISTRE.
 * Les coordonnées sont toujours retournées en WGS84 LonLat.
 * Lorsqu'un filtre bbox est défini, ce bbox est passé au serveur WFS dans la requête.
 * Pour chaque Feature, le serveur WFS retourne un id qui est utilisé comme clé de l'item.
 * Utilisation d'une pagination pour requêter les features définie par COUNT.
 */
class FeatureServer extends Dataset {
  /** Registre des serveurs WFS indexé par le nom du JdD. */
  const REGISTRE = [
    'IgnWfs' => [
      'title'=> "Service WFS de la Géoplateforme IGN",
      'description'=> "Service WFS de la Géoplateforme IGN",
      'url'=> 'https://data.geopf.fr/wfs/ows',
      'type'=> 'WFS',
      'version'=> '2.0.0',
    ],
    'ShomWfs' => [
      'title'=> "Service WFS du Shom",
      'description'=> "Service WFS du Shom",
      'url'=> 'https://services.data.shom.fr/INSPIRE/wfs',
      'type'=> 'WFS',
      'version'=> '2.0.0',
    ],
  ];
  /** Nbre de features par page. */
  const COUNT = 20;
  /** Les capacités du serveur WFS. */
  readonly WfsCap $cap;
  
  function __construct(string $name) {
    $this->cap = new WfsCap($name);
    //echo '<pre>'; print_r($this);
    //$registre = self::REGISTRE[$name];
    //echo '<pre>jsonSchemaOfTheDs='; print_r($this->cap->jsonSchemaOfTheDs($name)); echo "</pre>\n";
    parent::__construct($name, $this->cap->jsonSchemaOfTheDs($name), true);
  }
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
  function implementedFilters(string $collName): array { return ['skip', 'bbox']; }
  
  /** L'accès aux items d'une collection du JdD par un Generator. A REVOIR pour descendre le bbox dans la geometry !!!
   * @param string $cName nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - bbox: BBox - rectangle de sélection des n-uplets
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $cName, array $filters=[]): \Generator {
    //echo "cName=$cName<br>\n";
    $start = $filters['skip'] ?? 0;
    $qbboxLatLon = null;
    if ($qbbox = $filters['bbox'] ?? null) {
      // En WFS on précise le CRS du BBox qui doit être fourni en LatLon
      $qbboxLatLon = $qbbox->as4CoordsLatLon();
      $qbboxLatLon[] = 'urn:ogc:def:crs:EPSG::4326';
      //echo "bboxLatLon=[".implode(',',$bboxLatLon)."]<br>\n";
    }
    while (true) {
      $url = self::REGISTRE[$this->name]['url']
          ."?service=WFS&version=2.0.0&request=GetFeature&typeNames=$cName"
          .'&srsName=urn:ogc:def:crs:EPSG::4326'
          .($qbboxLatLon ? "&bbox=".implode(',',$qbboxLatLon) : '')
          ."&outputFormat=".urlencode('application/json')
          ."&startIndex=$start&count=".self::COUNT;
      //echo "url=$url<br>\n";
      $fcoll = Cache::get(
        "$this->name/features/$cName".($qbbox?'-'.$qbbox:'')."/$start-".self::COUNT.".json",
        $url
      );
      $fcoll = json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
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
  function getOneItemByKey(string $cName, string|int $id): array|null {
    $url = self::REGISTRE[$this->name]['url']
        ."?service=WFS&version=2.0.0&request=GetFeature&typeNames=$cName"
        .'&srsName=urn:ogc:def:crs:EPSG::4326'
        ."&outputFormat=".urlencode('application/json')
        ."&featureID=$id";
    $fcoll = Cache::get(
      "$this->name/features/$cName/id-$id.json",
      $url
    );
    $fcoll = json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
    if ($fcoll['numberMatched'] == 0) {
      //echo "Aucun résultat retourné<br>\n";
      return null;
    }
    //echo "<pre>fcoll="; print_r($fcoll); echo "</pre>\n";
    $geometry = $fcoll['features'][0]['geometry'];
    if (isset($fcoll['features'][0]['bbox'])) {
      $geometry['bbox'] = $fcoll['features'][0]['bbox'];
    }
    $tuple = array_merge(
      $fcoll['features'][0]['properties'],
      ['geometry'=> $geometry]
    );
    return $tuple;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


use Algebra\CollectionOfDs;

class FeatureServerBuild {
  static function main(): void {
    if (isset($_GET['dataset']))
      echo "<title>$_GET[dataset]</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: { // Menu 
        echo "<a href='?dataset=$_GET[dataset]&action=namedSpaces'>Accès aux données par les espaces de noms</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=cap'>Affiche les capacités WFS de $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=colls'>Affiche les collections de l'objet $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=getItemsOnBbox'>Test getItems sur bbox</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=listCRS'>Liste les CRS</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=delCache'>Effacement du cache de $_GET[dataset]</a><br>\n";
        break;
      }
      case 'namedSpaces': { // Accède aux données par les espaces de noms
        $fs = Dataset::get($_GET['dataset']);
        if (isset($_GET['ns'])) { // affiche les collections de l'espace de nom $_GET[ns]
          echo "<h2>Liste des collections de l'espace $_GET[ns]</h2>\n";
          foreach ($fs->schema['properties'] as $pname => $prop) {
            if ($pname == '$schema') continue;
            //echo "pname=$pname<br>\n";
            if (!preg_match('!^([^:]+):(.*)$!', $pname, $matches)) {
              throw new \Exception("No match on '$pname'");
            }
            if ($matches[1] == $_GET['ns']) {
              echo "<a href='?action=display&collection=$_GET[dataset].$pname'>$matches[2]<br>\n";
            }
          }
        }
        else { // affiche les espaces de noms de $_GET['dataset']
          echo "<h2>Liste des noms d'espaces</h2>\n";
          $names = [];
          foreach ($fs->schema['properties'] as $pname => $prop) {
            if ($pname == '$schema') continue;
            //echo "pname=$pname<br>\n";
            if (!preg_match('!^([^:]+):!', $pname, $matches)) {
              throw new \Exception("No match on '$pname'");
            }
            $names[$matches[1]] = 1;
          }
          echo "<table border=1>\n",
               implode(array_map(
                 function($name) {
                   return "<tr><td><a href='?dataset=$_GET[dataset]&action=$_GET[action]&ns=$name'>$name</a></td></tr>\n";
                 },
                 array_keys($names)
               )),
               "</table>\n";
        }
        break;
      }
      case 'display': {
        if (isset($_GET['key'])) {
          CollectionOfDs::get($_GET['collection'])->displayItem($_GET['key']);
        }
        else {
          CollectionOfDs::get($_GET['collection'])->display($_GET['skip'] ?? 0);
        }
        break;
      }
      case 'cap': { // Affiche les capacités WFS de $_GET[dataset]
        $fs = new FeatureServer($_GET['dataset']);
        echo '<pre>cap='; print_r($fs->cap->elt); echo "</pre>\n";
        break;
      }
      case 'colls': { // Affiche les collections de l'objet $_GET[dataset]
        $fs = Dataset::get($_GET['dataset']);
        //echo '<pre>$fs='; print_r($fs); echo "</pre>\n";
        echo "<h2>FeatureTypes</h2>\n";
        foreach ($fs->schema['properties'] as $pname => $prop) {
          if ($pname == '$schema') continue;
          //echo '<pre>'; print_r([$pname => $prop]);
          echo "$prop[title] <a href='?action=featureType&dataset=$_GET[dataset]&ft=$pname'>featureType</a>, ";
          echo "<a href='?action=getItems&dataset=$_GET[dataset]&ft=$pname'>getTuples</a><br>\n";
        }
        break;
      }
      case 'featureType': {
        $fs = new FeatureServer($_GET['dataset']);
        echo '<pre>'; print_r([$_GET['ft'] => $fs->schema['properties'][$_GET['ft']]]); echo "</pre>\n";
        $describeFt = $fs->cap->describeFeatureType($fs->name, $_GET['ft']);
        echo '<pre>$describeFt=',str_replace('<','&lt;', $describeFt),"</pre>\n";
        break;
      }
      case 'getItems': {
        $fs = new FeatureServer($_GET['dataset']);
        foreach ($fs->getItems($_GET['ft']) as $item) {
          echo '<pre>$item='; print_r($item); echo "</pre>\n";
        }
        break;
      }
      case 'getItemsOnBbox': {
        echo "<h2>getItemsOnBbox</h2>\n";
        $fs = new FeatureServer($_GET['dataset']);
        if (!isset($_GET['ft'])) {
          foreach ($fs->schema['properties'] as $ftName => $prop) {
            if ($ftName == '$schema') continue;
            //echo '<pre>'; print_r([$pname => $prop]);
            echo "sur <a href='?dataset=$_GET[dataset]&action=$_GET[action]&ft=$ftName'>$ftName</a><br>\n";
          }
        }
        else {
          $bbox = BBox::from4Coords([55, -21, 56, -20]);
          foreach ($fs->getItems($_GET['ft'], ['bbox'=> $bbox]) as $item) {
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
        $fs = new FeatureServer($_GET['dataset']);
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
        Cache::delete($_GET['dataset']);
        break;
      }
      default: throw new \Exception("Action $_GET[action] inconnue");
    }
  }
};
FeatureServerBuild::main();
