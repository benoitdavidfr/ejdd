<?php
/** GBox - Définition d'une algèbre sur les rectangles englobants en coord. géo. avec des calculs tenant compte de l'antiméridien.
 *
 * @package BBox
 */
namespace BBox;

require_once __DIR__.'/bbox.php';

use Pos\Pos;

/**
 * Un rectangle englobant en coord. geo. gérant correctement les BBox à cheval sur l'antiméridien.
 *
 * Les BBox qui couvrent tte la Terre correspondent à une seule représentation, définie par convention à [-180@-90, 180@90],
 * et nommée WORLD.
 */
class GBox extends BBox {
  /** Fabrique un GBox à partir de 4 coordonnées dans l'ordre [lonWest, latSouth, lonEst, latNorth].
   * @param (list<float>|list<string>) $coords - liste de 4 coordonnées. */
  static function from4Coords(array $coords): self {
    self::isAListOf4Numbers($coords);
    return new self(
      [floatval($coords[0]), floatval($coords[1])],
      [floatval($coords[2]), floatval($coords[3])]
    );
  }
  
  /** Fabrique une GBox à partir d'une Pos.
   * @param TPos $pos
   */
  static function fromPos(array $pos): self { return new self($pos, $pos); }

  /** Affiche dans le même format que celui de la construction sauf pour l'espace vide qui est affiché par 'NONE'. */
  function __toString(): string {
    if ($this == GNONE)
      return 'GBox\NONE';
    if ($this == WORLD)
      return 'BBox\WORLD';
    elseif ($this->sw == $this->ne)
      return "$this->sw";
    else
      return "G[$this->sw,$this->ne]";
  }
  
  /** Le GBox intersecte t'il l'antimérdien ?
   * Lorsque le GBox chevauche l'antiméridien (antimeridian), la longitude du coin SW est supérieure à celle du coin NE
   */
  function crossesAntimeridian(): bool { return ($this->sw->lon > $this->ne->lon); }
  
  /** Agrandit la BBox pour contenir la liste de points tout en restant la plus petite possible.
   * @param list<Pt> $lpts
   */
  function extends(array $lpts): self {
    if ($this->isEmpty()) {  // Je commence par traiter le cas particulier où $this == NONE
      if (count($lpts) == 0) // Si la liste des points est vide
        return GNONE;        // alors le résultat est NONE
      else {                          // sinon
        $pt = array_shift($lpts);     // j'extraie le 1er point de la liste
        $bbox = self::fromPos($pt->pos()); // je crée un bbox avec ce 1er point
        return $bbox->extends($lpts); // j'appelle récursivement la méthode sur ce bbox avec le reste de la liste
      }
    }
    // je sais maintenant que ttes les coords de $this sont définies
    $lonWest = $this->sw->lon;
    $latSouth = $this->sw->lat;
    $lonEast = $this->ne->lon;
    $latNorth = $this->ne->lat;
    foreach ($lpts as $pt) {
      $latSouth = min($latSouth, $pt->lat);
      $latNorth = max($latNorth, $pt->lat);
      /*if ($lonWest <= $lonEast) { // la BBox courante ne chevauche pas l'antiméridien
        echo "la BBox courante ne chevauche pas l'antiméridien\n";
        if (($pt->lon < $lonWest) || ($pt->lon > $lonEast)) {
          if (Pt::deltaLon($pt->lon, $lonWest) < Pt::deltaLon($pt->lon, $lonEast)) {
            $lonWest = $pt->lon;
          }
          else {
            $lonEast = $pt->lon;
          }
        }
      }
      else { // $lonWest > $lonEast - la BBox courante chevauche l'antiméridien
        echo "la BBox courante chevauche l'antiméridien\n";
        if (($pt->lon < $lonWest) || ($pt->lon > $lonEast)) {
          if (Pt::deltaLon($pt->lon, $lonWest) < Pt::deltaLon($pt->lon, $lonEast)) {
            $lonWest = $pt->lon;
          }
          else {
            $lonEast = $pt->lon;
          }
        }
      }*/
        
      if (($pt->lon < $lonWest) || ($pt->lon > $lonEast)) {
        if (Pt::deltaLon($pt->lon, $lonWest) < Pt::deltaLon($pt->lon, $lonEast)) {
          $lonWest = $pt->lon;
        }
        else {
          $lonEast = $pt->lon;
        }
      }
      
    }
    // La BBox résultante ne peut jamais couvrir l'ensemble de la Terre
    return self::from4Coords([$lonWest, $latSouth, $lonEast, $latNorth]);
  }
  
  /** Fabrique une BBox à partir d'une LPos.
   * @param TLPos $lpos
   */
  static function fromLPos(array $lpos): GBox { return GNONE->extends(Pt::lPos2LPt($lpos)); }
  
  /** Le Pt $pt est-il inclus dans le BBox ? */
  function includesPt(Pt $pt): bool {
    if ($pt->lat < $this->sw->lat)
      return false;
    if ($pt->lat > $this->ne->lat)
      return false;
    if ($this->sw->lon <= $this->ne->lon) { // la BBox courante ne chevauche pas l'antiméridien
      return ($pt->lon >= $this->sw->lon) && ($pt->lon <= $this->ne->lon);
    }
    else { // $this->sw->lon > $this->ne->lon - la BBox courante chevauche l'antiméridien
      return ($pt->lon >= $this->sw->lon) || ($pt->lon <= $this->ne->lon);
    }
  }
  
  /** Union géométrique de $this et $b. Le résultat est toujours une BBox.
   * C'est très approximatif car l'extension aux 2 coins n'implique pas que les points inclus dans b seront dans $this !!!
   */
  function union(BBox $b): BBox {
    if ($this->isEmpty())
      return $b;
    if ($b->isEmpty())
      return $this;
    if ($this->includesPt($b->sw) && $this->includesPt($b->ne))
      return $this;
    throw new \Exception("TO BE IMPLEMENTED");
  }
  
  /** Fabrique une GBox à partir d'une LLPos.
   * @param TLLPos $llPos
   */
  static function fromLLPos(array $llPos): self {
    echo "Appel de GBox::fromLLPos()<br>\n";
    // Je transforme la LLPos en LLPt
    $llPt = array_map(function(array $lPos) { return Pt::lPos2LPt($lPos); }, $llPos);
    // Tranforme la LLPt en LBBox
    $lBBox = array_map(function(array $lPt) { return GNONE->extends($lPt); }, $llPt);
    // Union des bbox de lBBox pour donner le résultat
    echo '<pre>$lBBox='; print_r($lBBox);
    $rbbox = GNONE;
    foreach ($lBBox as $bbox)
      $rbbox = $rbbox->union($bbox);
    return $rbbox;
  }
};

/** Constante pour le BBox correspondant à l'espace vide. */
const GNONE = new GBox(null, null);
/** Constante pour la Terre en coords géo. */
const WORLD = new GBox([-180,-90], [180,90]);


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<pre>\n";
switch ($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=testFrom4Coords'>testFrom4Coords</a>\n";
    echo "<a href='?action=testPt::deltaLon'>testPt::deltaLon</a>\n";
    echo "<a href='?action=testExtends'>testExtends</a>\n";
    break;
  }
  case 'testFrom4Coords': {
    foreach ([
      [],
      ['a'=>'b','b'=>'c','c'=>'d','d'=>'e'],
      [0,1,2,'x'],
      [0,'1e-2','2.5','4'],
      [0, 1e-2, 2.5, 4],
      [0,1,2,'4a'],
    ] as $array) {
      try {
        echo GBox::from4Coords($array),"\n";
        print_r(GBox::from4Coords($array));
      } catch (\Exception $e) {
        echo "Exception ",$e->getMessage(),"\n";
      }
    }
    break;
  }
  case 'testPt::deltaLon': {
    echo Pt::deltaLon(5, 10),"<br>\n";
    echo Pt::deltaLon(10, 5),"<br>\n";
    echo Pt::deltaLon(178, -178),"<br>\n";
    echo Pt::deltaLon(-178, 178),"<br>\n";
    break;
  }
  case 'testExtends': {
    echo "<h2>testExtends</h2><pre>\n";
    $bbox = BBox::from4Coords([0, 45, 2, 47]);
    
    //*
    $pt = new Pt([1, 46]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";

    $pt = new Pt([3, 46]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";

    $pt = new Pt([3, 48]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";

    $pt = new Pt([178, 50]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";

    $pt = new Pt([-178, 50]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";

    $bbox = BBox::from4Coords([10, 45, 12, 47]);
    $pt = new Pt([-178, 50]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";
    //*/

    $bbox = BBox::from4Coords([0, 45, 2, 47]);
    $pt1 = new Pt([178, 50]);
    $pt2 = new Pt([-178, 50]);
    $res = $bbox->extends([$pt1,$pt2]);
    echo "$bbox ->extends([$pt1,$pt2]) -> $res\n\n";
    
    $bbox = BBox::from4Coords([0, 45, 2, 47]);
    $pt1 = new Pt([178, 50]);
    $pt2 = new Pt([-178, 50]);
    $pt3 = new Pt([0, 45]);
    $res = $bbox->extends([$pt1,$pt2,$pt3]);
    echo "$bbox ->extends([$pt1,$pt2,$pt3]) -> $res\n\n";
    
    $bbox = BBox::from4Coords([178, 45, -178, 47]);
    $pt = new Pt([1, 46]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";
    break;
  }
}