<?php
/**
 * Package des primitives GeoJSON.
 *
 * Chaque primitive GeoJSON correpond à une classe afin de définir les traitemments associées à ces primtives.
 * Ce package fonctionne en harmonie avec pos.inc.php et bbox.php
 *
 * Les coordonnées peuvent être en coordonnées géographiques ou cartésiennes mais certaines fonctionnalités nécessitent
 * des coordonnées géographiques.
 *
 * @package GeoJSON
 */
namespace GeoJSON;

require_once __DIR__.'/gbox.php';
require_once __DIR__.'/../drawing/drawing.php';

use Pos\Pos;
use Pos\LPos;
use Pos\LLPos;
use Pos\LLLPos;
use BBox\BBox;
use BBox\GBox;
use Drawing\Drawing;

/** Les grandeurs kilo, Méga, Giga, ... */
class U {
  const K = 1024;
  const M = 1024 * self::K;
  const G = 1024 * self::M;
};

/** Classe abstraite de géométrie GeoJSON portant la méthode create() de création d'une géométrie.
 * Les différentes primitives géométriques héritent de cette classe abstraite.
 */
abstract class Geometry {
  /** libellé court des types de géométrie pour affichage. */
  const SHORT_TYPES = [
    'Point' => 'SPt',
    'MultiPoint'=> 'MPt',
    'LineString'=> 'SLs',
    'MultiLineString'=> 'MLs',
    'Polygon'=> 'SPol',
    'MultiPolygon'=> 'MPol',
  ];

  readonly string $type;
  /** @var TPos|TLPos|TLLPos|TLLLPos $coordinates */
  readonly array $coordinates;
  
  /** Crée un sous-objet concret de Geometry à partir d'une géométrie simple GeoJSON.
   * @param TGJSimpleGeometry $geom */
  static function create(array $geom): self {
    return match ($type = $geom['type'] ?? null) {
      'Point'=> new Point($geom['coordinates']),
      'MultiPoint'=> new MultiPoint($geom['coordinates']),
      'LineString'=> new LineString($geom['coordinates']),
      'MultiLineString'=> new MultiLineString($geom['coordinates']),
      'Polygon'=> new Polygon($geom['coordinates']),
      'MultiPolygon'=> new MultiPolygon($geom['coordinates']),
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
  
  /** calcule le GBox à partir des coordonnées. N'a de sens que si les coords sont des coords. géo. */
  abstract function bbox(): BBox;

  /** Retourne une représentation string de la géométrie. */
  function toString(BBox $bbox): string {
    $shortType = self::SHORT_TYPES[$this->type] ?? null;
    return "{{$shortType}: $bbox}";
  }

  /** Dessine la primitive dans un dessin.
   * @param array<string,string|int> $style */
  abstract function draw(Drawing $drawing, array $style=[]): void;

  /** reprojète une géométrie, prend en paramètre une fonction de reprojection d'une position, retourne un objet géométrie */
  abstract function reproject(callable $reprojPos): self;
  
  /** La gémétrie chevauche t'elle l'antiméridien ? */
  function crossesAntimeridian(): bool { return $this->bbox()->crossesAntimeridian(); }
    
  /** Translate une géométrie en longitude de -360° ou +360°.
   * J'utilise une fonction de reprojection qui effectue la translation.
   */
  function translate(float $translate): self {
    return $this->reproject(function(array $pos) use($translate): array { return [$pos[0]+$translate, $pos[1]]; });
  }
};

/** Point GeoJSON ; coordinates est un TPos. */
class Point extends Geometry {
  /** @param TPos $coordinates */
  function __construct(array $coordinates) {
    if (!Pos::is($coordinates))
      throw new \Exception("Le type des coordonnées d'un Point doit être TPos");
    parent::__construct(['type'=> 'Point', 'coordinates'=> $coordinates]);
  }

  function bbox(): BBox { return BBox::fromPos($this->coordinates); }
  
  function draw(Drawing $drawing, array $style=[]): void {}
  
  function reproject(callable $reprojPos): self { return new self($reprojPos($this->coordinates)); }
};

/** MultiPoint GeoJSON ; coordinates est un TLPos. */
class MultiPoint extends Geometry {
  /** @param TLPos $coordinates */
  function __construct(array $coordinates) {
    if (!LPos::is($coordinates))
      throw new \Exception("Le type des coordonnées d'un MultiPoint doit être TLPos");
    parent::__construct(['type'=> 'MultiPoint', 'coordinates'=> $coordinates]);
  }

  function bbox(): BBox { return BBox::fromLPos($this->coordinates); }
  
  function draw(Drawing $drawing, array $style=[]): void {}
  
  function reproject(callable $reprojPos): self { return new self(LPos::reproj($reprojPos, $this->coordinates)); }
};

/** Linestring GeoJSON ; coordinates est un TLPos. */
class LineString extends Geometry {
  /** @param TLPos $coordinates */
  function __construct(array $coordinates) {
    if (!LPos::is($coordinates))
      throw new \Exception("Le type des coordonnées d'un LineString doit être TLPos");
    parent::__construct(['type'=> 'LineString', 'coordinates'=> $coordinates]);
  }

  function bbox(): BBox { return BBox::fromLPos($this->coordinates); }
  
  function reso(): float {
    $dists = [];
    $precPos = null;
    foreach ($this->coordinates as $i => $pos) {
      if (!$precPos) {
        $dists[] = Pos::manhattanDist($pos, $precPos);
      }
      $precPos = $pos;
    }
    sort($dists);
    //echo '<pre>'; print_r($dists);
    $dist = $dists[intval(count($dists)/10)];
    //echo "dist=$dist\n";
    return $dist;
  }
  
  function draw(Drawing $drawing, array $style=[]): void { $drawing->polyline($this->coordinates, $style); }
  
  function reproject(callable $reprojPos): self { return new self(LPos::reproj($reprojPos, $this->coordinates)); }
};

/** MultiLineString GeoJSON ; coordinates est un TLLPos. */
class MultiLineString extends Geometry {
  /** @param TLLPos $coordinates */
  function __construct(array $coordinates) {
    if (!LLPos::is($coordinates))
      throw new \Exception("Le type des coordonnées d'un MultiLineString doit être TLLPos");
    parent::__construct(['type'=> 'MultiLineString', 'coordinates'=> $coordinates]);
  }

  function bbox(): BBox { return BBox::fromLLPos($this->coordinates); }
  
  function draw(Drawing $drawing, array $style=[]): void {
    foreach ($this->coordinates as $coords)
      $drawing->polyline($coords, $style);
  }
  
  function reproject(callable $reprojPos): self { return new self(LLPos::reproj($reprojPos, $this->coordinates)); }
};

/** Polygon GeoJSON ; coordinates est un TLLPos. */
class Polygon extends Geometry {
  /** @param TLLPos $coordinates */
  function __construct(array $coordinates) {
    if (!LLPos::is($coordinates))
      throw new \Exception("Le type des coordonnées d'un Polygon doit être TLLPos");
    parent::__construct(['type'=> 'Polygon', 'coordinates'=> $coordinates]);
  }

  /** Calcule la bbox sur l'extérieur du polygone, cad le ring 0.
   * TEST d'utilisation de GBox au lieu de BBox.
   */
  function bbox(): BBox { return GBox::fromLPos($this->coordinates[0]); }
  
  /** Estimation de la résolution */
  function reso(): float { return (new LineString($this->coordinates[0]))->reso(); }
  
  function draw(Drawing $drawing, array $style=[]): void { $drawing->polygon($this->coordinates, $style); }

  function reproject(callable $reprojPos): self { return new self(LLPos::reproj($reprojPos, $this->coordinates)); }
};

/** MultiPolygon GeoJSON ; coordinates est un TLLLPos. */
class MultiPolygon extends Geometry {
  /** @param TLLLPos $coordinates */
  function __construct(array $coordinates) {
    if (!LLLPos::is($coordinates))
      throw new \Exception("Le type des coordonnées d'un MultiPolygon doit être TLLLPos");
    parent::__construct(['type'=> 'MultiPolygon', 'coordinates'=> $coordinates]);
  }

  function bbox(): BBox {
    // Fabrique la liste des rings extérieurs des polygones en prenant dans chaque polygone son extérieur 
    $lExtRings = array_map(function(array $llpos) { return $llpos[0]; }, $this->coordinates);
    // Je peux fabriquer le BBox à partir de ce LLPos
    return GBox::fromLLPos($lExtRings);
  }
  
  /** Estimation de la résolution */
  function reso(): float { return (new LineString($this->coordinates[0][0]))->reso(); }
  
  function draw(Drawing $drawing, array $style=[]): void {
    foreach ($this->coordinates as $coords)
      $drawing->polygon($coords, $style);
  }
  
  function reproject(callable $reprojPos): self { return new self(LLLPos::reproj($reprojPos, $this->coordinates)); }
};

/** Feature GeoJSON.
 * Attention selon la RFC 7946, dans un Feature en JSON, les champs type, properties et geometry sont obligatoires,
 * alors que les champs id et bbox sont facultatifs.
 * Si les propriétés ou la géométrie est absente, le champ correspondant contient null. 
 */
class Feature {
  /** ?int|string $id - Eventuellement un identifiant du Feature, sinon null. */
  readonly mixed $id;
  /** @var ?array<mixed> $properties  - les properties GeoJSON */
  readonly ?array $properties;
  /* Si le bbox est présent dans le GeoJSON alors il est stocké comme BBox, sinon null. */
  readonly ?BBox $bbox;
  /* La géométrie GeoJSON, éventuellement nulle */
  readonly ?Geometry $geometry;
  
  /** Fabrique un Feature à partir de sa représentation GeoJSON décodée.
   * TEST d'utilisation de GBox au lieu de BBox.
   * @param TGeoJsonFeature $feature */
  function __construct(array $feature) {
    $this->id = $feature['id'] ?? null;
    $this->properties = $feature['properties'] ?? null;
    $this->bbox = ($bbox = $feature['bbox'] ?? null) ? GBox::from4Coords($bbox) : null;
    $this->geometry = (isset($feature['geometry']) && $feature['geometry']) ? Geometry::create($feature['geometry']) : null;
  }
  
  /** Génère la représentation array du Feature qui peut être transformé en GeoJSON par un encodage en JSON.
   * @return TGeoJsonFeature */
  function asArray(): array {
    return array_merge(
      ['type'=> 'Feature'],
      !is_null($this->id) ? ['id'=> $this->id] : [],
      ['properties'=> $this->properties],
      !is_null($this->bbox) ? ['bbox'=> $this->bbox->as4Coords()] : [],
      ['geometry'=> $this->geometry ? $this->geometry->asArray() : null]
    );
  }
  
  /** Retourne le BBox et s'il n'est pas stocké alors le calcule. Retourne null ssi aucune géométrie n'est définie. */
  function bbox(): ?BBox { return $this->bbox ?? ($this->geometry ? $this->geometry->bbox() : null); }
  
  /** Génère un affichage du Feature en éludant les coordonnées de la géométrie. */
  function __toString(): string {
    //var_dump($this);
    return
       (!is_null($this->id) ? json_encode($this->id).': ' : '')
      .'{properties:'.json_encode($this->properties, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).', '
      .'geom:'.($this->geometry ? $this->geometry->toString($this->bbox()) : 'null')
      .'}';
  }

  /** Génère les Feature d'un fichier stockant une FeatureCollection, permettant ainsi de lire son contenu qui ne tient pas en mémoire.
   * Le fichier doit être structuré avec 1 ligne par Feature comme le produit ogr2ogr.
   * L'option permet de générer des TGeoJsonFeature au lieu des Feature pour faire des tests.
   * Attention, le bbox produit par ogr2ogr est faux lorsque le feature chevauche l'antiméridien.
   * @return \Generator<int, Feature|TGeoJsonFeature>
   */
  static function fromFile(string $filePath, ?string $option=null): \Generator {
    if (!($fgjs = @fopen($filePath, 'r'))) {
      throw new \Exception("Ouverture de $filePath");
    }
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
        throw new \Exception("Aucun cas de fin de buffer sur '".substr($buff, -100)
          ."', la longueur du buffer ($buffLen) est probablement trop courte");
      $feature = json_decode($buff, true);
      if (!$feature)
        throw new \Exception("Erreur de json_decode() sur la ligne $nol (à partir de 1)");
      //$feature = new Feature($feature);
      if ($option == 'TGeoJsonFeature') {
        /*if (($feature['bbox'][0] == -180) && ($feature['bbox'][2] == 180))
          unset($feature['bbox']);*/
        yield $noFeature++ => $feature;
      }
      else {
        // J'efface les bbox faux de ogr2ogr
        if (($feature['bbox'][0] == -180) && ($feature['bbox'][2] == 180))
          unset($feature['bbox']);
        yield $noFeature++ => new self($feature);
      }
    }
    //echo "maxlen=$maxlen</p>\n"; // maxlen=75_343_092
  }
  
  /** Transforme un Feature en tuple à retourner par Dataset::getItems().
   * Ce tuple est un pure Array récursif, cad composé uniq. d'array et d'int|float|string. Il reprend les champs de properties
   * plus un champ geometry qui contient type et coordinates plus le champ bbox de Feature s'il existe.
   * De plus les noms des champs de properties sont convertis en minuscules.
   * Enfin, l'option 'delPropertyId' permet de supprimer la propriété 'id'.
   * @param array{delFieldId?: bool} $options
   * @return array<string,mixed> */
  function toTuple(array $options=[]): array {
    // Je tranfère le bbox du Feature dans la géométrie pour l'intégration dans le n-uplet
    $feature = $this->asArray();
    $properties = array_change_key_case($feature['properties']);
    if ($options['delPropertyId'] ?? false)
      unset($properties['id']);
    //echo '<pre>'; print_r($properties);
    $geometry = $feature['geometry'];
    if (isset($feature['bbox']))
      $geometry = ['type'=> $geometry['type'], 'bbox'=> $feature['bbox'], 'coordinates'=> $geometry['coordinates']];
    return array_merge($properties, ['geometry'=> $geometry]);
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

  /** Lecture du fichier comme objet FeatureCollection qui en général ne tient pas en mémoire.*/
  static function fromFile(string $filePath): self {
    $fc = file_get_contents($filePath);
    $fc = json_decode($fc, true);
    return new self($fc);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


class GeoJSONTest {
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);

    echo "<title>geojson.inc.php</title><pre>\n";
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=deBase'>Tests de base</a>\n";
        echo "<a href='?action=lectureGrosFichier'>Test de lecture d'un gros fichier GeoJSON</a>\n";
        echo "<a href='?action=feature'>Test création et affichage Feature, y.c. cas limites</a>\n";
        echo "<a href='?action=aecogpe2025'>Lecture et affichage du fichier aecogpe2025/region.geojson</a>\n";
        echo "<a href='?action=AlaskaEEZ'>AlaskaEEZ - lecture et affichage d'un feature à cheval sur l'antiméridien</a>\n";
        break;
      }
      case 'deBase': { // Tests de base
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
        break;
      }
      case 'lectureGrosFichier': { // Test de lecture d'un gros fichier GeoJSON
        foreach (Feature::fromFile(__DIR__.'/../datasets/ne110mphysical/ne_110m_coastline.geojson') as $feature) {
          echo '$feature='; print_r($feature);
        }
        break;
      }
      case 'feature': { // Test création et affichage Feature, y.c. cas limites
        echo "<h2>Test création et affichage Feature, y.c. cas limites</h2>\n";
        foreach ([
          "minimal non conforme"=> [],
          "minimal non conforme2"=> ['type'=>'Feature'],
          "minimal conforme"=> ['type'=>'Feature', 'geometry'=> null],
          "minimal avec properties null"=> ['type'=>'Feature', 'properties'=> null, 'geometry'=> null],
          "uniq. prop."=> ['type'=>'Feature', 'properties'=> ['p'=>'v']],
          "geom Pt"=> [
            'type'=>'Feature',
            'properties'=> ['p'=>'v'],
            'geometry'=> ['type'=> 'Point', 'coordinates'=> [12.23, 56.78]]],
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
            echo "asArray: ",json_encode($feature->asArray()),"\n";
          }
          catch (\Exception $e) {
            echo "Erreur sur le feature: "; print_r($feature); 
            echo "Exception: ",$e->getMessage(),"<br>\n";
          }
        }
  
        echo "<h3>Test avec clé string</h3>\n";
        $feature = ['type'=>'Feature', 'id'=>'id56', 'properties'=> ['p'=>'v'], 'geometry'=>null];
        $feature = new Feature($feature);
        echo "feature: $feature<br>\n";

        echo "<h3>Test avec clé int</h3>\n";
        $feature = ['type'=>'Feature', 'id'=>56, 'properties'=> ['p'=>'v'], 'geometry'=>null];
        $feature = new Feature($feature);
        echo "feature: $feature<br>\n";
        break;
      }
      case 'aecogpe2025': { // Test de lecture et affichage du fichier aecogpe2025/region.geojson
        echo "<h2>Lecture et affichage du fichier aecogpe2025/region.geojson</h2>\n";
        foreach (Feature::fromFile(__DIR__.'/../datasets/aecogpe2025/region.geojson') as $feature) {
          echo "feature: $feature<br>\n";
          //print_r($feature);
        }
        break;
      }
      case 'AlaskaEEZ': { // Test de lecture et affichage d'un feature à cheval sur l'antiméridien
        echo "<h2>Lecture et affichage la ZEE de l'Alaska</h2>\n";
        echo "Cela montre que le calcul du BBox par ogr2ogr est faux<br>\n";
        foreach (Feature::fromFile(__DIR__.'/../datasets/worldeez/eez_v11.geojson', 'TGeoJsonFeature') as $feature) {
          if ($feature['properties']['MRGID'] == 8463) {
            $f = $feature;
            unset($f['bbox']);
            $f = new Feature($f);
            echo "feature: $f\n";
            echo 'feature issu du fichier GeoJSON='; print_r($feature);
            break;
          }
        }
        break;
      }
    }
  }
};
GeoJSONTest::main();
