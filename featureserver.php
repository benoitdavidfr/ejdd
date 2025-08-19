<?php
/** FeatureServer - Catégorie des Dataset WFS.
 * Chaque JdD correspond à un serveur WFS.
 * Attention les coordonnées peuvent ne pas être en CRS:84.
 *
 * @package Dataset
 */

require_once 'dataset.inc.php';

/** Gère un cache de certains appels Http. */
class Cache {
  static function get(string $filePath, string $url): string {
    if (is_file($filePath)) {
      return file_get_contents($filePath);
    }
    else {
      $string = file_get_contents($url);
      if ($string === false)
        throw new Exception("Ouverture $url impossible");
      file_put_contents($filePath, $string);
      return $string;
    }
  }
};

/** Manipulation des Capabilities d'un serveur WFS */
class WfsCap {
  /** Capacités dans lesquelles les espaces de noms sont supprimés. */
  readonly SimpleXMLElement $elt;
  
  /** Retourne le string correspondant aux capabilities */
  static function getCapabilities(string $name): string {
    return Cache::get(
      "featureserver/cap/$name.xml",
      FeatureServer::REGISTRE[$name]['url'].'?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetCapabilities'
    );
  }

  function __construct(string $name) {
    $cap = self::getCapabilities($name);
    $cap = preg_replace('!<(/)?(([^:]+):)?!', '<$1$3__', $cap);
    //header('Content-Type: application/xml'); die($cap);
    $this->elt = simplexml_load_string($cap);
    //echo '<pre>$elt='; print_r($this->elt);
  }
  
  /** Déduction du schema à partir des capacités.
   * @return array<mixed> */
  function jsonSchemaOfTheDs(): array {
    $sections = [];
    //echo '$elt='; print_r($this->elt);
    //echo 'FeatureTypeList='; print_r($this->elt->FeatureTypeList->FeatureType);
    foreach ($this->elt->FeatureTypeList->FeatureType as $featureType) {
      //print_r($featureType);
      $name = str_replace('__', ':', (string)$featureType->Name);
      $sections[$name] = [
        'title'=> (string)$featureType->Title,
        'description'=> 'Abstract: '.(string)$featureType->Abstract
          ."\nDefaultCRS: ".(string)$featureType->DefaultCRS
          ."\nWGS84BoundingBox:"
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;LowerCorner:".$featureType->ows__WGS84BoundingBox->ows__LowerCorner
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;UpperCorner:".$featureType->ows__WGS84BoundingBox->ows__UpperCorner,
        'type'=> 'array',
      ];
    }
    ksort($sections);
    return [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schema du serveur WFS",
      'description'=> "Schema du serveur WFS",
      'type'=> 'object',
      'properties'=> $sections,
    ];
  }
  
  /** Utilisé en test. Ammène peu. */
  function describeFeatureType(string $fsname, string $ftname): string {
    return Cache::get(
      "featureserver/ft/$fsname-$ftname",
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
    $registre = self::REGISTRE[$name];
    parent::__construct($name, $registre['title'], $registre['description'], $this->cap->jsonSchemaOfTheDs());
  }
  
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** L'accès aux tuples d'une section du JdD par un Generator.
   * @param string $cName nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return Generator
   */
  function getItems(string $cName, array $filters=[]): Generator {
    $start = $filters['skip'] ?? 0;
    while (true) {
      $url = self::REGISTRE[$this->name]['url']
          ."?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=$cName"
          ."&outputFormat=".urlencode('application/json')
          ."&startIndex=$start&count=1";
      $fcoll = Cache::get(
        "featureserver/features/$this->name-$cName-$start.json",
        $url
      );
      if ($fcoll == false)
        throw new Exception("Erreur sur $url");
      $fcoll = json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
      $tuple = array_merge(
        $fcoll['features'][0]['properties'],
        isset($fcoll['features'][0]['bbox']) ? ['bbox'=> $fcoll['features'][0]['bbox']] : [],
        ['geometry'=> $fcoll['features'][0]['geometry']]
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


switch ($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=cap&dataset=$_GET[dataset]'>Affiche les capacités WFS de $_GET[dataset]</a><br>\n";
    echo "<a href='?action=create&dataset=$_GET[dataset]'>Création de l'objet $_GET[dataset]</a><br>\n";
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
      //echo '<pre>'; print_r([$pname => $prop]);
      echo "$prop[title] <a href='?action=featureType&dataset=$_GET[dataset]&ft=$pname'>featureType</a>, ";
      echo "<a href='?action=getTuples&dataset=$_GET[dataset]&ft=$pname'>getTuples</a><br>\n";
    }
    break;
  }
  case 'featureType': {
    $fs = new FeatureServer($_GET['dataset']);
    echo '<pre>'; print_r([$_GET['ft'] => $fs->schema['properties'][$_GET['ft']]]); echo "</pre>\n";
    $describeFt = $fs->cap->describeFeatureType($fs->name, $_GET['ft']);
    echo '<pre>$describeFt='; print_r($describeFt); echo "</pre>\n";
    break;
  }
  case 'getTuples': {
    $fs = new FeatureServer($_GET['dataset']);
    foreach ($fs->getItems($_GET['ft']) as $item) {
      echo '<pre>$item='; print_r($item); echo "</pre>\n";
    }
    break;
  }
}
