<?php
/** 2ème version d'intervalle de longitude, fondée sur la création d'un intervalle ayant un esat > 180°
 *
 * @package BBox
 */
namespace BBox;

/** 2ème version d'intervalle de longitude fondée sur la création d'un intervalle ayant un east évt. > 180°.
 * Lorsque l'intervalle chevauche l'AM, $east > 180°
 */
class LongInterval2 {
  readonly float $west;
  readonly float $east;
  
  /** Création à partir de $west et $east respectant les contraites de GBox. */
  function __construct(float $west, float $east) {
    $this->west = $west;
    if ($east < $west)
      $this->east = $east + 360;
    else
      $this->east = $east;
  }
  
  function __toString() {
    if ($this->east < 180)
      return sprintf('[%.0f,%.0f]', $this->west, $this->east);
    else
      return sprintf('[-180,%.0f]u[%.0f,180]', $this->east-360, $this->west);
  }
  
  function shift360(): self { return new self($this->west+360, $this->east+360); }
  
  /** L'intervalle $this inclut'il $b ? */
  function includes(self $b): bool {
    if (($b->west > $this->west) && ($b->east < $this->east)) // cas 1.4, 2.3 2.4
      return true;
    $b = $b->shift360();
    if (($b->west > $this->west) && ($b->east < $this->east)) // cas 1.4, 2.3 2.4
      return true;
    return false;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


$schema = <<<'EOT'
Les différents cas pour $this et $b:
------ -180 +----------------------------- 0 -----------------------------+ +180 --
$this1               w[--------------------------------]e
$b1           [--]         
$b2           [---------------]
$b3           [----------------------------------------------]
$b4                       [-----------------------]
$b5                       [-----------------------------------------]
$b6                                                       [---------]

$this2      |-------]e                                        w[----------|
$b1           [--]
$b2           [-----------]
$b3                                                              [---]
$b4         |--]                                                    [-----|
EOT;

class LongInterval2Test {
  /** @return array<string,mixed> */
  static function EXAMPLES() {
    return  [
      "b avant this, aucun CA (1.1)"=> [
        'a'=> new LongInterval2(-90, 90),
        'b'=> new LongInterval2(-150, -130),
        'exp'=> false,
      ],
      "1.4"=> [
        'a'=> new LongInterval2(-90, 90),
        'b'=> new LongInterval2(-60, 50),
        'exp'=> true,
      ],
      "2.1"=> [
        'a'=> new LongInterval2(90, -90),
        'b'=> new LongInterval2(-170, -100),
        'exp'=> true,
      ],
      "2.4"=> [
        'a'=> new LongInterval2(90, -90),
        'b'=> new LongInterval2(150, -150),
        'exp'=> true,
      ],
    ];
  }
  
  static function main(string $schema): void {
    echo "<title>LongInterval2</title><h1>LongInterval2</h1><pre>\n";
    echo "$schema\n\n";
    
    foreach (self::EXAMPLES() as $t => $e) {
      echo "<b>$t</b>\n";
      echo $e['a'],' includes ',$e['b'],' -> ',$e['a']->includes($e['b'])?'vrai':'faux', ' / ',$e['exp']?'vrai':'faux',"\n\n";
    }
  }
};
LongInterval2Test::main($schema);
