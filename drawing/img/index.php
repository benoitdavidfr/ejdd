<?php
/** Test de l'utilisation de la saisie d'un point en Html.
 * planisphere: image 2629 X 1642
 * planisphere2: image 1315 X 821
 */

function genRect(int $x, int $y, int $dx, int $dy, string $href, string $alt): string {
  $x2 = $x + $dx;
  $y2 = $y + $dy;
  return "<area shape='rect' coords='$x, $y, $x2, $y2' href='$href' alt='$alt' />\n";
}

function genMap(string $name): string {
  $map = "<map name='$name'>\n";
  for($x = 0; $x < 1300; $x += 100) {
    for ($y=0 ;$y < 820; $y += 100) {
      $map .= genRect($x, $y, 100, 100, "?action=pt&x=$x&y=$y", "image{$x}X{$y}");
    }
  }
  $map .= "</map>\n";
  return $map;
}

switch ($_GET['action'] ?? null) {
  case null: {
    $mapName = 'example-map-1';
    echo genMap($mapName);
    echo "<img src='planisphere2.png' alt='image' usemap='#$mapName' />\n";
    break;
  }
  case 'pt': {
    print_r($_GET);
    break;
  }
}
