<?php
/** BBox V2 - Algèbre sur les rectangles englobants avec calculs simples en coord. cartésiennes.
 *
 * @package BBox
 */
namespace BBox;

require_once __DIR__.'/pos.inc.php';

use Pos\Pos;
use Pos\BiPos;

/** Un Point en coord. géo. (degrés lon,lat) ou cartésiennes. Classe interne à BBox.
 * Dans cette version, la notion de Pt à l'Ouest d'un autre n'a pas de sens car dépend de quel côté on tourne.
 * Normalement lon appartient à [-180,+180] et lat à [-90,+90] mais pour certains besoins algo. cette contrainte peut ne pas être respectée,
 * notamment sur les longitudes.
 */
class Pt {
  /** Nombre de chiffres significatifs à l'affichage. */
  const PRECISON = 2;
  /** float $lon - longitude pour ces coords géo., x pour des coords cartésiennes. */
  readonly float $lon;
  /** float $lat - latitude pour ces coords géo., y pour des coords cartésiennes. */
  readonly float $lat;
  
  /** @param TPos $pos. */
  function __construct(array $pos) {
    if (!Pos::is($pos)) {
      echo 'pos='; var_dump($pos);
      throw new \Exception("Un Pt est fabriqué à partir d'un TPos");
    }
    $this->lon = $pos[0];
    $this->lat = $pos[1];
  }
    
  /** Restitution d'un TPos à partir d'un Pt.
   * @return TPos */
  function pos(): array { return [$this->lon, $this->lat]; }

  /** Construit un Pt à partir d'un texte au format {nbre}@{nbre}. */
  static function fromText(string $text): self {
    if (!preg_match('!^([.0-9]+)@([.0-9]+)$!', $text, $matches))
      throw new \Exception("le texte en entrée '$text' ne correspont pas au motif d'une position");
    return new self([floatval($matches[1]), floatval($matches[2])]);
  }
  
  /** Affiche ss limiter le nmbre de chiffres significatifs. */
  function __toString2(): string { return "$this->lon@$this->lat"; }

  /** Affiche en fixant le nmbre de chiffres significatifs à PRECISON. */
  function __toString(): string {
    $format = sprintf('%%.%df@%%.%df', self::PRECISON, self::PRECISON);
    return sprintf($format, $this->lon, $this->lat);
  }
  
  /** Teste si les 2 coord de $this sont inférieures ou égales à celles de $b. */ 
  function isLess(self $b): bool { return ($this->lon <= $b->lon) || ($this->lat <= $b->lat); }
  
  /** Le point ayant comme coordonnées les min pour chaque coord. des 2 pts en entrées. */
  function min(self $b): self { return new self([min($this->lon, $b->lon), min($this->lat, $b->lat)]); }
  
  /** Le point ayant comme coordonnées les max pour chaque coord. des 2 pts en entrées. */
  function max(self $b): self { return new self([max($this->lon, $b->lon), max($this->lat, $b->lat)]); }
  
  /** écart absolu en longitude, retourne une valeur entre 0 et 180. */
  static function deltaLon(float $lon1, float $lon2): float {
    $delta = abs($lon2 - $lon1); // lon in [-180 , +180] => delta in [0, +360]
    if ($delta > 180)
      $delta = abs($delta - 360);
    return $delta;
  }
  
  /** Fabrique une liste de Pt à partir d'une TLPos.
   * @param TLPos $lpos - liste de positions
   * @return list<Pt> - retourne une liste de Pt
   */
  static function lPos2LPt(array $lpos): array { return array_map(function(array $pos) { return new Pt($pos); }, $lpos); }
};

/**
 * Un rectangle englobant générique avec calculs simples en coord. cartésiennes.
 *
 * Implémente le calcul de BBox sur des géométries de manière simple en calculant le min et max des coordonnées
 * sans tenir compte de l'antiméridien.
 *
 * @phpstan-consistent-constructor
 */
class BBox {
  /** Coin SW comme Pt */
  readonly ?Pt $sw;
  /** Coin NE comme Pt */
  readonly ?Pt $ne;
  
  /** Fabrique un BBox avec vérification des contraintes d'intégrité.
   @param ?TPos $sw - le coin SW comme TPos
   @param ?TPos $ne - le coin NE comme TPos
   */
  function __construct(?array $sw, ?array $ne) {
    $this->sw = $sw ? new Pt($sw) : null;
    $this->ne = $ne ? new Pt($ne) : null;
    if ((is_null($this->sw) && !is_null($this->ne)) || (!is_null($this->sw) && is_null($this->ne)))
      throw new \Exception("Dans la construction d'une BBox, soit les 2 coins sont null, soit aucun.");
    if ($this->sw && ($this->sw->lat > $this->ne->lat))
      throw new \Exception("Dans la construction d'une BBox, le coin SW doit être au Sud du coin NE.");
  }
  
  /** Retourne vrai ssi la classe de $b est celle utilisée pour l'appel statique ; fonctionne correctement avec GBox. */
  static function is(self $b): bool {
    //echo "get_class=",get_class($b)," , get_called_class()=",get_called_class();
    return get_class($b) == get_called_class();
  }
  
  /** Fabrique une BBox/GBox à partir d'un texte au format [{Pt},{Pt}] ou {Pt} ou chaine vide.
   * @return static
   */
  static function fromText(string $text): self {
    if ($text == '')
      return new static(null, null);
    if (preg_match('!^([.0-9@]+)$!', $text, $matches))
      return new static($pos = Pt::fromText($matches[1])->pos(), $pos);
    elseif (preg_match('!^\[([.0-9@]+),([.0-9@]+)\]$!', $text, $matches))
      return new static(Pt::fromText($matches[1])->pos(), Pt::fromText($matches[2])->pos());
    else
      throw new \Exception("le texte en entrée '$text' ne correspond pas au motif d'une BBox/GBox");
  }
  
  /** Fabrique un BBox/GBox à partir de 4 coordonnées dans l'ordre [lonWest, latSouth, lonEst, latNorth].
   * @param (list<float>|list<string>) $coords - liste de 4 coordonnées.
   * @return static
   */
  static function from4Coords(array $coords): self {
    if (!BiPos::is($coords))
      throw new \Exception("Le paramètre n'est pas une liste de 4 nombres");
    return new static(
      [floatval($coords[0]), floatval($coords[1])],
      [floatval($coords[2]), floatval($coords[3])]
    );
  }

  /** Fabrique une BBox/GBox à partir d'une Pos.
   * @param TPos $pos
   * @return static
   */
  static function fromPos(array $pos): self { return new static($pos, $pos); }

  function west(): float { return $this->sw->lon; }
  function south(): float { return $this->sw->lat; }
  function east(): float { return $this->ne->lon; }
  function north(): float { return $this->ne->lat; }

  /** Affiche dans le même format que celui de la construction sauf pour l'espace vide qui est affiché par 'NONE'. */
  function __toString(): string {
    if ($this == BNONE)
      return 'BBox\NONE';
    elseif ($this->sw == $this->ne)
      return "$this->sw";
    else
      return "[$this->sw,$this->ne]";
  }

  /** Génère un array de 4 coordonnées dans l'ordre [west, south, east, north] utilisé en GeoJSON.
   * @return array{0: float, 1:float, 2:float, 3:float} */
  function as4Coords(): array { return $this->sw ? [$this->west(), $this->south(), $this->east(), $this->north()] : []; }
  
  /** Génère un array de 4 coordonnées LatLon dans l'ordre [south, west, north, east] utilisé par WFS.
   * @return array{0: float, 1:float, 2:float, 3:float} */
  function as4CoordsLatLon(): array { return [$this->south(), $this->west(), $this->north(), $this->east()]; }
  
  /** Teste si le BBox correspond à l'espace vide, fonctionne aussi pour GBox. */
  function isEmpty(): bool { return is_null($this->sw); }
  
  /** $this inclus $b au sens large, cad que $a->includes($a) est vrai. */
  function includes(self $b): bool {
    if ($b->isEmpty())
      return true; // l'espace vide est inclus dans tout y.c. lui-même 
    elseif ($this->isEmpty())
      return false; // l'espace vide n'inclut rien sauf lui-même
    else
      return $this->sw->isLess($b->sw) && $b->ne->isLess($this->ne);
  }
  
  /** Retourne le centre de la BBox.
   * @return TPos */
  function center(): array {
    return $this->isEmpty() ? [] : [($this->west() + $this->east())/2, ($this->south() + $this->north())/2];
  }
  
  /** Taille de la bbox en degrés pour des coords. géo. */
  function sizeInDegree(): float {
    $dLat = $this->north() - $this->south();
    // le delta en longitude est multiplé par le cosinus de la moyenne des latitudes
    $dLon = $this->east() - $this->west() * cos(($this->south() + $this->north()) * pi() / 2 / 180);
    return sqrt($dLon * $dLon + $dLat * $dLat);
  }
  
  /** La BBox intersecte t'elle l'antimérdien ?
   * La BBox chevauche l'antiméridien ssi la longitude min est inférieure à -180 ou la logitude max est supérieure à 180.
   */
  function crossesAntimeridian(): bool { return ($this->sw->lon <= -179.9999) || ($this->ne->lon >= +179.9999); }
  
  /** Agrandit la BBox au plus juste pour qu'elle contienne la liste de points.
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

  /** Fabrique une BBox à partir des coords d'une LineString ne chevauchant pas l'AM définis comme une LPos.
   * @param TLPos $coords
   */
  static function fromLineString(array $coords): self { return BNONE->extends(Pt::lPos2LPt($coords)); }
  
  /** Union géométrique de $this et $b. Le résultat est toujours une BBox. */
  function union(self $b): self {
    if ($this->isEmpty())
      return $b;
    if ($b->isEmpty())
      return $this;
    $sw = $this->sw->min($b->sw); // le SW de l'union est le pt juste au SW des 2 SW
    $ne = $this->ne->max($b->ne); // le NE de l'union est le pt juste au NE des 2 NE
    return new self($sw->pos(), $ne->pos());
  }
  
  /** Fabrique une BBox à partir des coordonnées d'une MultiLineString définis comme LLPos.
   * @param TLLPos $coords
   */
  static function fromMultiLineString(array $coords): self {
    // Je transforme la LLPos en LLPt
    $llPt = array_map(function(array $lPos) { return Pt::lPos2LPt($lPos); }, $coords);
    // Tranforme la LLPt en LBBox
    $lBBox = array_map(function(array $lPt) { return BNONE->extends($lPt); }, $llPt);
    // Union des bbox de lBBox pour donner le résultat
    $rbbox = BNONE;
    foreach ($lBBox as $bbox)
      $rbbox = $rbbox->union($bbox);
    return $rbbox;
  }

  /** Intersection géométrique de $this avec $b. Le résultat est toujours une BBox ! */
  function intersection(self $b): self {
    if (($this->isEmpty()) || ($b->isEmpty()))
      return BNONE;
    $sw = $this->sw->max($b->sw); // le SW du nv bbox est le pt juste au NE des 2 SW
    $ne = $this->ne->min($b->ne); // le NE du nv bbox est le pt juste au SW des 2 NE
    if ($sw->isLess($ne))         // si le coin SW est au SW du point NE
      return new self($sw->pos(), $ne->pos()); //  alors ils définissent un nouveau BBox
    else                         // sinon
      return BNONE;               //  l'intersection est vide
  }
  
  /** Les 2 GBox s'intersectent-elles ? */
  function intersects(BBox $b): bool { return $this->intersection($b) <> BNONE; }
};

/** Constante pour le BBox correspondant à l'espace vide. */
const BNONE = new BBox(null, null);


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<pre>";
switch ($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=testPt::deltaLon'>testPt::deltaLon</a>\n";
    echo "<a href='?action=testFrom4Coords'>testFrom4Coords</a>\n";
    echo "<a href='?action=testIs'>testIs</a>\n";
    break;
  }
  case 'testPt::deltaLon': {
    echo Pt::deltaLon(5, 10),"\n";
    echo Pt::deltaLon(10, 5),"\n";
    echo Pt::deltaLon(178, -178),"\n";
    echo Pt::deltaLon(-178, 178),"\n";
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
        echo BBox::from4Coords($array),"\n";
      } catch (\Exception $e) {
        echo "Exception ",$e->getMessage(),"\n";
      }
    }
    break;
  }
  case 'testIs': {
    echo "BNONE est-elle une BBox ? ",BBox::is(BNONE)?'oui':'non',"\n";
    break;
  }
}