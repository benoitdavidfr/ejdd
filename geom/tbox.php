<?php
/** Test de hiérarchie de classes avec op. binaire.
 * Dans GeoJSON, je veux pouvoir utiliser BBox ou GBox mais en m'assurant que je ne mélange pas les 2 types de traitement
 * 1) Quand GBox hérite de BBox, Php ne permet pas dé définir GBox::op(GBox) -> GBox
 *    -> définir GBox::op(BBox) -> GBox et éventuellement lancer une exception si le paramètre n'est pas un GBox
 * 2) get_class() est compliqué à utiliser car il intègre l'espace de nom.
 *    -> définition de className()
 * 3) il est préférable d'éviter d'utiliser le noms de la classe dans la classe car en cas de chgt de nom de classe cela ne fonctionne plus
 *    -> utiliser (get_class($b) == __CLASS__)
 * Conclusion: Je peux définir une classe BBox concrète et simple et une classe GBox plus sophistiquée et héritant de BBox.
 * Avantage:
 *   - Dans GeoJSON, je peux n'utiliser que type BBox en définissant qqpart un paramètre BBox/GBox pour choir entre les 2 implems.
 *   - je garantis que je ne mélange pas les traitements BBox et GBox
 */
namespace BBox\TBox;

/** Classe BBox concrète et simple + 2 sous-classes spécialisées GBox et EBox.
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
