<?php
/** Bibliothèque d'utilisation du GeoJSON */
//ini_set('memory_limit', '10G');

/** Les grandeurs kilo, Méga, Giga, ... */
class U {
  const K = 1024;
  const M = 1024 * self::K;
  const G = 1024 * self::M;
};

/** Fonctions sur les positions */
class Pos {
  /** distance entre 2 positions */
  static function dist(array $pos1, array $pos2): float {
    return abs($pos2[0]-$pos1[0]) + abs($pos2[1]-$pos1[1]);
  }
};

/** Géométrie GeoJSON */
abstract class Geometry {
  readonly string $type;
  readonly array $coordinates;
  
  static function create(array $geom): self {
    switch ($type = $geom['type'] ?? null) {
      case 'Point': return new Point($geom);
      case 'MultiPoint': return new MultiPoint($geom);
      case 'LineString': return new LineString($geom);
      case 'MultiLineString': return new MultiLineString($geom);
      case 'Polygon': return new Polygon($geom);
      case 'MultiPolygon': return new MultiPolygon($geom);
      default: throw new Exception("Dans Geometry::create(), type ".($type ? "'$type'" : 'null')." non reconnu");
    }
  }
  
  function __construct(array $geom) { $this->type = $geom['type']; $this->coordinates = $geom['coordinates']; }
  
  function asArray(): array { return ['type'=> $this->type, 'coordinates'=> $this->coordinates]; }
};

class Point extends Geometry {};
class MultiPoint extends Geometry {};

class LineString extends Geometry {
  function reso(): float {
    $dists = [];
    foreach ($this->coordinates as $i => $pos) {
      if ($i <> 0) {
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

class MultiLineString extends Geometry {};

class Polygon extends Geometry {
  /** Estimation de la résolution */
  function reso(): float {
    $ls = new LineString(['type'=> 'LineString', 'coordinates'=> $this->coordinates[0]]);
    return $ls->reso();
  }
};

class MultiPolygon extends Geometry {
  /** Estimation de la résolution */
  function reso(): float {
    $ls = new LineString(['type'=> 'LineString', 'coordinates'=> $this->coordinates[0][0]]);
    return $ls->reso();
  }
};
  
class Feature {
  readonly array $properties;
  readonly Geometry $geometry;
  
  function __construct(array $feature) {
    $this->properties = $feature['properties'] ?? null;
    $this->geometry = Geometry::create($feature['geometry'] ?? null);
  }
  
  function asArray(): array {
    return [
      'type'=> 'FeatureCollection',
      'properties'=> $this->properties,
      'geometry'=> $this->geometry->asArray(),
    ];
    }
};

class FeatureCollection {
  readonly array $features;
  
  function __construct(array $featCol) {
    $this->features = array_map(
      function (array $feature) { return new Feature($feature); },
      $featCol['features'] ?? []
    );
  }
};

/** Un fichier contenant une FeatureCollection */
class FileOfFC {
  readonly string $filePath;
  
  function __construct(string $filePath) { $this->filePath = $filePath; }
  
  function readFC(): FeatureCollection {
    $fc = file_get_contents($this->filePath);
    $fc = json_decode($fc, true);
    return new FeatureCollection($fc);
  }
  
  function readFeatures(): Generator {
    $fgjs = fopen($this->filePath, 'r');
    $nol = 0;
    $maxlen = 0;
    // fgets garde le \n à la fin
    $buffLen = U::G;
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
        throw new Exception("Aucun cas de fin de buffer sur '".substr($buff, -100)."', la longueur du buffer ($buffLen) est probablement trop courte");
      $feature = json_decode($buff, true);
      if (!$feature)
        throw new Exception("Erreur de json_decode()");
      //$feature = new Feature($feature);
      yield $feature;
    }
    //echo "maxlen=$maxlen</p>\n"; // maxlen=75_343_092
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<pre>\n";
if (0) {
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

if (0) {
  $foffc = new FileOfFC('ne110mphysical/ne_110m_coastline.geojson');
  echo '$foffc='; print_r($foffc);
  print_r($foffc->readFC());
}

if (1) {
  $foffc = new FileOfFC('ne110mphysical/ne_110m_coastline.geojson');
  echo '$foffc='; print_r($foffc);
  foreach ($foffc->readFeatures() as $feature) {
    echo '$feature='; print_r($feature);
  }
}
