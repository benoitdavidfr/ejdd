<?php
/** Fonctions sur des positions et des listes de positions.
 * Une position est une liste de 2 coordonnées. Le type est défini dans PhpStan. La classe Pos regroupe les fonctions sur Pos.
 * De même LPos est une liste de Position, LLPos une liste de LPos et LLLPos une liste de LLPos.
 * Le type ne préjuge pas s'il s'agit de coordonnées géographiques ou cartésiennes, ainsi la distance ne tient pas compte de
 * l'antiméridien.
 * @package Pos
 */
namespace Pos;

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
  
  /** Distance de Manhattan entre 2 positions, ne tient pas compte de l'antimérdien.
   * Voir https://fr.wikipedia.org/wiki/Distance_de_Manhattan
   * @param TPos $pos1
   * @param TPos $pos2
   */
  static function manhattanDist(array $pos1, array $pos2): float {
    return abs($pos2[0]-$pos1[0]) + abs($pos2[1]-$pos1[1]);
  }

  /** reprojète une TPos
   * @param TPos $pos
   * @return TPos */
  static function reproj(callable $reprojPos, array $pos): array { return $reprojPos($pos); }
};

/** Un BiPos est une liste de 4 coordonnées dans l'ordre [westLon, southLat, eastLon, northLat] utilisée pour représenter un BBox en GeoJSON. */
class BiPos {
  /** Teste si le paramètre est un BiPos.
   * @param (list<float>|list<string>) $coords - liste de 4 nombres. */
  static function is(array $coords): bool {
    //print_r($coords);
    if (count($coords) <> 4)
      return false;
    for ($i=0; $i<4; $i++) {
      if (!is_numeric($coords[$i]))
        return false;
    }
    return true;
  }
};

/** Fonction sur les TLPos, définies comme liste de TPos. */
class LPos {
  /** Vérifie que le paramètre est un TLPos.
   * @param TLPos $lpos
   */
  static function is(mixed $lpos): bool {
    if (!is_array($lpos))
      return false;
    foreach ($lpos as $pos) {
      if (!Pos::is($pos))
        return false;
    }
    return true;
  }
  
  /** reprojète une TLPos et la retourne
   * @param TLPos $lpos
   * @return TLPos */
  static function reproj(callable $reprojPos, array $lpos): array { return array_map($reprojPos, $lpos); }
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
  
  /** reprojète une TLLPos et la retourne
   * @param TLLPos $llpos
   * @return TLLPos */
  static function reproj(callable $reprojPos, array $llpos): array {
    return array_map(function($lpos) use($reprojPos) { return LPos::reproj($reprojPos, $lpos); }, $llpos);
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

  /** reprojète une TLLLPos et la retourne
   * @param TLLLPos $lllpos
   * @return TLLLPos */
  static function reproj(callable $reprojPos, array $lllpos): array {
    return array_map(function($llpos) use($reprojPos) { return LLPos::reproj($reprojPos, $llpos); }, $lllpos);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 

