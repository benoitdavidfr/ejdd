<?php
/** Autour des niveaux de zoom Leaflet.
 * @package ZoomLevel
 */
namespace ZoomLevel;

require_once __DIR__.'/bbox.php';
#require_once __DIR__.'/lib/coordsys.php';

use BBox\BBox;
#use CoordSys\CoordSys;

/** Autour des niveaux de zoom Leaflet. */
class ZoomLevel {
  /** Demi grand axe de l'ellipsoide IAG_GRS_1980 - en anglais Equatorial radius - en mètres */
  const IAG_GRS_1980_A = 6378137.0;
  /** Résolution std d'un pixel défini dans le standard WMS, en mètres. */
  const STD_PIXEL_SIZE_IN_METERS = 2.8e-4;
    
  /** extension des coordonnées WebMercator */
  static function WebMercatorExtension(): float { return 2 * pi() * self::IAG_GRS_1980_A; }
  
  /** Dénominateur de l'échelle de la tuile 0 de Leaflet. */
  static function scaleDenLevel0(): float {
    // taille terrain réel / taille carte
    return self::WebMercatorExtension() / (self::STD_PIXEL_SIZE_IN_METERS * 256);
  }
  
  /** Calcule le dénominateur de l'échelle pour le niveau $level. */
  static function scaleDenLevel(int $level): float {
    return self::scaleDenLevel0() / (2**$level);
  }
  
  /** Calcule de niveau de zoom adapté pour visualiser un BBox ; le calcul est effectué en degrés. */
  static function fromBBox(BBox $bbox): int {
    $size = $bbox->size();
    //echo "size=$size<br>\n";
    $maxSize = sqrt(360*360 + 180*180)/sqrt(2);
    $level = - log($size/$maxSize, 2);
    //echo "level=$level<br>\n";
    if ($level > 18)
      $level = 18;
    return (int)round($level);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // séparateur


//echo "maxSize=", sqrt(360*360 + 180*180)/sqrt(2),"<br>\n";
switch ($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=scaleDenLevel0'>scaleDenLevel0</a><br>\n";
    echo "<a href='?action=scaleDen'>scaleDen</a><br>\n";
    echo "<a href='?action=fromBBox'>fromBBox</a><br>\n";
    break;
  }
  case 'scaleDenLevel0': {
    printf("scaleDenLevel0 = %d<br>\n", ZoomLevel::scaleDenLevel0());
    break;
  }
  case 'scaleDen': {
    echo "<pre>zoomLevel:\n";
    for ($level=0; $level<=18; $level++) {
      $scaleDen = sprintf("%d", ZoomLevel::scaleDenLevel($level));
      $scaleDen = preg_replace('!(\d\d\d)$!', '_$1', $scaleDen);
      $scaleDen = preg_replace('!(\d)(\d\d\d)_!', '$1_$2_', $scaleDen);
      printf("  %d: 1:%s\n", $level, $scaleDen);
    }
    break;
  }
  case 'fromBBox': {
    foreach ([
      'world'=> [-180, -90, 180, 90],
      'PfZee'=> [-158.13, -31.24, -131.98, -4.54],
      '1°'=> [-0.5, -0.5, 0.5, 0.5],
    ] as $t => $bbox) {
      echo "$t -> deg->",ZoomLevel::fromBBox(BBox::from4Coords($bbox)),"<br>\n";
    }
    $unMetre = 1/ZoomLevel::WebMercatorExtension()*360;
    $dixMetre = 10 * $unMetre;
    echo "10m=$dixMetre<br>\n";
    $bbox = [-$dixMetre, -$dixMetre, $dixMetre, $dixMetre];
    echo "10m-> deg->",ZoomLevel::fromBBox(BBox::from4Coords($bbox)),"<br>\n";
    break;
  }
}


