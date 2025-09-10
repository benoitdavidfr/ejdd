<?php
/** Fonctionnalités de dessin.
 * Drawing est l'interface de dessin.
 * GdDrawing est une classe respectant l'intace ci-dessus implémentant le dessin avec GD.
 * @package Drawing
 */
namespace Drawing;

require_once __DIR__.'/../geom/ebox.php';

use \BBox\EBox;

interface Drawing {
  /** const COLORNAMES - quelques noms utiles de couleurs, voir https://en.wikipedia.org/wiki/Web_colors */
  const COLORNAMES = [
    'DarkOrange'=> 0xFF8C00,
  ];

  /**
   * __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) - initialisation du dessin
   *
   * @param int $width largeur du dessin sur l'écran en nbre de pixels
   * @param int $height hauteur du dessin sur l'écran en nbre de pixels
   * @param EBox $world système de coordonnées utilisateur
   * @param int $bgColor couleur de fond du dessin codé en RGB, ex. 0xFFFFFF
   * @param float $bgOpacity opacité du fond entre 0 (transparent) et 1 (opaque)
  */
  function __construct(int $width, int $height, EBox $world, int $bgColor=0xFFFFFF, float $bgOpacity=1);

  /**
   * polyline(array $lpos, array $style=[]): void - dessine une ligne brisée
   *
   * @param TLPos $lpos liste de positions en coordonnées utilisateur
   * @param array<string, string|int|float> $style style de dessin
  */
  function polyline(array $lpos, array $style=[]): void;

  /**
   * polygon(array $llpos, array $style=[]): void - dessine un polygone
   *
   * @param TLLPos $llpos liste de listes de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polygon(array $llpos, array $style=[]): void;
   
  /**
   * flush(string $format='', bool $noheader=false): void - affiche l'image construite
   *
   * @param string $format format MIME d'affichage
   * @param bool $noheader si vrai alors le header n'est pas transmis
  */
  function flush(string $format='', bool $noheader=false): void;
};

/**
 * class DumbDrawing extends Drawing - classe concrète ne produisant rien, utile pour des vérifications formelles
 */
class DumbDrawing implements Drawing {
  function __construct(int $width, int $height, ?EBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) {}
  /**
   * polyline(array $lpos, array $style=[]): void - dessine une ligne brisée
   *
   * @param TLPos $lpos liste de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polyline(array $lpos, array $style=[]): void {}
  /**
   * polygon(array $llpos, array $style=[]): void - dessine un polygone
   *
   * @param TLLPos $llpos liste de listes de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polygon(array $llpos, array $style=[]): void {}
  function flush(string $format='', bool $noheader=false): void {}
};
