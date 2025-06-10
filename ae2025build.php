<?php
/** Création des fichiers GeoJSON d'AE2025 à partir des fichier SHP.
 * A exécuter en CLI.
 */
define('SHP_DIR', '../data/aecog2025/ADMIN-EXPRESS-COG-CARTO-PE_3-2__SHP_WGS84G_FRA_2025-04-07/ADMIN-EXPRESS-COG-CARTO-PE/1_DONNEES_LIVRAISON_2025-04-00317/ADECOGPE_3-2_SHP_WGS84G_FRA-ED2025-04-07/');

$shpdir = dir(SHP_DIR);
while (false !== ($entry = $shpdir->read())) {
  if (in_array($entry, ['.','..']))
    continue;
  if (!preg_match('!\.shp$!', $entry))
    continue;
  echo $entry."\n";
  $dest = substr($entry, 0, strlen($entry)-3).'geojson';
  $dest = strToLower($dest);
  $src = SHP_DIR.$entry;
  $cmde = "ogr2ogr -f 'GeoJSON' ae2025/$dest $src";
  echo "$cmde\n";
  $ret = exec($cmde, $output, $result_code);
  if ($result_code <> 0) {
    echo '$ret='; var_dump($ret);
    echo "result_code=$result_code\n";
    echo '$output'; print_r($output);
  }
}
$shpdir->close();
