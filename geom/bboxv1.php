<?php
/** VERSION PEFIMEE BBoxV1. Définition d'une algèbre sur les rectangles englobants y.c. les points.
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
 *
 * Pour gérer correctement les BBox à cheval sur l'antiméridien, les BBox sont limités à une largeur en longitude < 180°.
 * En effet, la création d'un BBox avec 2 Pts aux antipodes est ambigüe car 2 BBox peuvent être définis.
 *
 * Dans certains cas, il serait utile de gérer des BBox plus grand, éventuellement écrire une classe BigBBox
 *
 * @package BBoxV1
 */
namespace BBoxV1;

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
    if (!Pos::is($pos)) {
      echo 'pos='; var_dump($pos);
      throw new \Exception("Un Pt se fabrique à partir d'un TPos");
    }
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
  
  /** $this est largement au Sud-Ouest de $b. */
  function islSW(Pt $b): bool {
    $xmin = min($this->x, $b->x);
    $xmax = max($this->x, $b->x);
    if (($xmax - $xmin) > 180) {
     return ($this->y <= $b->y) && ($this->x >= $b->x);
    }
    else {
      return ($this->y <= $b->y) && ($this->x <= $b->x);
    }
  }
  
  /** Le point juste au SW des 2 points ; tient compte de la gestion de l'antiméridien */
  function sw(self $b): self {
    $xmin = min($this->x, $b->x);
    $xmax = max($this->x, $b->x);
    //echo "xmin=$xmin, xmax=$xmax, delta=$delta, 360-delta=",360-$delta,"\n";
    if (($xmax - $xmin) > 180) {
      //echo "delta > 180\n";
      return new self([$xmax, min($this->y, $b->y)]);
    }
    else {
      return new self([$xmin, min($this->y, $b->y)]);
    }
  }
  
  /** Le point juste au NE des 2 points. */
  function ne(self $b): self {
    $xmin = min($this->x, $b->x);
    $xmax = max($this->x, $b->x);
    $delta = $xmax - $xmin;
    //echo "xmin=$xmin, xmax=$xmax, delta=$delta, 360-delta=",360-$delta,"\n";
    if ($delta > 180) {
      //echo "delta > 180\n";
      return new self([$xmin, max($this->y, $b->y)]);
    }
    else {
      return new self([$xmax, max($this->y, $b->y)]);
    }
  }

  /** Distance entre 2 points; calcul et résultat en degrés tenant compte de l'antiméridien.
   * Il pourrait être préférable de calculer une distance en WebMercator.
   */
  function distance(self $b): float {
    $dx = max($this->x, $b->x) - min($this->x, $b->x);
    //echo "dx=$dx\n";
    // le delta en longitude tient compte de l'antiméridien
    if ($dx > 180) {
      $dx -= 360;
      //echo "dx > 180 => dx = $dx\n";
    }
    // le delta en longitude est multiplé par le cosinus de la moyenne des latitudes
    $dx *= cos(($this->y + $b->y) * pi() / 2 / 180);
    $dy = $this->y - $b->y;
    $dist = sqrt($dx * $dx + $dy * $dy);
    return $dist;
  }
  
  /** Milieu entre 2 points tenant compte de l'antiméridien. */
  function midPoint(self $b): self {
    $dx = max($this->x, $b->x) - min($this->x, $b->x);
    $x = ($this->x + $b->x)/2;
    if ($dx > 180) {
      if ($x > 0)
        $x -= 180;
      else
        $x += 180;
    }
    return new self([$x, ($this->y + $b->y)/2]);
  }
  
  /** Fabrique une liste de Pt à partir d'une TLPos.
   * @param TLPos $lpos - liste de positions
   * @return list<Pt> - retourne une liste de Pt
   */
  static function lPos2LPt(array $lpos): array {
    return array_map(function(array $pos) { return new Pt($pos); }, $lpos);
  }
};

/**
 * Un rectangle englobant en coord. geo. pour le stocker, effectuer diverses opérations et tester des conditions.
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
 *  2) Si les coins sont non nuls alors le coin SW doit être au SW du coin NE.
 *
 * Un point est représenté par 2 coins identiques. L'espace vide est représenté par 2 points null.
 *
 * Lorsque le BBox chevauche l'antiméridien (antimeridian), la longitude du coin SW est > 0 et celle du coin NE est < 0.
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
  function center(): array { return $this->sw->midPoint($this->ne)->pos(); }
  
  /** Taille du bbox en degrés. */
  function size(): float { return $this->sw->distance($this->ne); }
  
  /** Le BBox intersecte t'il l'antimérdien ?
   * Lorsque le BBox chevauche l'antiméridien (antimeridian), la longitude du coin SW est > 0 et celle du coin NE est < 0.
   */
  function crossesAntimeridian(): bool { return ($this->sw->x > 0) && ($this->ne->x < 0); }
  
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


/** Test de BBox. */
class BBoxTest {
  static function main(): void {
    echo "<title>bbox</title>\n",
         "<h2>Test de BBox</h2><pre>\n";
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=test1'>test1</a>\n";
        echo "<a href='?action=test2'>test2</a>\n";
        echo "<a href='?action=test3'>test3</a>\n";
        echo "<a href='?action=testUnion'>testUnion</a>\n";
        echo "<a href='?action=usa-rus'>test usa-rus</a>\n";
        echo "<a href='?action=am'>test am</a>\n";
        break;
      }
      case 'test1': {
        $pt = Pt::fromText('1@4.56789'); echo "pt=$pt\n"; //die();
  
        $pt = Pt::fromText('0@0'); echo "pt=$pt\n";
        echo "$pt==NONE: ", ($pt == NONE) ? "vrai\n" : "faux\n";

        $bbox = BBox::fromText('[0@0,1@1]'); echo "bbox=$bbox\n";

        //print_r(NONE);
        $emptySp2 = new BBox(null, null);
        echo "$emptySp2==NONE: ", ($emptySp2 == NONE) ? "vrai\n" : "faux\n";
        echo "$emptySp2===NONE: ", ($emptySp2 === NONE) ? "vrai\n" : "faux\n";
        break;
      }
      case 'test2': {
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
        break;
      }
      case 'test3': {
        $lpts = [new Pt([0,0]), new Pt([4,5]), new Pt([-4, -5])];
        echo NONE->extends($lpts);
        break;
      }
      case 'testUnion': {
        echo "<h3>Test de l'union</h3>\n";
        $r0 = BBox::fromText('[0@0,10@10]');
        $r1 = BBox::fromText('[5@5,15@15]');
        echo "$r0 + $r1 = ",$r0->union($r1),"\n";
        break;
      }
      case 'usa-rus': {
        $json = file_get_contents('geom-eez-usa-rus.json');
        $json = json_decode($json, true);
        $lExtRings = array_map(function(array $llpos) { return $llpos[0]; }, $json['coordinates']);
        $bbox = BBox::fromLLPos($lExtRings);
        echo "bbox=$bbox, size=",$bbox->size(),
             ", centre=",new Pt($bbox->center()),
             ", ",$bbox->crossesAntimeridian() ? 'franchit':'NE franchit PAS'," l'antiméridien\n";
        break;
      }
      case 'am': {
        foreach ([
          "BiPts sur l'AM"=> ['sw'=> new Pt([179,40]), 'ne'=> new Pt([-179,50])],
          "BiPts loin de l'AM"=> ['sw'=> new Pt([40,40]), 'ne'=> new Pt([50,50])],
          "Petit biPts près de l'AM"=> ['sw'=> new Pt([178,40]), 'ne'=> new Pt([179,41])],
          "Grand biPts près de l'AM à l'Est du MG"=> ['sw'=> new Pt([0,40]), 'ne'=> new Pt([179,41])],
          "Grand biPts près de l'AM à l'Ouest du MG"=> ['sw'=> new Pt([-179,40]), 'ne'=> new Pt([0,41])],
          "Grand biPts sur l'AM à l'Est du MG mauvais BBox"=> ['sw'=> new Pt([0,40]), 'ne'=> new Pt([-179,41])],
          "Grand biPts sur l'AM à l'Est du MG"=> ['sw'=> new Pt([2,40]), 'ne'=> new Pt([-179,41])],
        ] as $title => $biPts) {
          echo "</pre><h2>$title</h2><pre>\n";
          $sw = $biPts['sw'];
          $ne = $biPts['ne'];
          $swsw = $sw->sw($ne);
          $swne = $sw->ne($ne);
          echo "sw($sw,$ne)->$swsw, ne($sw,$ne)->$swne\n";
          echo "$sw au SW de $ne -> ",$sw->islSW($ne) ? 'vrai' : 'faux',"\n";
          echo "$ne au SW de $sw -> ",$ne->islSW($sw) ? 'vrai' : 'faux',"\n";
          printf("distance %s -> %s : %.2f\n", $sw, $ne, $sw->distance($ne));
          try {
            $bbox = new BBox($sw->pos(), $ne->pos());
            echo "bbox=$bbox ",$bbox->crossesAntimeridian() ? 'franchit':'NE franchit PAS'," l'antiméridien\n";
          } catch (\Exception $e) {
            echo "Erreur: ",$e->getMessage();
          }
        }
        break;
      }
      default: throw new \Exception("Action $_GET[action] non définie");
    }
  }
};
BBoxTest::main();
