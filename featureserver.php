<?php
/** FeatureServer - Catégorie des Dataset WFS.
 * Chaque JdD correspond à une serveur WFS.
 */

require_once 'dataset.inc.php';

/** Permet de gérer un cache de certains appels */
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
  
  /** @return array<mixed> */
  function jsonSchemaOfTheDs(): array {
    $properties = [];
    //echo '$elt='; print_r($this->elt);
    //echo 'FeatureTypeList='; print_r($this->elt->FeatureTypeList->FeatureType);
    foreach ($this->elt->FeatureTypeList->FeatureType as $featureType) {
      //print_r($featureType);
      $name = str_replace('__', ':', (string)$featureType->Name);
      $properties[$name] = [
        'title'=> (string)$featureType->Title,
        'description'=> 'Abstract: '.(string)$featureType->Abstract
          ."\nDefaultCRS: ".(string)$featureType->DefaultCRS
          ."\nWGS84BoundingBox:"
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;LowerCorner:".$featureType->ows__WGS84BoundingBox->ows__LowerCorner
          ."\n&nbsp;&nbsp;&nbsp;&nbsp;UpperCorner:".$featureType->ows__WGS84BoundingBox->ows__UpperCorner,
        'type'=> 'array',
      ];
    }
    return [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schema du serveur WFS",
      'description'=> "Schema du serveur WFS",
      'type'=> 'object',
      'properties'=> $properties,
    ];
  }
  
  function describeFeatureType(string $fsname, string $ftname): string {
    return Cache::get(
      "featureserver/ft/$fsname-$ftname",
      FeatureServer::REGISTRE[$fsname]['url']
        ."?SERVICE=WFS&VERSION=2.0.0&REQUEST=DescribeFeatureType&TYPENAMES=$ftname"
        ."&OUTPUTFORMAT=".urlencode('application/gml+xml; version=3.2')
    );
  }
};

class FeatureServer extends Dataset {
  const REGISTRE = [
    'wfs-fr-ign-gpf' => [
      'title'=> "Service WFS de la Géoplateforme",
      'description'=> "Service WFS de la Géoplateforme",
      'url'=> 'https://data.geopf.fr/wfs/ows',
      'type'=> 'WFS',
      'version'=> '2.0.0',
    ],
  ];
  readonly string $name;
  readonly WfsCap $cap;
  
  function __construct(string $name) {
    $this->name = $name;
    $this->cap = new WfsCap($name);
    //echo '<pre>'; print_r($this);
    parent::__construct(self::REGISTRE[$name]['title'], self::REGISTRE[$name]['description'], $this->cap->jsonSchemaOfTheDs());
  }
  
  /** L'accès aux tuples d'une section du JdD par un Generator.
   * @param string $section nom de la section
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return Generator
   */
  function getTuples(string $section, array $filters=[]): Generator {
    $start = $filters['skip'] ?? 0;
    while (true) {
      $url = self::REGISTRE[$this->name]['url']
          ."?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature&TYPENAMES=$section"
          ."&outputFormat=".urlencode('application/json')
          ."&startIndex=$start&count=1";
      $fcoll = Cache::get(
        "featureserver/features/$this->name-$section-$start.json",
        $url
      );
      if ($fcoll == false)
        throw new Exception("Erreur sur $url");
      $fcoll = json_decode($fcoll, true, 512, JSON_THROW_ON_ERROR);
      $tuple = array_merge(
        $fcoll['features'][0]['properties'],
        ['bbox'=> $fcoll['features'][0]['bbox']],
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
    foreach ($fs->getTuples($_GET['ft']) as $tuple) {
      echo '<pre>$tuple='; print_r($tuple); echo "</pre>\n";
    }
    break;
  }
}
