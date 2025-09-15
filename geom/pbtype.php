<?php
/**
 * Ce fichier trace la solution proposée par ChatGPT pour supprimer l'erreur de type sur from4Coords().
 * Définir BBox::from4Coords() avec un new static permet lors de l'appel de GBox::from4Coords() de créer un GBox ss dupliquer le code.
 * Cependant, par défaut PhpStan ne comprend pas ce code et génère une erreur sur le type retourné par GBox::from4Coords().
 * Pour éviter cette erreur, il faut rajouter le tag '@phpstan-consistent-constructor' dans la classe BBox.
 * Je garde ce code pour tracer cette solution proposée par ChatGPT.
 *
 * @package BBox\PbType
 */
namespace BBox\PbType;

/** Un Point en coord. géo. (lon,lat) ou cartésiennes (x,y). */
class Pt {
  /** Nombre de chiffres significatifs à l'affichage. */
  public const PRECISON = 2;

  /** longitude / x */
  public readonly float $lon;
  /** latitude / y */
  public readonly float $lat;

  /**
   * @param array{0:float,1:float} $pos
   */
  public function __construct(array $pos) {
    $this->lon = $pos[0];
    $this->lat = $pos[1];
  }
}

/**
 * Voir la doc du fichier.
 * @phpstan-consistent-constructor
 */
class BBox {
  /** Coin SW comme Pt */
  public readonly ?Pt $sw;
  /** Coin NE comme Pt */
  public readonly ?Pt $ne;

  /**
   * @param array{0:float,1:float}|null $sw
   * @param array{0:float,1:float}|null $ne
   */
  public function __construct(?array $sw, ?array $ne) {
    $this->sw = $sw ? new Pt($sw) : null;
    $this->ne = $ne ? new Pt($ne) : null;
  }

  /**
   * Fabrique un BBox (ou sous-classe) à partir de 4 coordonnées
   * [lonWest, latSouth, lonEast, latNorth].
   *
   * @param list<float|numeric-string> $coords  Doit contenir exactement 4 valeurs.
   * @return static
   */
  public static function from4Coords(array $coords): static {
    // (facultatif) sécuriser la taille
    if (\count($coords) !== 4) {
      throw new \InvalidArgumentException('from4Coords attend exactement 4 valeurs.');
    }
    return new static(
      [\floatval($coords[0]), \floatval($coords[1])],
      [\floatval($coords[2]), \floatval($coords[3])]
    );
  }
}

/** Voir la doc du fichier. */
class GBox extends BBox {
  /**
   * @param array{0:float,1:float}|null $sw
   * @param array{0:float,1:float}|null $ne
   */
  public function __construct(?array $sw, ?array $ne) {
    parent::__construct($sw, $ne);
  }
}

/** Voir la doc du fichier.
 * Exemple de méthode imposant un GBox et appelé avec GBox::from4Coords()
 */
class XXX {
  public static function xxx(GBox $gbox): void {
    // ...
  }
}

// OK pour PHPStan : GBox::from4Coords() est inféré comme GBox
XXX::xxx(GBox::from4Coords([0, 1, 2, 3]));
