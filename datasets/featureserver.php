<?php
/** FeatureServer - Catégorie des Dataset WFS.
 * Chaque JdD correspond à un serveur WFS.
 * Effectue si nécessaire la conversion des coordonnées en WGS84 LonLat.
 * Lorsqu'un filtre bbox est défini, ce bbox est intégré dans la requête au serveur WFS.
 *
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/../dataset.inc.php';
require_once __DIR__.'/../geojson.inc.php';
require_once __DIR__.'/../lib/coordsys.inc.php';

use GeoJSON\Geometry;
use BBox\BBox;
use CoordSys\Lambert93;
use CoordSys\WebMercator;
use CoordSys\UTM;

/** Gère un cache d'appels Http. */
class Cache {
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
    $filePath = __DIR__."/featureserver/$filePath";
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
};

/** Effectue les chgt de syst. de coord. */
class Wgs84LonLat {
  /** Fabrique une fonction de conversion en WGS84 LonLat en fonction du code CRS défini comme URN OGC.
   * CRS utilisés dans wfs-fr-ign-gpf:
   *   - EPSG:4326
   *   - EPSG:2154 = Lambert93
   *   - EPSG:3857 = WebMercator
   *   - EPSG:4471 = RGM04 / UTM zone 38S (utilisé à Mayotte)
   *   - EPSG:32620 = WGS 84 / UTM zone 20N (utilisé dans les Antilles)
   *   - EPSG:2972 = RGFG95 / UTM zone 22N (utilisé en Guyane)
   *   - EPSG:2975 = RGR92 / UTM zone 40S (utilisé à La Réunion)
   *   - EPSG:5490 = RGAF09 / UTM zone 20N (utilisé dans les Antilles françaises)
   *   - EPSG:3944 = RGF93 v1 / CC44 (utilisé pour le cadastre)
   */
  static function reproj(string $crs): callable {
    if (!preg_match('!^urn:ogc:def:crs:EPSG::(\d+)$!', $crs, $matches)) {
      throw new \Exception("CRS '$crs' non interprété");
    }
    $epsg = $matches[1];
    return match ($epsg) {
      // Si EPSG:4326 est utilisé, cela signifie que les coordonnées sont en WGS84 LonLat
      '4326'=> function ($pos) { return $pos; },
      // EPSG:2154 = Lambert93
      '2154'=> function ($pos) { return Lambert93::geo($pos); },
      // EPSG:3857 = WebMercator
      '3857'=> function ($pos) { return WebMercator::geo($pos); },
      // ESG:4471 = RGM04 / UTM zone 38S (utilisé à Mayotte)
      '4471'=> function ($pos) { return UTM::geo($pos, '38S'); },
      // ESG:32620 = WGS 84 / UTM zone 20N (utilisé dans les Antilles)
      '32620'=> function ($pos) { return UTM::geo($pos, '20N'); },
      // EPSG:2972 = RGFG95 / UTM zone 22N (utilisé en Guyane)
      '2972'=> function ($pos) { return UTM::geo($pos, '22N'); },
      // EPSG:2975 = RGR92 / UTM zone 40S (utilisé à La Réunion)
      '2975'=> function ($pos) { return UTM::geo($pos, '40S'); },
      // EPSG:5490 = RGAF09 / UTM zone 20N (utilisé dans les Antilles françaises)
      '5490'=> function ($pos) { return UTM::geo($pos, '20N'); },
      default=> throw new \Exception("EPSG:$epsg non traité"),
    };
    
  }
  
  /** Convertit une géométrie en $crs en WGS84 LonLat.
   * @param array<mixed> $geometry
   * @return array<mixed>
   */
  static function geom(string $crs, array $geometry): array {
    return Geometry::create($geometry)->reproject(self::reproj($crs))->asArray();
  }
  
  /** Convertit un bbox en $crs en WGS84 LonLat.
   * @param array<float> $bbox
   * @return array<float>
   */
  static function bbox(string $crs, array $bbox): array {
    $reproj = self::reproj($crs);
    $sw = $reproj([$bbox[0], $bbox[1]]);
    $ne = $reproj([$bbox[2], $bbox[3]]);
    return [$sw[0], $sw[1], $ne[0], $ne[1]];
  }
};

/** Gestion des Capabilities d'un serveur WFS.
 * Construit notamment le schéma du JdD.
 */
class WfsCap {
  /** Capacités dans lesquelles les espaces de noms sont supprimés. */
  readonly \SimpleXMLElement $elt;
  
  /** Retourne le string correspondant aux capabilities */
  static function getCapabilities(string $name): string {
    return Cache::get(
      "cap/$name.xml",
      FeatureServer::REGISTRE[$name]['url'].'?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetCapabilities'
    );
  }
  
  /** Convertit un coin d'un WGS84BoundingBox en Position.
   * @return TPos */
  static function corner2Pos(\SimpleXMLElement $corner): array {
    if (!preg_match('!^([-\d\.]+) ([-\d\.]+)$!', $corner, $matches)) {
      throw new \Exception("No match sur '$corner'");
    }
    return [floatval($matches[1]), floatval($matches[2])];
  }
  
  /** Convertit un WGS84BoundingBox en BBox. */
  static function WGS84BoundingBox2BBox(\SimpleXMLElement $WGS84BoundingBox): BBox {
    $bbox = new BBox(
      self::corner2Pos($WGS84BoundingBox->ows__LowerCorner),
      self::corner2Pos($WGS84BoundingBox->ows__UpperCorner)
    );
    //echo '<pre>WGS84BoundingBox2BBox() returns '; print_r($bbox); echo "</pre>\n";
    return $bbox;
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
    $requiredProps = ['$schema'];
    $collectionProps = [
      '$schema'=> ['description'=> "Le schéma du JdD", 'type'=> 'object'],
    ];
    //echo '$elt='; print_r($this->elt);
    //echo 'FeatureTypeList='; print_r($this->elt->FeatureTypeList->FeatureType);
    foreach ($this->elt->FeatureTypeList->FeatureType as $featureType) {
      //print_r($featureType);
      $ftname = str_replace('__', ':', (string)$featureType->Name);
      $ftNames = [
        'ADMINEXPRESS-COG-CARTO-PE.2025:region', // polygones en EPSG:4326
        'ADMINEXPRESS-COG-CARTO-PE.2025:chef_lieu_de_region', // points en EPSG:4326
        'patrinat_pn:parc_national', // polygones en EPSG:3857
      ]; // sélection de noms de FT 
      if (!in_array($ftname, $ftNames)) continue;
      $requiredProps[] = $ftname;
      $collectionProps[$ftname] = [
        'title'=> (string)$featureType->Title,
        'description'=> 'Abstract: '.(string)$featureType->Abstract
          ."\nDefaultCRS: ".(string)$featureType->DefaultCRS
          ."\nWGS84BoundingBox:"
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;LowerCorner:".$featureType->ows__WGS84BoundingBox->ows__LowerCorner
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;UpperCorner:".$featureType->ows__WGS84BoundingBox->ows__UpperCorner,
        'defaultCRS'=> str_replace('__',':', $featureType->DefaultCRS),
        'bbox'=> self::WGS84BoundingBox2BBox($featureType->ows__WGS84BoundingBox)->as4Coordinates(),
        'type'=> 'array',
      ];
      //if (count($requiredProps) > 15) break; // limitation du nbre de FeatureType pour le développement
    }
    sort($requiredProps);
    ksort($collectionProps);
    return [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> FeatureServer::REGISTRE[$fsName]['title'],
      'description'=> FeatureServer::REGISTRE[$fsName]['description'],
      'type'=> 'object',
      'required'=> $requiredProps,
      'additionalProperties'=> false,
      'properties'=> $collectionProps,
    ];
  }
  
  /** Utilisé en test. Apporte peu. */
  function describeFeatureType(string $fsname, string $ftname): string {
    return Cache::get(
      "ft/$fsname-$ftname",
      FeatureServer::REGISTRE[$fsname]['url']
        ."?SERVICE=WFS&VERSION=2.0.0&REQUEST=DescribeFeatureType&TYPENAMES=$ftname"
        ."&OUTPUTFORMAT=".urlencode('application/gml+xml; version=3.2')
    );
  }
};

/** FeatureServer - Catégorie des Dataset WFS. Chaque JdD correspond à une serveur WFS.
 * Le mapping nom -> url est fait dans REGISTRE.
 */
class FeatureServer extends Dataset {
  /** Registre des serveurs WFS indexé par le nom du JdD. */
  const REGISTRE = [
    'wfs-fr-ign-gpf' => [
      'title'=> "Service WFS de la Géoplateforme",
      'description'=> "Service WFS de la Géoplateforme",
      'url'=> 'https://data.geopf.fr/wfs/ows',
      'type'=> 'WFS',
      'version'=> '2.0.0',
    ],
  ];
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
    echo "cName=$cName<br>\n";
    $defaultCRS = $this->collections[$cName]->schema->schema['defaultCRS'];
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
          ."?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=$cName"
          .($qbboxLatLon ? "&bbox=".implode(',',$qbboxLatLon) : '')
          ."&outputFormat=".urlencode('application/json')
          ."&startIndex=$start&count=1";
      //echo "url=$url<br>\n";
      $fcoll = Cache::get(
        "features/$this->name/$cName".($qbbox?'-'.$qbbox:'')."/$start.json",
        $url
      );
      if ($fcoll == false)
        throw new \Exception("Erreur sur $url");
      $fcoll = json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
      if ($fcoll['numberMatched'] == 0) {
        //echo "Aucun résultat retourné<br>\n";
        return;
      }
      $geometry = Wgs84LonLat::geom($defaultCRS, $fcoll['features'][0]['geometry']);
      if (isset($fcoll['features'][0]['bbox'])) {
        $geometry['bbox'] = Wgs84LonLat::bbox($defaultCRS, $fcoll['features'][0]['bbox']);
      }
      $tuple = array_merge(
        $fcoll['features'][0]['properties'],
        ['geometry'=> $geometry]
      );
      yield $start => $tuple;
      $numberMatched = $fcoll['numberMatched'];
      if ($start >= $numberMatched-1)
        return;
      $start++;
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


class FeatureServerBuild {
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?dataset=$_GET[dataset]&action=cap'>Affiche les capacités WFS de $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=create'>Création de l'objet $_GET[dataset]</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=getItemsOnBbox'>Test getItems sur bbox</a><br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=listCRS'>Liste les CRS</a><br>\n";
        break;
      }
      case 'cap': {
        $fs = new FeatureServer($_GET['dataset']);
        echo '<pre>cap='; print_r($fs->cap->elt); echo "</pre>\n";
        break;
      }
      case 'create': {
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
      default: throw new \Exception("Action $_GET[action] inconnue");
    }
  }
};
FeatureServerBuild::main();
