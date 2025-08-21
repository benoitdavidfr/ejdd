<?php
namespace geojson;
/** Bibliothèque des primitives GeoJSON.
 * Cette bibliothèque fonctionne en harmonie avec bbox.php
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

/** Fonctions sur les positions (TPos) définies comme une liste de 2 nombres. */
class Pos {
  /** Vérifie que le paramètre est un TPos.
   * @param TPos $pos
   */
  static function is(mixed $pos): bool {
    if (!is_array($pos))
      return false;
    foreach([0,1] as $i) {
      if (!isset($pos[$i]))
        return false;
      if (!is_float($pos[$i]) && !is_int($pos[$i]))
        return false;
    }
    return true;
  }
  
  /** distance entre 2 positions.
   * @param TPos $pos1
   * @param TPos $pos2
   */
  static function dist(array $pos1, array $pos2): float {
    return abs($pos2[0]-$pos1[0]) + abs($pos2[1]-$pos1[1]);
  }
};

/** Fonction sur les TLPos, définies comme liste de TPos. */
class LPos {
  /** Vérifie que le paramètre est un TLPos.
   * @param TLPos $geom
   */
  static function is(mixed $geom): bool {
    if (!is_array($geom))
      return false;
    foreach ($geom as $pos) {
      if (!Pos::is($pos))
        return false;
    }
    return true;
  }
};

/** Fonction sur les TLLPos, définies comme liste de TLPos. */
class LLPos {
  /** Vérifie que le paramètre est un TLLPos.
   * @param TLLPos $geom
   */
  static function is(mixed $geom): bool {
    if (!is_array($geom))
      return false;
    foreach ($geom as $lpos) {
      if (!LPos::is($lpos))
        return false;
    }
    return true;
  }
};

/** Fonction sur les TLLLPos, définies comme liste de TLLPos. */
class LLLPos {
  /** Vérifie que le paramètre est un TLLLPos.
   * @param TLLLPos $geom
   */
  static function is(mixed $geom): bool {
    if (!is_array($geom))
      return false;
    foreach ($geom as $lpos) {
      if (!LLPos::is($lpos))
        return false;
    }
    return true;
  }
};

/** Classe abstraite de géométrie GeoJSON portant la méthode create() de création d'une géométrie.
 * Les différentes primitives géométritques héritent de cette classe abstraite.
 */
abstract class Geometry {
  readonly string $type;
  /** @var TPos|TLPos|TLLPos|TLLLPos $coordinates */
  readonly array $coordinates;
  
  /** Crée un sous-objet concret de Geometry à partir d'une géométrie simple GeoJSON.
   * @param TGJSimpleGeometry $geom */
  static function create(array $geom): self {
    return match ($type = $geom['type'] ?? null) {
      'Point'=> new Point($geom),
      'MultiPoint'=> new MultiPoint($geom),
      'LineString'=> new LineString($geom),
      'MultiLineString'=> new MultiLineString($geom),
      'Polygon'=> new Polygon($geom),
      'MultiPolygon'=> new MultiPolygon($geom),
      default=> throw new \Exception("Dans Geometry::create(), type=".($type?"'$type'":'null')." non reconnu"),
    };
  }
  
  /** @param TGJSimpleGeometry $geom */
  function __construct(array $geom) {
    $this->type = $geom['type'];
    $this->coordinates = $geom['coordinates'];
  }
  
  /** @return TGJSimpleGeometry $geom */
  function asArray(): array { return ['type'=> $this->type, 'coordinates'=> $this->coordinates]; }
  
  /** calcule le BBox à partir des coordonnées. */
  abstract function bbox(): \bbox\BBox;
};

/** Point GeoJSON ; coordinates est un TPos. */
class Point extends Geometry {
  function __construct(array $geom) {
    if (!Pos::is($geom['coordinates']))
      throw new \Exception("Le type des coordonnées d'un Point doit TPos");
    parent::__construct($geom);
  }

  function bbox(): \bbox\BBox { return \bbox\BBox::fromPos($this->coordinates); }
};

/** MultiPoint GeoJSON ; coordinates est un TLPos. */
class MultiPoint extends Geometry {
  function __construct(array $geom) {
    if (!LPos::is($geom['coordinates']))
      throw new \Exception("Le type des coordonnées d'un MultiPoint doit être TLPos");
    parent::__construct($geom);
  }

  function bbox(): \bbox\BBox { return \bbox\BBox::fromLPos($this->coordinates); }
};

/** Linestring GeoJSON ; coordinates est un TLPos. */
class LineString extends Geometry {
  function __construct(array $geom) {
    if (!LPos::is($geom['coordinates']))
      throw new \Exception("Le type des coordonnées d'un LineString doit être TLPos");
    parent::__construct($geom);
  }

  function bbox(): \bbox\BBox { return \bbox\BBox::fromLPos($this->coordinates); }
  
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

/** MultiLineString GeoJSON ; coordinates est un TLLPos. */
class MultiLineString extends Geometry {
  function __construct(array $geom) {
    if (!LLPos::is($geom['coordinates']))
      throw new \Exception("Le type des coordonnées d'un MultiLineString doit être TLLPos");
    parent::__construct($geom);
  }

  function bbox(): \bbox\BBox { return \bbox\BBox::fromLLPos($this->coordinates); }
};

/** Polygon GeoJSON ; coordinates est un TLLPos. */
class Polygon extends Geometry {
  function __construct(array $geom) {
    if (!LLPos::is($geom['coordinates']))
      throw new \Exception("Le type des coordonnées d'un Polygon doit être TLLPos");
    parent::__construct($geom);
  }

  /** Calcule la bbox sur l'extérieur du polygone, cad le ring 0. */
  function bbox(): \bbox\BBox { return \bbox\BBox::fromLPos($this->coordinates[0]); }
  
  /** Estimation de la résolution */
  function reso(): float {
    $ls = new LineString(['type'=> 'LineString', 'coordinates'=> $this->coordinates[0]]);
    return $ls->reso();
  }
};

/** MultiPolygon GeoJSON ; coordinates est un TLLLPos. */
class MultiPolygon extends Geometry {
  function __construct(array $geom) {
    if (!LLLPos::is($geom['coordinates']))
      throw new \Exception("Le type des coordonnées d'un MultiPolygon doit être TLLLPos");
    parent::__construct($geom);
  }

  function bbox(): \bbox\BBox {
    // Fabrique la liste des rings extérieurs des polygones en prenant dans chaque polygone son extérieur 
    $lExtRings = array_map(function(array $llpos) { return $llpos[0]; }, $this->coordinates);
    // Je peux fabriquer le BBox à partir de ce LLPos
    return \bbox\BBox::fromLLPos($lExtRings);
  }
  
  /** Estimation de la résolution */
  function reso(): float {
    $ls = new LineString(['type'=> 'LineString', 'coordinates'=> $this->coordinates[0][0]]);
    return $ls->reso();
  }
};

/** Feature GeoJSON. */
class Feature {
  /** libellé court des types de géométrie pour affichage. */
  const SHORT_TYPES = [
    'Point' => 'SPt',
    'MultiPoint'=> 'MPt',
    'LineString'=> 'SLs',
    'MultiLineString'=> 'MLs',
    'Polygon'=> 'SPol',
    'MultiPolygon'=> 'MPol',
  ];
  /** ?int|string $id - Eventuellement un içdentifiant du Feature. */
  readonly mixed $id;
  /** @var ?array<mixed> $properties  - les properties GeoJSON */
  readonly ?array $properties;
  /* Si le bbox est présent dans le GeoJSON alors stockage comme BBox. */
  readonly ?\bbox\BBox $bbox;
  /* La géométrie GeoJSON, éventuellement absente */
  readonly ?Geometry $geometry;
  
  /** @param TGeoJsonFeature $feature
   * @param ?(int|string) $id */
  function __construct(array $feature, mixed $id=null) {
    $this->id = $id;
    $this->properties = $feature['properties'] ?? null;
    $this->bbox = ($bbox = $feature['bbox'] ?? null) ? \bbox\BBox::from4Coords($bbox) : null;
    $this->geometry = isset($feature['geometry']) ? Geometry::create($feature['geometry']) : null;
  }
  
  /** @return TGeoJsonFeature */
  function asArray(): array {
    return array_merge(
      ['type'=> 'Feature'],
      $this->properties ? ['properties'=> $this->properties] : [],
      $this->geometry ? ['geometry'=> $this->geometry->asArray()] : []
    );
  }
  
  /** Retourne le BBox et s'il n'est pas stocké alors le calcule. Retourne null si aucune géométrie n'est définie. */
  function bbox(): ?\bbox\BBox { return $this->bbox ?? ($this->geometry ? $this->geometry->bbox() : null); }
  
  /** Retourne une représentation string de la géométrie. */
  static function geomToString(?\bbox\BBox $bbox, ?Geometry $geom): string {
    if (!$geom)
      return 'NONE';
    $shortType = self::SHORT_TYPES[$geom->type] ?? null;
    return "{{$shortType}: $bbox}";
  }
  
  /** Génère un affichage du Feature en éludant les coordonnées de la géométrie. */
  function __toString(): string {
    //var_dump($this);
    return
       (!is_null($this->id) ? json_encode($this->id).'=> ' : '')
      .'{properties:'.json_encode($this->properties, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).', '
      .'geom:'.self::geomToString($this->bbox(), $this->geometry)
      .'}';
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
 * Je ne lit par le bbox de la FeatureCollection pour 2 raisons:
 *  1) c'est assez compliqué car il faut commencer à lire le fichier
 *  2) ce n'est pas forcément très utile.
 */
class FileOfFC {
  readonly string $filePath;
  
  /** Initialisation. */
  function __construct(string $filePath) { $this->filePath = $filePath; }
  
  /** Lecture du fichier comme objet FeatureCollection. En général cela ne tient pas en mémoire.*/
  function readFC(): FeatureCollection {
    $fc = file_get_contents($this->filePath);
    $fc = json_decode($fc, true);
    return new FeatureCollection($fc);
  }
  
  /** Lecture du fichier par un Generator générant des Feature. Plus facile pour ce ca tienne en mémoire.
   * Le fichier doit être structuré avec 1 ligne par Feature comme le produit ogr2ogr.
   */
  function readFeatures(): \Generator {
    $fgjs = fopen($this->filePath, 'r');
    $nol = 0;
    $maxlen = 0; // pour connaitre la longueur max d'une ligne
    // fgets garde le \n à la fin
    $noFeature = 0;
    $buffLen = U::G; // je met $buffLen à 1 Go
    while ($buff = fgets($fgjs, $buffLen)) {
      /*echo "$nol (",strlen($buff),")> ",
        strlen($buff) < 1000 ? $buff : substr($buff, 0, 500)."...".substr($buff, -50),"<br>\n";*/
      $nol++;
      if (($len = strlen($buff)) > $maxlen)
        $maxlen = $len;
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
        throw new \Exception("Erreur de json_decode() sur la ligne $nol (à partir de 1)");
      //$feature = new Feature($feature);
      yield $noFeature++ => $feature;
    }
    //echo "maxlen=$maxlen</p>\n"; // maxlen=75_343_092
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<title>geojson.inc.php</title><pre>\n";
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

// Test création et affichage Feature
elseif (0) { // @phpstan-ignore elseif.alwaysFalse
  echo "<h2>Test création et affichage Feature</h2>\n";
  foreach ([
    "minimal"=> ['type'=>'Feature'],
    "uniq. prop."=> ['type'=>'Feature', 'properties'=> ['p'=>'v']],
    "geom Pt"=> ['type'=>'Feature', 'properties'=> ['p'=>'v'], 'geometry'=> ['type'=> 'Point', 'coordinates'=> [12.23, 56.78]]],
    "geom MPt"=> [
      'type'=>'Feature',
      'properties'=> ['p'=>'v'],
      'geometry'=> ['type'=> 'MultiPoint', 'coordinates'=> [[12.23, 56.78],[42.23, 56.78]]]
    ],
    "erroné sur type coordonnées"=> [
      'type'=>'Feature',
      'properties'=> ['p'=>'v'],
      'geometry'=> ['type'=> 'MultiPoint', 'coordinates'=> [12.23, 56.78]]
    ],
  ] as $title => $feature) {
    echo "<h3>$title</h3>\n";
    try {
      $feature = new Feature($feature);
      echo "feature: $feature<br>\n";
    }
    catch (\Exception $e) {
      echo "Erreur sur le feature: "; print_r($feature); 
      echo "Exception: ",$e->getMessage(),"<br>\n";
    }
  }
  
  echo "<h3>Test avec clé string</h3>\n";
  $feature = ['type'=>'Feature', 'properties'=> ['p'=>'v']];
  $feature = new Feature($feature, 'id56');
  echo "feature: $feature<br>\n";

  echo "<h3>Test avec clé int</h3>\n";
  $feature = ['type'=>'Feature', 'properties'=> ['p'=>'v']];
  $feature = new Feature($feature, 56);
  echo "feature: $feature<br>\n";
}

elseif (1) {
  echo "<h2>Lecture et affichage du fichier aecogpe2025/region.geojson</h2>\n";
  ini_set('memory_limit', '10G');
  set_time_limit(5*60);
  $foffc = new FileOfFC('aecogpe2025/region.geojson');
  foreach ($foffc->readFeatures() as $noFeature => $feature) {
    //$feature['geometry']['coordinates'] = [];
    //echo '$feature='; print_r($feature);
    $feature = new Feature($feature, $noFeature);
    echo "feature: $feature<br>\n";
  }
}