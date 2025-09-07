<?php
/** Définition d'une algèbre sur les rectangles englobants y.c. les points.
 *  opérations
 *    - a * b est l'intersection entre a et b qui est un rect, un point ou 0
 *    - a * b = NONE <=> intersection vide
 *    - a + b = plus petit rectangle contenant a et b (union)
 *  conditions
 *    - a >= b <=> a inclut b
 *    - a > b <=> a inclut strictement b
 *    - a = b <=> a identique à b
 *
 * Je considère qu'un point s'inclut lui-même.
 * Sur l'espace vide (NONE):
 *   - a * NONE = NONE
 *   - NONE >= NONE vrai
 *   - NONE > NONE faux
 *   - a >= NONE vrai
 *   - a > NONE ssi a<>NONE
 *
 * Cette implémentation définit une seule classe BBox dans laquelle l'info est évent. dégénérée,
 * ce qui permet un code plus simple et plus compact.
 *
 * Cette implémentation intègre des fonctionnalités nécessaires à geojson.inc.php sans connaitre les classes GeoJSON
 * en utilisant les types Pos, LPos et LLPos.
 * La classe Pt ne semble pas indispensable et pourrait être remplacée par des fonctions sur Pos.
 *
 * @package BBox
 */
namespace BBox;

require_once __DIR__.'/pos.inc.php';

use Pos\Pos;

/** Un Point en coord. géo. (degrés lon,lat). Classe interne à BBox. */
class Pt {
  /** Nombre de chiffres significatifs à l'affichage. */
  const PRECISON = 2;
  readonly float $x;
  readonly float $y;
  
  /** @param TPos $pos. */
  function __construct(array $pos) {
    if (!Pos::is($pos))
      throw new \Exception("Un Pt se fabrique à partir d'un TPos");
    $this->x = $pos[0];
    $this->y = $pos[1];
  }
    
  /** Un TPos à partir d'un Pt.
   * @return TPos */
  function pos(): array { return [$this->x, $this->y]; }

  /** Construit à partir d'un texte au format {nbre}@{nbre}. */
  static function fromText(string $text): self {
    if (!preg_match('!^([.0-9]+)@([.0-9]+)$!', $text, $matches))
      throw new \Exception("le texte en entrée '$text' ne correspont pas au motif d'un Pt");
    return new self([floatval($matches[1]), floatval($matches[2])]);
  }
  
  /** Affiche ss limiter le nmbre de chiffres significatifs. */
  function __toString2(): string { return "$this->x@$this->y"; }

  /** Affiche en fixant le nmbre de chiffres significatifs. */
  function __toString(): string {
    $format = sprintf('%%.%df@%%.%df', self::PRECISON, self::PRECISON);
    return sprintf($format, $this->x, $this->y);
  }
  
  /** $this est strictement au Sud-Ouest de $b. */
  //function issSW(Pt $b): bool { return ($this->x < $b->x) && ($this->y < $b->y); }
  
  /** $this est largement au Sud-Ouest de $b. */
  function islSW(Pt $b): bool { return ($this->x <= $b->x) && ($this->y <= $b->y); }
  
  /** Le point juste au SW des 2 points. */
  function sw(self $b): self { return new self([min($this->x, $b->x), min($this->y, $b->y)]); }
  
  /** Le point juste au NE des 2 points. */
  function ne(self $b): self { return new self([max($this->x, $b->x), max($this->y, $b->y)]); }
  
  /** Fabrique une liste de Pt à partir d'une TLPos.
   * @param TLPos $lpos - liste de positions
   * @return list<Pt> - retourne une liste de Pt
   */
  static function lPos2LPt(array $lpos): array {
    return array_map(function(array $pos) { return new Pt($pos); }, $lpos);
  }
};

/**
 * Un rectangle englobant en coord. geo. pour le stoker et effectuer diverses opérations et tester des conditions.
 * Les opérations sont l'intersection et l'union entre 2 BBox. Les tests sont ceux d'intersection et d'inclusion.
 * Dans l'intersection entre BBox, elles sont considérées comme topologiquement fermées.
 * Cela veut dire que 2 bbox qui se touchent sur un bord ont comme intersection le segment commun ;
 * et que 2 bbox. qui se touchent dans un coin ont comme intersection le point correspondant à ce coin.
 * Un point, un segment vertical ou horizontal sont représentés comme des BBox dégénérés.
 * L'espace vide est représenté par un BBox particulier défini comme une constante,
 * permettant ainsi de tester si une intersection est vide ou non.
 * Les BBox munies des opérations est une algèbre, cad que le résultat d'une opération est toujours un BBox.
 *
 * Du point de vue implémentation:
 * Un BBox est défini par ses 2 coins SW et NE avec 2 contraintes d'intégrité:
 *  1) Si 1 des 2 coins est null alors l'autre doit aussi l'être.
 *  2) Si les coins sont non nuls alors le coin SW doit être au SW de coin NE.
 *
 * Un point est représenté par 2 coins identiques. L'espace vide est représenté par 2 points null.
 *
 * Enfin le constructeur prend en paramètres des TPos et non des Pt pour qu'il soit appelable de l'extérieur
 * sans avoir à utiliser la classe Pt. Par contre en interne c'est plus simple d'avoir des Pt que des TPos.
 */
class BBox {
  /** Coin SW */
  readonly ?Pt $sw;
  /** Coin NE */
  readonly ?Pt $ne;
  
  /** Fabrique avec vérification des contraintes d'intégrité.
   @param ?TPos $sw - le coin SW comme TPos
   @param ?TPos $ne - le coin NE comme TPos
   */
  function __construct(?array $sw, ?array $ne) {
    $this->sw = $sw ? new Pt($sw) : null;
    $this->ne = $ne ? new Pt($ne) : null;
    if ((is_null($this->sw) && !is_null($this->ne)) || (!is_null($this->sw) && is_null($this->ne)))
      throw new \Exception("Dans la construction d'une BBox, soit les 2 coins sont null, soit aucun.");
    if ($this->sw && !$this->sw->islSW($this->ne))
      throw new \Exception("Dans la construction d'une BBox, le coin SW doit être au SW du coin NE.");
  }
  
  /** Fabrique un BBox à partir d'un texte au format [{Pt},{Pt}] ou {Pt} ou chaine vide. */
  static function fromText(string $text): self {
    if ($text == '')
      return new self(null, null);
    if (preg_match('!^([.0-9@]+)$!', $text, $matches))
      return new self($pos = Pt::fromText($matches[1])->pos(), $pos);
    elseif (preg_match('!^\[([.0-9@]+),([.0-9@]+)\]$!', $text, $matches))
      return new self(Pt::fromText($matches[1])->pos(), Pt::fromText($matches[2])->pos());
    else
      throw new \Exception("le texte en entrée '$text' ne correspond pas au motif d'une BBox");
  }
  
  /** Fabrique un BBox à partir de 4 coordonnées [xmin, ymin, xmax, ymax].
   * @param (list<float>|list<string>) $coords - liste de 4 coordonnées. */
  static function from4Coords(array $coords): self {
    if (count($coords) <> 4)
      throw new \Exception("Erreur, dans BBox::from4Coords(), coords doit comportar 4 coordonnées, ".count($coords)." fournies");
    return NONE->extends([
      new Pt([floatval($coords[0]), floatval($coords[1])]),
      new Pt([floatval($coords[2]), floatval($coords[3])])
    ]);
  }
  
  /** Génère un array de 4 coordonnées utilisé en GeoJSON.
   * @return array{0: float, 1:float, 2:float, 3:float} */
  function as4Coordinates(): array { return [$this->sw->x, $this->sw->y, $this->ne->x, $this->ne->y]; }
  
  /** Génère un array de 4 coordonnées LatLon utilisé par WFS.
   * @return array{0: float, 1:float, 2:float, 3:float} */
  function as4CoordsLatLon(): array { return [$this->sw->y, $this->sw->x, $this->ne->y, $this->ne->x]; }
  
  /** Fabrique une BBox à partir d'une Pos.
   * @param TPos $pos
   */
  static function fromPos(array $pos): self { return new self($pos, $pos); }
  
  /** Fabrique une BBox à partir d'une LPos.
   * @param TLPos $lpos
   */
  static function fromLPos(array $lpos): self { return NONE->extends(Pt::lPos2LPt($lpos)); }
  
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
  
  function isEmpty(): bool { return is_null($this->sw) || is_null($this->ne); }

  /** Affiche dans le même format que celui de la construction sauf pour l'espace vide qui est affiché par 'NONE'. */
  function __toString(): string {
    if ($this->isEmpty())
      return 'NONE';
    elseif ($this->sw == $this->ne)
      return "$this->sw";
    else
      return "[$this->sw,$this->ne]";
  }
  
  /** Retourne le centre de la BBox.
   * @return TPos */
  function center(): array { return [($this->sw->x + $this->ne->x)/2, ($this->sw->y + $this->ne->y)/2]; }
  
  /** Taille du bbox en degrés. */
  function size(): float {
    $dLat = $this->ne->y - $this->sw->y;
    $dLon = ($this->ne->x - $this->sw->x) * cos(($this->ne->y + $this->sw->y) * pi() / 2 / 180);
    $dist = sqrt($dLat * $dLat + $dLon * $dLon) / sqrt(2);
    return $dist;
  }
  
  /** $this inclus $b au sens large, cad que $a->includes($a) est vrai. */
  function includes(self $b): bool {
    if ($b->isEmpty())
      return true; // l'espace vide est inclus dans tout y.c. lui-même 
    elseif ($this->isEmpty())
      return false; // l'espace vide n'inclut rien sauf lui-même
    else
      return $this->sw->isLSW($b->sw) && $b->ne->islSW($this->ne);
  }

  /** Intersection géométrique de $this avec $b. Le résultat est toujours une BBox ! */
  function inters(self $b): self {
    if (($this == NONE) || ($b == NONE))
      return NONE;
    $sw = $this->sw->ne($b->sw); // le SW du nv bbox est le pt juste au NE des 2 SW
    $ne = $this->ne->sw($b->ne); // le NE du nv bbox est le pt juste au SW des 2 NE
    if ($sw->islSW($ne))         // si le coin SW est au SW du point NE
      return new self($sw->pos(), $ne->pos()); //  alors ils définissent un nouveau BBox
    else                         // sinon
      return NONE;               //  l'intersection est vide
  }
  
  /** Union géométrique de $this et $b. Le résultat est toujours une BBox. */
  function union(self $b): self {
    if ($this->isEmpty())
      return $b;
    if ($b->isEmpty())
      return $this;
    $sw = $this->sw->sw($b->sw); // le SW de l'union est le pt juste au SW des 2 SW
    $ne = $this->ne->ne($b->ne); // le NE de l'union est le pt juste au NE des 2 NE
    return new self($sw->pos(), $ne->pos());
  }
  
  /** Agrandit la BBox au plus juste pour qu'elle contienne la liste de points.
   * @param list<Pt> $lpts
   */
  function extends(array $lpts): self {
    $sw = $this->sw;
    $ne = $this->ne;
    foreach ($lpts as $pt) {
      $sw = $sw ? $sw->sw($pt) : $pt;
      $ne = $ne ? $ne->ne($pt) : $pt;
    }
    return new self($sw->pos(), $ne->pos());
  }
};

/** Constante pour l'espace vide. */
const NONE = new BBox(null, null);


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<title>bbox</title>\n",
     "<h2>Test de BBox</h2><pre>\n";

if (0) { // @phpstan-ignore if.alwaysFalse
  $pt = Pt::fromText('1@4.56789'); echo "pt=$pt\n"; //die();
  
  $pt = Pt::fromText('0@0'); echo "pt=$pt\n";
  echo "$pt==NONE: ", ($pt == NONE) ? "vrai\n" : "faux\n";

  $bbox = BBox::fromText('[0@0,1@1]'); echo "bbox=$bbox\n";

  //print_r(NONE);
  $emptySp2 = new BBox(null, null);
  echo "$emptySp2==NONE: ", ($emptySp2 == NONE) ? "vrai\n" : "faux\n";
  echo "$emptySp2===NONE: ", ($emptySp2 === NONE) ? "vrai\n" : "faux\n";
}
elseif (0) { // @phpstan-ignore elseif.alwaysFalse
  $r0 = BBox::fromText('[0@0,1@1]');
  $r1 = BBox::fromText('[1@1,2@2]');
  $r2 = BBox::fromText('[1@1,3@3]');
  echo "$r0 * $r1 = ",$r0->inters($r1),"\n";
  echo "$r1 * $r2 = ",$r1->inters($r2),"\n";
  
  $r0 = BBox::fromText('[0@0,10@10]');
  $r1 = BBox::fromText('[5@5,15@15]');
  echo "$r0 * $r1 = ",$r0->inters($r1),"\n";
  
  $r0 = BBox::fromText('[0@0,10@10]');
  $r1 = BBox::fromText('[5@15,15@15]');
  echo "$r0 * $r1 = ",$r0->inters($r1),"\n";
}
elseif (0) { // @phpstan-ignore elseif.alwaysFalse
  $lpts = [new Pt([0,0]), new Pt([4,5]), new Pt([-4, -5])];
  echo NONE->extends($lpts);
}
elseif (1) {
  echo "<h3>Test de l'union</h3>\n";
  $r0 = BBox::fromText('[0@0,10@10]');
  $r1 = BBox::fromText('[5@5,15@15]');
  echo "$r0 + $r1 = ",$r0->union($r1),"\n";
}
