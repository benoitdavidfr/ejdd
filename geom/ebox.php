<?php
/** EBox, BBox en coord. cartésiennes.
 * @package BBox
 */
namespace BBox;

require_once __DIR__.'/bbox.php';

use BBox\BBox;

/** EBox, BBox en coord. cartésiennes.
 */
class EBox extends BBox {
  /** Fabrique un BBox avec vérification des contraintes d'intégrité.
   @param ?TPos $sw - le coin SW comme TPos
   @param ?TPos $ne - le coin NE comme TPos
   */
  function __construct(?array $sw, ?array $ne) { parent::__construct($sw, $ne); }
  
  function south(): float { return $this->sw->lat; }
  function west(): float { return $this->sw->lon; }
  function north(): float { return $this->ne->lat; }
  function east(): float { return $this->ne->lon; }
};