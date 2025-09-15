<?php
/** Test de hiérarchie de classes avec op. binaire.
 * Dans GeoJSON, je veux pouvoir utiliser BBox ou GBox en m'assurant de ne pas mélanger pas les 2 types de traitement
 * 1) Quand GBox hérite de BBox, Php ne permet pas de définir GBox::op(GBox): GBox
 *    donc définir GBox::op(BBox): GBox et éventuellement lancer une exception si le paramètre n'est pas un GBox
 * 2) get_class() est compliqué à utiliser car il intègre l'espace de nom.
 *    donc définition de className()
 * 3) il est préférable d'éviter d'utiliser le noms de la classe dans la classe car en cas de chgt de nom de classe cela ne fonctionne plus
 *    donc utiliser (get_class($b) == __CLASS__)
 *
 * Conclusion du test: définir une classe BBox concrète et simple et une classe GBox plus sophistiquée et héritant de BBox.
 *
 * Avantage:
 *   - Dans GeoJSON, dans le use je peux choir d'utiliser soit le type BBox, soit GBox.
 *   - je garantis que je ne mélange pas les traitements BBox et GBox
 * @package BBox\TBox
 */
namespace BBox\TBox;

/** Voir la doc du fichier.
 * Classe BBox concrète et simple + 2 sous-classes spécialisées GBox et EBox.
 * Je décide que l'opération est interdite entre 2 objets de classes différentes.
 */
class BBox {
  /** Retourne le nom de la classe sans l'espace de noms. */
  function className(): string { $class = explode('\\', __CLASS__); return end($class); }

  function op(self $b): self {
    echo "BBox::op(",get_class($b),")\n";
    return new self;
  }
};

/** Voir la doc du fichier. */
class GBox extends BBox {
  function op(BBox $b): self {
    echo "Dans GBox::op(): get_class()=",get_class($b),"\n";
    echo "Dans GBox::op(): className=",$b->className(),"\n";
    echo "Dans GBox::op(): __CLASS__=",__CLASS__,"\n";
    echo "Dans GBox::op(): get_class()==__CLASS__: ", (get_class($b) == __CLASS__)?'vrai':'faux',"\n";
    
    if (get_class($b) <> __CLASS__)
      throw new \Exception("Dans GBox::op(), b est un ".get_class($b)." et PAS un ".__CLASS__);
    
    return new self;
  }
};

/** Voir la doc du fichier. */
class EBox extends BBox {
  function op(BBox $b): self {
    return new self;
  }
};

// Cas OK
$gbox = new GBox;
$gbox->op(new GBox);

// Erreur
$gbox->op(new BBox);
