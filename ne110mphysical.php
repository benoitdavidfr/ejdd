<?php
/** Jeu de données Nartural Earth 1:110m Cultural */
require_once 'naturalearth.inc.php';

define('NE110MP_DESCRIPTION', [
  <<<'EOT'
Natural Earth is a public domain map dataset available at 1:10m, 1:50m, and 1:110 million scales. Featuring tightly integrated vector and raster data, with Natural Earth you can make a variety of visually pleasing, well-crafted maps with cartography or GIS software. (https://www.naturalearthdata.com/)
The Small scale data, 1:110m, is suitable for schematic maps of the world on a postcard or as a small locator globe.

La signification des différentes couches et de leurs attributs n'est pas simple.
EOT
]
);
define ('NE110MP_LISTE_DES_FICHIERS', [
   <<<'EOT'
ne110mphysical/ne_110m_coastline.geojson
ne110mphysical/ne_110m_geographic_lines.geojson

ne110mphysical/ne_110m_graticules_30.geojson
ne110mphysical/ne_110m_graticules_20.geojson
ne110mphysical/ne_110m_graticules_15.geojson
ne110mphysical/ne_110m_graticules_10.geojson
ne110mphysical/ne_110m_graticules_5.geojson
ne110mphysical/ne_110m_graticules_1.geojson

ne110mphysical/ne_110m_geography_marine_polys.geojson
ne110mphysical/ne_110m_geography_regions_elevation_points.geojson
ne110mphysical/ne_110m_geography_regions_points.geojson
ne110mphysical/ne_110m_lakes.geojson
ne110mphysical/ne_110m_geography_regions_polys.geojson
ne110mphysical/ne_110m_land.geojson
ne110mphysical/ne_110m_glaciated_areas.geojson
ne110mphysical/ne_110m_ocean.geojson
ne110mphysical/ne_110m_rivers_lake_centerlines.geojson
ne110mphysical/ne_110m_wgs84_bounding_box.geojson
EOT
]
);
class NE110mPhysical extends NaturalEarth {
  const GEOJSON_DIR = 'ne110mphysical';
  const TITLE = "Natural Earth, Small scale data, 1:110m, physical themes";
  const DESCRIPTION = NE110MP_DESCRIPTION[0];
  const SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma d'NaturalEarth110mCultural",
    'description'=> "NaturalEarth110mCultural",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'region'],
    'additionalProperties'=> false,
    'properties'=> [
      'title'=> ['description'=> "Titre du jeu de données", 'type'=> 'string'],
      'description'=> ['description'=> "Commentaire sur le jeu de données", 'type'=> 'string'],
      '$schema'=> ['description'=> "Schéma JSON du jeu de données", 'type'=> 'object'],
      'ne_110m_coastline'=> [
        'title'=> "Ocean coastline, including major islands. Coastline is matched to land and water polygons",
        'description'=> "",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'propertiesForGeoJSON'=> ['featurecla'],
        ],
      ],
      'ne_110m_wgs84_bounding_box'=> [
        'title'=> "Graticules wgs84_bounding_box",
        'description'=> "",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'propertiesForGeoJSON'=> ['featurecla'],
        ],
      ],
    ],
  ];
 
  function __construct() {
    parent::__construct(self::TITLE, self::DESCRIPTION, self::SCHEMA);
  }
  
  /** L'accès aux sections du JdD.
   * @return array<mixed>
   */
  function getData(string $sname, mixed $filtre=null): array { return parent::negetData(self::GEOJSON_DIR, $sname, $filtre); }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


class Build {
  /** Chemin du répertoire contenant les fichiers SHP */
  const SHP_DIR =  '../data/naturalearth/110m_physical/';
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(): void {
    NaturalEarthBuild::buildGeoJson(self::SHP_DIR, NE110mPhysical::GEOJSON_DIR);
  }
  
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=buildGeoJson'>Produit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        echo "<a href='?action=doc'>Lire la doc</a><br>\n";
        break;
      }
      case 'buildGeoJson': {
        self::buildGeoJson();
        break;
      }
      case 'doc': {
        $docs = [];
        $gjsdir = dir(NE110mPhysical::GEOJSON_DIR);
        while (false !== ($entry = $gjsdir->read())) {
          if (!preg_match('!\.html$!', $entry))
            continue;
          //echo "$entry<br>\n";
          $docs[$entry] = 1;
        }
        $gjsdir->close();
        ksort($docs);
        foreach (array_keys($docs) as $doc)
          echo "<a href='",NE110mPhysical::GEOJSON_DIR,"/$doc'>$doc</a><br>\n";
        break;
      }
    }
  }
};
Build::main();
