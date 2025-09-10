<?php
/** GBox - Définition d'une algèbre sur les rectangles englobants en coord. géo. avec des calculs tenant compte de l'antiméridiens.
 *
 * @package BBox
 */
namespace BBox;

require_once __DIR__.'/bbox.php';

use Pos\Pos;

/**
 * Un rectangle englobant en coord. geo. pour le stocker, effectuer diverses opérations et tester des conditions.
 * Les opérations sont l'intersection et l'union entre 2 BBox. Les tests sont ceux d'intersection et d'inclusion.
 * Dans l'intersection entre BBox, elles sont considérées comme topologiquement fermées.
 * Cela veut dire que 2 bbox qui se touchent sur un bord ont comme intersection le segment commun ;
 * et que 2 bbox. qui se touchent dans un coin ont comme intersection le point correspondant à ce coin.
 * Un point, un segment vertical ou horizontal sont représentés comme des BBox dégénérés.
 * L'espace vide est représenté par un BBox particulier défini comme une constante nommée NONE,
 * permettant ainsi de tester si une intersection est vide ou non.
 * Les BBox munies des opérations est une algèbre, cad que le résultat d'une opération est toujours un BBox.
 *
 * Du point de vue implémentation:
 * Un BBox est défini par ses 2 coins SW et NE qui sont des Pt avec 2 contraintes d'intégrité:
 *  1) Si 1 des 2 coins est null alors l'autre doit aussi l'être et il s'agit de l'espace vide nommé NONE.
 *  2) Si les coins sont non nuls alors le coin SW doit être au Sud du coin NE.
 *
 * Un point est représenté par 2 coins identiques.
 *
 * La BBox chevauche l'antiméridien (antimeridian) <=> lonWest > lonEst.
 *
 * Les BBox qui couvrent tte la Terre correspondent à une seule représentation définie par convention à [-180@-90, 180@90]
 */
class GBox extends BBox {
  /** Affiche dans le même format que celui de la construction sauf pour l'espace vide qui est affiché par 'NONE'. */
  function __toString(): string {
    if ($this == NONE)
      return 'BBox\NONE';
    if ($this == WORLD)
      return 'BBox\WORLD';
    elseif ($this->sw == $this->ne)
      return "$this->sw";
    else
      return "[$this->sw,$this->ne]";
  }

  /** Génère un array de 4 coordonnées dans l'ordre [lonWest, latSouth, lonEst, latNorth] utilisé en GeoJSON.
   * @return array{0: float, 1:float, 2:float, 3:float} */
  function as4Coords(): array { return [$this->sw->lon, $this->sw->lat, $this->ne->lon, $this->ne->lat]; }
  
  /** Génère un array de 4 coordonnées LatLon dans l'ordre [latSouth, lonWest, latNorth, lonEst] utilisé par WFS.
   * @return array{0: float, 1:float, 2:float, 3:float} */
  function as4CoordsLatLon(): array { return [$this->sw->lat, $this->sw->lon, $this->ne->lat, $this->ne->lon]; }
  
  /** Le BBox intersecte t'il l'antimérdien ?
   * Lorsque le BBox chevauche l'antiméridien (antimeridian), la longitude du coin SW est > 0 et celle du coin NE est < 0.
   */
  function crossesAntimeridian(): bool { return ($this->sw->lon > $this->ne->lon); }
  
  /** Agrandit la BBox pour contenir la liste de points tout en restant la plus petite possible.
   * @param list<Pt> $lpts
   */
  function extends(array $lpts): self {
    if ($this == NONE) {     // Je commence par traiter le cas particulier où $this == NONE
      if (count($lpts) == 0) // Si la liste des points est vide
        return NONE;         // alors le résultat est NONE
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
  static function fromLPos(array $lpos): self { return NONE->extends(Pt::lPos2LPt($lpos)); }
  
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
  function union(self $b): self {
    if ($this == NONE)
      return $b;
    if ($b == NONE)
      return $this;
    if ($this->includesPt($b->sw) && $this->includesPt($b->ne))
      return $this;
    throw new \Exception("TO BE IMPLEMENTED");
  }
  
  /** Fabrique une BBox à partir d'une LLPos.
   * @param TLLPos $llPos
   */
  static function fromLLPos(array $llPos): self {
    // Je transforme la LLPos en LLPt
    $llPt = array_map(function(array $lPos) { return Pt::lPos2LPt($lPos); }, $llPos);
    // Tranforme la LLPt en LBBox
    $lBBox = array_map(function(array $lPt) { return NONE->extends($lPt); }, $llPt);
    // Union des bbox de lBBox pour donner le résultat
    $rbbox = NONE;
    foreach ($lBBox as $bbox)
      $rbbox = $rbbox->union($bbox);
    return $rbbox;
  }
  
};

/** Constante pour la Terre en coords géo. */
const WORLD = new GBox([-180,-90], [180,90]);


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


switch ($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=testPt::deltaLon'>testPt::deltaLon</a><br>\n";
    echo "<a href='?action=testExtends'>testExtends</a><br>\n";
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