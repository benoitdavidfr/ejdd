<?php
/** Autour des niveaux de zoom Leaflet.
 * @package Misc
 */
namespace Misc;

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
  
  static function scaleDenLevel(int $level): float {
    return self::scaleDenLevel0() / (2**$level);
  }
};

//printf("scaleDenLevel0 = %d<br>\n", ZoomLevel::scaleDenLevel0());

echo "<pre>zoomLevel:\n";
for ($level=0; $level<=18; $level++) {
  $scaleDen = sprintf("%d", ZoomLevel::scaleDenLevel($level));
  $scaleDen = preg_replace('!(\d\d\d)$!', '_$1', $scaleDen);
  $scaleDen = preg_replace('!(\d)(\d\d\d)_!', '$1_$2_', $scaleDen);
  printf("  %d: 1:%s\n", $level, $scaleDen);
}
