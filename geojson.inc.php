<?php
namespace geojson;
/** Bibliothèque d'utilisation du GeoJSON
 * @package GeoJSON
 */
//ini_set('memory_limit', '10G');

require_once('bbox.php');

/** Les grandeurs kilo, Méga, Giga, ... */
class U {
  const K = 1024;
  const M = 1024 * self::K;
  const G = 1024 * self::M;
};

/** Fonctions sur les positions définies comme une liste de 2 nombres. */
class Pos {
  /** distance entre 2 positions.
   * @param TPos $pos1
   * @param TPos $pos2
   */
  static function dist(array $pos1, array $pos2): float {
    return abs($pos2[0]-$pos1[0]) + abs($pos2[1]-$pos1[1]);
  }
};

/** Classe abstraite de géométrie GeoJSON portant la méthode create() de création d'une géométrie. */
abstract class Geometry {
  readonly string $type;
  /** @var TPos|TLPos|TLLPos|TLLLPos $coordinates */
  readonly array $coordinates;
  
  /** Crée un objet géométrie.
   * @param TGJSimpleGeometry $geom */
  static function create(array $geom): self {
    switch ($type = $geom['type'] ?? null) {
      case 'Point': return new Point($geom);
      case 'MultiPoint': return new MultiPoint($geom);
      case 'LineString': return new LineString($geom);
      case 'MultiLineString': return new MultiLineString($geom);
      case 'Polygon': return new Polygon($geom);
      case 'MultiPolygon': return new MultiPolygon($geom);
      default: throw new \Exception("Dans Geometry::create(), type ".($type ? "'$type'" : 'null')." non reconnu");
    }
  }
  
  /** @param TGJSimpleGeometry $geom */
  function __construct(array $geom) {
    $this->type = $geom['type'];
    $this->coordinates = $geom['coordinates'];
  }
  
  /** @return TGJSimpleGeometry $geom */
  function asArray(): array { return ['type'=> $this->type, 'coordinates'=> $this->coordinates]; }
};

/** Point GeoJSON. */
class Point extends Geometry {};
/** MultiPoint GeoJSON. */
class MultiPoint extends Geometry {};

/** Linestring GeoJSON. */
class LineString extends Geometry {
  function reso(): float {
    $dists = [];
    $precPos = null;
    foreach ($this->coordinates as $i => $pos) {
      if (!$precPos) {
        $dists[] = Pos::dist($pos, $precPos);
      }
      $precPos = $pos;
    }
    sort($dists);
    //echo '<pre>'; print_r($dists);
    $dist = $dists[intval(count($dists)/10)];
    //echo "dist=$dist\n";
    return $dist;
  }
};

/** MultiLineString GeoJSON. */
class MultiLineString extends Geometry {};

/** Polygon GeoJSON. */
class Polygon extends Geometry {
  /** Estimation de la résolution */
  function reso(): float {
    $ls = new LineString(['type'=> 'LineString', 'coordinates'=> $this->coordinates[0]]);
    return $ls->reso();
  }
};

/** MultiPolygon GeoJSON. */
class MultiPolygon extends Geometry {
  /** Estimation de la résolution */
  function reso(): float {
    $ls = new LineString(['type'=> 'LineString', 'coordinates'=> $this->coordinates[0][0]]);
    return $ls->reso();
  }
};

/** Feature GeoJSON. */
class Feature {
  /** @var array<mixed> $properties  - les properties GeoJSON */
  readonly array $properties;
  /* Si le bbox est présent dans le GeoJson alors création d'un BBox. */
  readonly ?\bbox\BBox $bbox;
  /* La géométrie GeoJSON, éventuellement absente */
  readonly ?Geometry $geometry;
  
  /** @param TGeoJsonFeature $feature */
  function __construct(array $feature) {
    $this->properties = $feature['properties'] ?? null;
    $this->bbox = ($bbox = $feature['bbox'] ?? null) ?
      new \bbox\BBox(new \bbox\Pt($bbox[0], $bbox[1]), new \bbox\Pt($bbox[2], $bbox[3]))
      : null;
    $this->geometry = Geometry::create($feature['geometry'] ?? null);
  }
  
  /** @return TGeoJsonFeature */
  function asArray(): array {
    return [
      'type'=> 'Feature',
      'properties'=> $this->properties,
      'geometry'=> $this->geometry->asArray(),
    ];
  }
};

/** FeatureCollection GeoJSON. */
class FeatureCollection {
  /** @var list<Feature> $features */
  readonly array $features;
  
  /** @param TGeoJsonFeatureCollection $featCol */
  function __construct(array $featCol) {
    $this->features = array_map(
      function (array $feature) { return new Feature($feature); },
      $featCol['features'] ?? []
    );
  }
};

/** Lit un fichier contenant une FeatureCollection.
 * Je ne list par le bbox de la FeatureCollection pour 2 raisons:
 *  1) c'est assez compliqué car il faut commencer à lire le fichier
 $  é) ce n'est pas forcément très utile.
 */
class FileOfFC {
  readonly string $filePath;
  
  /** Initialisation. */
  function __construct(string $filePath) { $this->filePath = $filePath; }
  
  /** Lecture du fichier comme objet FeatureCollection. */
  function readFC(): FeatureCollection {
    $fc = file_get_contents($this->filePath);
    $fc = json_decode($fc, true);
    return new FeatureCollection($fc);
  }
  
  /** Lecture du fichier par un Generator générant des Feature.
   * Le fichier doit être structuré avec 1 ligne par Feature comme le produit ogr2ogr.
   */
  function readFeatures(): \Generator {
    $fgjs = fopen($this->filePath, 'r');
    $nol = 0;
    $maxlen = 0; // pour connaitre la longueur max d'une ligne
    // fgets garde le \n à la fin
    $buffLen = U::G; // je met $buffLen à 1 Go
    while ($buff = fgets($fgjs, $buffLen)) {
      /*echo "$nol (",strlen($buff),")> ",
        strlen($buff) < 1000 ? $buff : substr($buff, 0, 500)."...".substr($buff, -50),"<br>\n";*/
      $nol++;
      if (strlen($buff) > $maxlen)
        $maxlen = strlen($buff);
      if (substr($buff, 0, 20) <> '{ "type": "Feature",')
        continue;
      if (substr($buff, -3) == "},\n")
        $buff = substr($buff, 0, strlen($buff)-2);
      elseif (substr($buff, -2) == "}\n")
        $buff = substr($buff, 0, strlen($buff)-1);
      else
        throw new \Exception("Aucun cas de fin de buffer sur '".substr($buff, -100)."', la longueur du buffer ($buffLen) est probablement trop courte");
      $feature = json_decode($buff, true);
      if (!$feature)
        throw new \Exception("Erreur de json_decode()");
      //$feature = new Feature($feature);
      yield $feature;
    }
    //echo "maxlen=$maxlen</p>\n"; // maxlen=75_343_092
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<pre>\n";
if (0) { // @phpstan-ignore if.alwaysFalse 
  $point = Geometry::create(['type'=> 'Point', 'coordinates'=> [0,0]]);
  echo '$point='; print_r($point);
  $feature = new Feature([
    'type'=> 'Feature',
    'properties'=>[
      'a'=> 'vala',
    ],
    'geometry'=> $point->asArray(),
  ]);
  echo '$feature='; print_r($feature);
  $fc = new FeatureCollection(['type'=> 'FeatureCollection', 'features'=> [$feature->asArray()]]);
  echo '$fc='; print_r($fc);
}

elseif (0) { // @phpstan-ignore elseif.alwaysFalse 
  $foffc = new FileOfFC('ne110mphysical/ne_110m_coastline.geojson');
  echo '$foffc='; print_r($foffc);
  print_r($foffc->readFC());
}

elseif (0) { // @phpstan-ignore elseif.alwaysFalse 
  $foffc = new FileOfFC('ne110mphysical/ne_110m_coastline.geojson');
  echo '$foffc='; print_r($foffc);
  foreach ($foffc->readFeatures() as $feature) {
    echo '$feature='; print_r($feature);
  }
}

elseif (1) {
  ini_set('memory_limit', '10G');
  set_time_limit(5*60);
  $foffc = new FileOfFC('aecogpe2025/region.geojson');
  foreach ($foffc->readFeatures() as $feature) {
    $feature['geometry']['coordinates'] = [];
    echo '$feature='; print_r($feature);
    $feature = new Feature($feature);
    echo '$feature='; print_r($feature);
  }
}