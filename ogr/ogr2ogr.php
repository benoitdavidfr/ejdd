<?php
/** ogr2ogr - classe facilitant l'utilisation de ogr2ogr.
 * @package Ogr
 */
namespace Ogr;

const NOTE_SUR_OGR2OGR = [
<<<'EOT'
Usage:
  ogr2ogr [--help] [--long-usage] [--help-general]
                 [-of <output_format>] [-dsco <NAME>=<VALUE>]...
                 [-lco <NAME>=<VALUE>]...
                 [[-append]|[-upsert]|[-overwrite]]
                 [-update] [-sql <statement>|@<filename>] [-dialect <dialect>]
                 [-spat <xmin> <ymin> <xmax> <ymax>]
                 [-where <restricted_where>|@<filename>] [-select <field_list>]
                 [-nln <name>] [-nlt <type>]... [-s_srs <srs_def>]
                 [[-a_srs <srs_def>]|[-t_srs <srs_def>]]
                 <dst_dataset_name> <src_dataset_name> [<layer_name>]...

Au 25/9/2025: GDAL 3.6.2, released 2023/01/02

Les principales options de ogr2ogr:
  -of <format_name>
    format de sortie, principaux formats: GeoJSON
  -lco <NAME>=<VALUE>
    Layer creation option (format specific)
  -t_srs <srs_def>
    Reproject/transform to this SRS on output, and assign it as output SRS.
    Les codes EPSG peuvent être utilisés.
  -doo <NAME>=<VALUE>
    Destination dataset open option (format specific), only valid in -update mode.
  -wrapdateline
    Split geometries crossing the dateline meridian (long. = +/- 180deg)

Les drivers:
  GeoJSON
    Layer creation options
      WRITE_BBOX=[YES​/​NO]
        Defaults to NO. Set to YES to write a bbox property with the bounding box of the geometries at the feature and feature collection level.
      COORDINATE_PRECISION=<integer>
        Maximum number of figures after decimal separator to write in coordinates. Default to 15 for GeoJSON 2008, and 7 for RFC 7946.
        "Smart" truncation will occur to remove trailing zeros.
      ID_FIELD=value
        Name of the source field that must be written as the 'id' member of Feature objects.
  <<<'EOT'
EOT
];

/** ogr2ogr - classe facilitant l'utilisation de ogr2ogr comme cmde Unix. */
class Ogr2ogr {
  /** Convertit un fichier GML d'une FeatureCollection en une FeatureCollection GeoJSON en utilisant ogr2ogr.
   * Prend en paramètre le chemin du fichier GML.
   * Si un feature a une propriété 'gml_id', sa valeur est utilisée pour définir l'id du feature.
   */
  static function convertGmlToGeoJson(string $srcPath): string {
    //echo "ConvertGmlToGeoJson::convert(path=$srcPath)<br>\n";
  
    $destPath = substr($srcPath, 0, -4).'.json';
    // Si le fichier GML existe déjà alors le fichier existant est utilisé et la conversion n'est pas réalisée
    if (!is_file($destPath)) {
      $options = "-lco WRITE_BBOX=YES"
                ." -lco COORDINATE_PRECISION=5"  // résolution 1m
                ." -lco ID_FIELD=gml_id"; // nom du champ à utiliser pour l'id du Feature GeoJSON
      $cmde = "ogr2ogr -f 'GeoJSON' $options $destPath $srcPath";
      //echo "$cmde<br>\n";
      $ret = exec($cmde, $output, $result_code);
      if ($result_code <> 0) {
        echo '$ret='; var_dump($ret);
        echo "result_code=$result_code<br>\n";
        echo '<pre>$output'; print_r($output); echo "</pre>\n";
      }
    }
    $json = file_get_contents($destPath);
    return $json;
  }
};
