<?php
namespace bbox;
/** Définition d'opérations sur des rectangles englobants et des points.
 *  opérations
 *    - a * b est l'intersection entre a et b qui est un rect, un point ou 0
 *    - a * b = 0 <=> intersection vide
 *    - a + b = plus petit rectangle contenant a et b (union)
 *  conditions
 *    - a >= b <=> a inclut b
 *    - a > b <=> a inclut strictement b
 *    - a = b <=> a identique à b
 *
 * Dans l'intersection entre bbox, on considère que les bbox sont topologiquement fermées.
 * Cela veut dire que 2 bbox qui se touchent sur un bord ont comme intersection le segment commun (qui est considéré comme
 * un bbox) ;
 * et que 2 bbox. qui se touchent dans un coin ont comme intersection le point correspondant à ce coin.
 *
 * Je considère qu'un point s'inclut lui-même.
 * Sur l'espace vide (NONE):
 *   - a * NONE = NONE
 *   - NONE >= NONE vrai
 *   - NONE > NONE faux
 *   - a >= NONE vrai
 *   - a > NONE ssi a<>NONE
 *
 * 2ème implémentation en gardant l'idée de construire une algèbre mais en implémentant une seule classe BBox dans laquelle
 * l'info est évent. dégénérée. Permet un code plus simple et plus compact.
 */
require_once('geojson.inc.php');

/** Un Point en coord. géo. (degrés lon,lat). Classe interne à BBox. */
class Pt {
  /** Nombre de chiffres significatifs à l'affichage. */
  const PRECISON = 2;
  
  function __construct(readonly float $x, readonly float $y) {}
    
  /** Construit à partir d'un texte au format {nbre}@{nbre}. */
  static function fromText(string $text): self {
    if (!preg_match('!^([.0-9]+)@([.0-9]+)$!', $text, $matches))
      throw new \Exception("le texte en entrée '$text' ne correspont pas au motif d'un Pt");
    return new self(floatval($matches[1]), floatval($matches[2]));
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
  function sw(self $b): self { return new self(min($this->x, $b->x), min($this->y, $b->y)); }
  
  /** Le point juste au NE des 2 points. */
  function ne(self $b): self { return new self(max($this->x, $b->x), max($this->y, $b->y)); }
};

/**
 * BBox implémente un rect. englobant en coord. geo. qui peut être dégénéré en un point ou même l'espace vide.
 * Défini par ses 2 coins SW et NE. Un point est représenté par 2 coins identiques.
 * L'espace vide est représenté par 2 points null.
 * Il y a 2 contraintes d'intégrité:
 *  1) Si 1 des 2 coins est null alors l'autre doit aussi l'être.
 *  2) Si les coins sont non nuls alors le coin SW doit être au SW de coin NE.
 */
class BBox {
  /** Construction avec vérification des contraintes d'intégrité. */
  function __construct(readonly ?Pt $sw, readonly ?Pt $ne) {
    if ((is_null($sw) && !is_null($ne)) || (!is_null($sw) && is_null($ne)))
      throw new \Exception("Erreur dans la construction d'une BBox, soit les 2 coins sont null, soit aucun.");
    if ($sw && !$sw->islSW($ne))
      throw new \Exception("Erreur dans la construction d'une BBox, le coin SW doit être au SW du coin NE.");
  }
  
  /** Construit à partir d'un texte au format [{Pt},{Pt}] ou {Pt} ou ''. */
  static function fromText(string $text): self {
    if ($text == '')
      return new self(null, null);
    if (preg_match('!^([.0-9@]+)$!', $text, $matches))
      return new self($pt = Pt::fromText($matches[1]), $pt);
    elseif (preg_match('!^\[([.0-9@]+),([.0-9@]+)\]$!', $text, $matches))
      return new self(Pt::fromText($matches[1]), Pt::fromText($matches[2]));
    else
      throw new \Exception("le texte en entrée '$text' ne correspond pas au motif d'une BBox");
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
  
  /** $this inclus(large) $b. */
  function includes(self $b): bool {
    if ($b->isEmpty())
      return true; // l'espace vide est inclus dans tout y.c. lui-même 
    elseif ($this->isEmpty())
      return false; // l'espace vide n'inclut rien sauf lui-même
    else
      return $this->sw->isLSW($b->sw) && $b->ne->islSW($this->ne);
  }

  /** Intersection géométrique de $this et $b. Le résultat est toujours une BBox ! */
  function inters(self $b): self {
    if ($this->isEmpty() || $b->isEmpty())
      return NONE;
    $sw = $this->sw->ne($b->sw); // le SW du nv bbox est le pt juste au NE des 2 SW
    $ne = $this->ne->sw($b->ne); // le NE du nv bbox est le pt juste au SW des 2 NE
    if ($sw->islSW($ne))         // si le coin SW est au SW du point NE
      return new self($sw, $ne); //  alors ils définissent un nouveau BBox
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
    return new self($sw, $ne);
  }
  
  /** Agrandit une BBox pour contenir la liste de points.
   * @param list<Pt> $lpts
   */
  function extend(array $lpts): self {
    $pt = array_pop($lpts);
    $bbox = (new self($this->sw ? $this->sw->sw($pt) : $pt, $this->ne ? $this->ne->ne($pt) : $pt));
    echo "pt=$pt -> bbox=$bbox\n";
    return $lpts ? $bbox->extend($lpts) : $bbox;
  }

  /** Construction d'un BBox à partir d'un Geometry GeoJSON. */
  //static function fromGeoJsonGeom(\geojson\Geometry $geometry): BBox {}
};
/** Cosntante pour l'espace vide. */
define('NONE', new BBox(null, null));


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


echo "<title>bbox</title>\n<pre>\n";

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
  $lpts = [new Pt(0,0), new Pt(4,5), new Pt(-4, -5)];
  echo NONE->extend($lpts);
}
elseif (1) {
}
