<?php
/** GBox - Définition d'une algèbre sur les boites englobantes en coord. géo. gérant correctement les géométries chevauchant l'antiméridien.
 *
 * @package BBox
 */
namespace BBox;

require_once __DIR__.'/bbox.php';

use Pos\Pos;
use Pos\BiPos;

/**
 * Une boite englobante en coord. geo. gérant correctement les géométries chevauchant l'antiméridien.
 *
 * Lorsque la GBox ne chevauche pas l'antiméridien (AM), $sw->lon <= $ne->lon, c'est la représentation classique.
 * Si $sw->lon == $ne->lon alors la GBox est réduite soit à 1 point, soit à segment de méridien.
 * Si $sw->lon > $ne->lon alors la GBox chevauche l'AM
 *   et on peut se la représenter comme l'union de 2 boites, la 1ère de $sw->lon à +180° et la 2nd de -180° à $ne->lon.
 * Enfin, cas particuliers:
 *  - si la GBox couvre ttes les longitudes, elle est codée [-180, +180] et on considère qu'elle chevauche l'AM.
 *  - un segment d'AM est codé [+180, +180] et on considère qu'il NE chevauche PAS l'AM.
 *
 * Le calcul d'une GBox se fait fondamentalement sur les coordonnées d'une LineString (LPos).
 * On fait l'hypothèse que ces coordonnées respectent les règles de GeoJSON, ainsi lorsqu'une LineString par exemple chevauche l'AM
 * elle est décomposé en 2, l'une à l'ouest de l'AM et l'autre à l'Est regroupées dans un MultiLineString.
 * Ainsi les coordonnées d'une LineString ne chevauche jamais l'AM.
 *
 * En conséquence la méthode extends() qui prend en paramètre une liste de Pt fait l'hypothèse que cette LineString ne chevauche pas l'AM.
 * Par contre la méthode union() qui agrège 2 GBox doit pouvoir prendre des GBox chevauchant l'AM et produire une GBox chevauchant l'AM.
 */
class GBox extends BBox {
  /** Fabrique un GBox avec vérification des contraintes d'intégrité.
   * Les latitudes doivent être comprises entre -90 et +90.
   * Les longitudes sont ramenées entre -180 et +180 autorisant ainsi à calculer un GBox sur des géométries translatées de 360°.
   * @param ?TPos $sw - le coin SW comme TPos ou []
   * @param ?TPos $ne - le coin NE comme TPos ou []
   */
  function __construct(?array $sw, ?array $ne) {
    if (!$sw) {
      parent::__construct($sw, $ne);
      return;
    }
    if (($sw[1] < -90) || ($sw[1] > 90) || ($ne[1] < -90) || ($ne[1] > 90))
      throw new \Exception("Dans la construction d'une GBox, les latitudes doivent être comprises entre -90 et +90.");
    if ($sw[1] > $ne[1])
      throw new \Exception("Dans la construction d'une GBox, le coin SW doit être au Sud du coin NE.");
    if (($sw[0] < -180) || ($sw[0] > 180)) {
      $sw[0] = fmod($sw[0], 360);
      if ($sw[0] >= 180)
        $sw[0] -= 360;
    }
    if (($ne[0] < -180) | ($ne[0] > +180)) {
      $ne[0] = fmod($ne[0], 360);
      if ($ne[0] > 180)
        $ne[0] -= 360;
    }
    parent::__construct($sw, $ne);
  }
  
  /** Fabrique un GBox à partir de 4 coordonnées dans l'ordre [lonWest, latSouth, lonEst, latNorth].
   * @param (list<float>|list<string>) $coords - liste de 4 coordonnées. */
  static function from4Coords(array $coords): self {
    if (!BiPos::is($coords))
      throw new \Exception("Le paramètre n'est pas une liste de 4 nombres");
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
  
  /** Le GBox chevauche t'il l'antiméridien ? */
  function crossesAntimeridian(): bool { return ($this->sw->lon > $this->ne->lon) || (($this->sw->lon == -180) && ($this->ne->lon == +180)); }
  
  /* * PERIME Retourne une GBox agrandie pour contenir les segments définis par la liste de points tout en restant la plus petite possible.
   * @param list<Pt> $lpts * /
  function PERIMEextends(array $lpts): self {
    if ($this->isEmpty()) {  // Je commence par traiter le cas particulier de la GBox est vide
      if (count($lpts) == 0) // Si la liste des points est vide
        return GNONE;        // alors le résultat est GNONE
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
      
      /*if ($lonWest <= $lonEast) { // la GBox courante ne chevauche pas l'antiméridien
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
      else { // $lonWest > $lonEast - la BBox courante chevauche l'antiméridien <=> [-180 -> lonEast] + [lonWest -> +180]
        echo "la BBox courante chevauche l'antiméridien\n";
        if (($pt->lon > $lonEast) && ($pt->lon < $lonWest)) {
          if (Pt::deltaLon($pt->lon, $lonWest) < Pt::deltaLon($pt->lon, $lonEast)) {
            $lonWest = $pt->lon;
          }
          else {
            $lonEast = $pt->lon;
          }
        }
      }* /
      
      if ( // la GBox courante NE chevauche PAS l'antiméridien -> pt->lon à l'extérieur de l'intervalle
          (($lonWest <= $lonEast) && (($pt->lon < $lonWest) || ($pt->lon > $lonEast)))
         ||
            // la GBox courante chevauche l'antiméridien -> pt->lon est dans l'intervalle extérieur
          (($lonWest >  $lonEast) && (($pt->lon > $lonEast) && ($pt->lon < $lonWest)))
         ) {
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
  }*/
  
  /** Agrandit la GBox au plus juste pour qu'elle contienne la liste de points.
   * @param list<Pt> $lpts
   */
  function extends(array $lpts): self {
    $sw = $this->sw;
    $ne = $this->ne;
    foreach ($lpts as $pt) {
      $sw = $sw ? $sw->min($pt) : $pt;
      $ne = $ne ? $ne->max($pt) : $pt;
    }
    return new self($sw->pos(), $ne->pos());
  }
  
  /** Fabrique une GBox à partir des coords d'une LineString ne chevauchant pas l'AM définie comme une LPos.
   * @param TLPos $coords
   */
  static function fromLineString(array $coords): GBox { return GNONE->extends(Pt::lPos2LPt($coords)); }
  
  /** Le Pt $pt est-il inclus dans le BBox ? */
  function includesPt(Pt $pt): bool {
    if (($pt->lat < $this->sw->lat) || ($pt->lat > $this->ne->lat))
      return false;
    if ($this->sw->lon <= $this->ne->lon) { // la BBox courante ne chevauche pas l'AM
      return ($pt->lon >= $this->sw->lon) && ($pt->lon <= $this->ne->lon);
    }
    else { // $this->sw->lon > $this->ne->lon - la BBox courante chevauche l'AM
      return ($pt->lon >= $this->sw->lon) || ($pt->lon <= $this->ne->lon);
    }
  }
  
  /** Union géométrique de $this et $b. Le résultat est toujours une GBox.
   * C'est très approximatif car l'extension aux 2 coins n'implique pas que les points inclus dans b seront dans $this !!!
   */
  function union(BBox $b): self {
    if (get_class($b) <> __CLASS__)
      throw new \Exception("Dans GBox::union(), b est un ".get_class($b)." et PAS un ".__CLASS__);
    if ($this->isEmpty())
      return $b;
    if ($b->isEmpty())
      return $this;
    throw new \Exception("TO BE IMPLEMENTED");
  }
  
  /** Fabrique une GBox à partir des coordonnées d'une MultiLineString définis comme LLPos.
   * @param TLLPos $coords
   */
  static function fromMultiLineString(array $coords): self {
    // Je transforme la LLPos en LLPt
    $llPt = array_map(function(array $lPos) { return Pt::lPos2LPt($lPos); }, $coords);
    // Tranforme la LLPt en LBBox
    $lBBox = array_map(function(array $lPt) { return BNONE->extends($lPt); }, $llPt);
    // Union des bbox de lBBox pour donner le résultat
    $rbbox = GNONE;
    foreach ($lBBox as $bbox)
      $rbbox = $rbbox->union($bbox);
    return $rbbox;
  }
  
  /** Intersection géométrique de $this avec $b. Le résultat est toujours une GBox ! */
  function inters(BBox $b): self {
    if (get_class($b) <> __CLASS__)
      throw new \Exception("Dans GBox::union(), b est un ".get_class($b)." et PAS un ".__CLASS__);
    if (($this->isEmpty()) || ($b->isEmpty()))
      return GNONE;
    throw new \Exception("TO BE IMPLEMENTED");
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
    echo "<a href='?action=testExtends2'>testExtends2</a>\n";
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
    $bbox = GBox::from4Coords([0, 45, 2, 47]);
    
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

    $bbox = GBox::from4Coords([0, 45, 2, 47]);
    $pt1 = new Pt([178, 50]);
    $pt2 = new Pt([-178, 50]);
    $res = $bbox->extends([$pt1,$pt2]);
    echo "$bbox ->extends([$pt1,$pt2]) -> $res\n\n";
    
    $bbox = GBox::from4Coords([0, 45, 2, 47]);
    $pt1 = new Pt([178, 50]);
    $pt2 = new Pt([-178, 50]);
    $pt3 = new Pt([0, 45]);
    $res = $bbox->extends([$pt1,$pt2,$pt3]);
    echo "$bbox ->extends([$pt1,$pt2,$pt3]) -> $res\n\n";
    
    $bbox = GBox::from4Coords([178, 45, -178, 47]);
    $pt = new Pt([1, 46]);
    $res = $bbox->extends([$pt]);
    echo "$bbox ->extends([$pt]) -> $res\n\n";
    break;
  }
  case 'testExtends2': {

    break;
  }
}