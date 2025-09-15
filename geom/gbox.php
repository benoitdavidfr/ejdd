<?php
/** GBox - Définition d'une algèbre sur les boites englobantes en coord. géo. gérant correctement les géométries chevauchant l'antiméridien.
 *
 * @package BBox
 */
namespace BBox;

require_once __DIR__.'/bbox.php';
require_once __DIR__.'/longint.php';
require_once __DIR__.'/longint2.php';

use Pos\Pos;
#use Pos\BiPos;

/**
 * Une boite englobante en coord. geo. gérant correctement les géométries chevauchant l'antiméridien.
 *
 * Lorsque la GBox ne chevauche pas l'antiméridien (AM), west <= east, c'est la représentation classique.
 * Si west == east alors la GBox est réduite soit à 1 point, soit à segment de méridien.
 * Si west > east alors la GBox chevauche l'AM
 *   et on peut se la représenter comme l'union de 2 boites, la 1ère [west -> +180°] et la 2nd [-180° -> east].
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
 * Par contre la méthode union() qui agrège 2 GBox et la méthode intersection() doivent pouvoir prendre des GBox chevauchant l'AM
 * et produire une GBox chevauchant l'AM.
 *
 * Le calcul de l'union et de l'intersection utilise LongInterval.
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

  /** Retourne l'intervalle2 en longitudes de la GBox. */
  private function longInterval2(): LongInterval2 {
    if (!$this->sw)
      throw new \Exception("Impossible de créer un LongInterval2 sur GNONE");
    return new LongInterval2($this->west(), $this->east());
  }
  
  /** $this inclus $b au sens large, cad que $a->includes($a) est vrai. */
  function includes(BBox $b): bool {
    if (get_class($b) <> __CLASS__)
      throw new \Exception("Dans GBox::includes(), b est un ".get_class($b)." et PAS un ".__CLASS__);
    if (!$this->sw)
      return false;
    if (!$b->sw)
      return true;
    if (($b->south() < $this->south()) || ($b->north() > $this->north()))
      return false;
    return $this->longInterval2()->includes($b->longInterval2());
  }
  
  /** Retourne le centre de la BBox.
   * @return TPos */
  function center(): array {
    if (!$this->sw)
      return [];
    $lon = ($this->sw->lon + $this->ne->lon)/2;
    $lat = ($this->sw->lat + $this->ne->lat)/2;
    if ($this->west() <= $this->east()) // Ne chevauche PAS l'AM
      return [$lon, $lat];
    elseif ($lon > 0)
      return [$lon-180, $lat];
    else
      return [$lon+180, $lat];
  }
  
  /** Taille de la bbox en degrés pour des coords. géo. */
  function sizeInDegree(): float {
    $dLat = $this->north() - $this->south();
    $dLon = $this->east() - $this->west();
    if ($dLon < 0) // le GBox chevauche l'AM
      $dLon += 360;
    // le delta en longitude est multiplé par le cosinus de la moyenne des latitudes
    $dLon *= cos(($this->south() + $this->north()) * pi() / 2 / 180);
    return sqrt($dLon * $dLon + $dLat * $dLat);
  }

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
  function crossesAntimeridian(): bool { return ($this->west() > $this->east()) || (($this->west() == -180) && ($this->east() == +180)); }
  
  /** Agrandit la GBox au plus juste pour qu'elle contienne la liste de points.
   * @param list<Pt> $lpts
   */
  function extends(array $lpts): self {
    if (!$lpts)
      return $this;
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
  
  /** Le Pt $pt est-il inclus dans le BBox ? * /
  function includesPt(Pt $pt): bool {
    if (($pt->lat < $this->sw->lat) || ($pt->lat > $this->ne->lat))
      return false;
    if ($this->sw->lon <= $this->ne->lon) { // la BBox courante ne chevauche pas l'AM
      return ($pt->lon >= $this->sw->lon) && ($pt->lon <= $this->ne->lon);
    }
    else { // $this->sw->lon > $this->ne->lon - la BBox courante chevauche l'AM
      return ($pt->lon >= $this->sw->lon) || ($pt->lon <= $this->ne->lon);
    }
  }*/
  
  /** Retourne l'intervalle en longitudes de la GBox. */
  private function longInterval(): LongInterval {
    if (!$this->sw)
      throw new \Exception("Impossible de créer un LongInterval sur GNONE");
    return new LongInterval($this->west(), $this->east());
  }
  
  /** Union de $this et $b. Le résultat est toujours une GBox.
   */
  function union(BBox $b): self {
    if (get_class($b) <> __CLASS__)
      throw new \Exception("Dans GBox::union(), b est un ".get_class($b)." et PAS un ".__CLASS__);
    
    if (!$this->sw)
      return $b;
    if (!$b->sw)
      return $this;
    
    // union en longitude
    $lon = $this->longInterval()->union($b->longInterval());
    
    // union en latitude
    $south = min($this->south(), $b->south());
    $north = max($this->north(), $b->north());
    
    return self::from4Coords([$lon->west, $south, $lon->east, $north]);
  }
  
  /** Fabrique une GBox à partir des coordonnées d'une MultiLineString définis comme LLPos.
   * @param TLLPos $coords
   */
  static function fromMultiLineString(array $coords): self {
    // Je transforme la LLPos en LLPt
    $llPt = array_map(function(array $lPos) { return Pt::lPos2LPt($lPos); }, $coords);
    // Tranforme la LLPt en LBBox
    $lBBox = array_map(function(array $lPt) { return GNONE->extends($lPt); }, $llPt);
    // Union des bbox de lBBox pour donner le résultat
    $rbbox = GNONE;
    foreach ($lBBox as $bbox)
      $rbbox = $rbbox->union($bbox);
    return $rbbox;
  }
  
  /** Intersection de $this avec $b. Le résultat est toujours une GBox ! */
  function intersection(BBox $b): self {
    if (get_class($b) <> __CLASS__)
      throw new \Exception("Dans GBox::union(), b est un ".get_class($b)." et PAS un ".__CLASS__);
    if (!$this->sw || !$b->sw)
      return GNONE;
    
    // intersection en longitude
    if (!($lon = $this->longInterval()->intersection($b->longInterval())))
      return GNONE;
    
    // intersection en latitude
    $south = max($this->south(), $b->south());
    $north = min($this->north(), $b->north());
    if ($south > $north)
      return GNONE;
    
    return self::from4Coords([$lon->west, $south, $lon->east, $north]);
  }
  
  /** Les 2 GBox s'intersectent-elles ? */
  function intersects(BBox $b): bool { return $this->intersection($b) <> GNONE; }
};

/** Constante pour la GBox correspondant à l'espace vide. */
const GNONE = new GBox(null, null);
/** Constante pour la Terre entière en coords géo. */
const WORLD = new GBox([-180,-90], [180,90]);


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<title>GBox</title><pre>\n";
switch ($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=testFrom4Coords'>testFrom4Coords</a>\n";
    echo "<a href='?action=testPt::deltaLon'>testPt::deltaLon</a>\n";
    echo "<a href='?action=testExtends'>testExtends</a>\n";
    echo "<a href='?action=testExtends2'>testExtends2</a>\n";
    echo "<a href='?action=testIs'>testIs</a>\n";
    break;
  }
  case 'testFrom4Coords': {
    foreach ([
      /*[],
      ['a'=>'b','b'=>'c','c'=>'d','d'=>'e'],
      [0,1,2,'x'],
      [0,'1e-2','2.5','4'],
      [0, 1e-2, 2.5, 4],
      [0,1,2,'4a'],*/
      [0,0,10,10],
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
  case 'testIs': {
    echo "BNONE est-elle une GBox ? ",GBox::is(BNONE)?'oui':'non',"\n";
    echo "GNONE est-elle une GBox ? ",GBox::is(GNONE)?'oui':'non',"\n";
    break;
  }
}