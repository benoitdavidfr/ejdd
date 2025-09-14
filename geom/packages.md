# ChatGPT recommande comme package
## Question
Connais-tu des packages Php 8.4 qui permettent des calculs sur des objets GeoJSON, notamment l'intersection et l'union d'objets GeoJSON.

## brick/geo
Tu veux du pur PHP avec vraies ops topo → brick/geo + un engine :

GEOS pour perfs locales (si tu peux installer l’extension).

PostGIS (ou MySQL/MariaDB/SpatiaLite) si tu as déjà une BD spatiale. 

GitHub: https://github.com/brick/geo
### install
    composer require brick/geo
### exemple
    use Brick\Geo\Engine\GeosEngine;
    use Brick\Geo\Io\GeoJsonReader;

    $engine = new GeosEngine();
    $reader = new GeoJsonReader();

    $a = $reader->read($geojsonA);   // Polygon/MultiPolygon/…
    $b = $reader->read($geojsonB);

    $u = $engine->union($a, $b);
    $i = $engine->intersection($a, $b);

## geoPHP
Projet legacy ou besoin ultra-simple → geoPHP (idéalement avec GEOS).
### install
    composer require geophp/geophp
### exemple
    $g1 = geoPHP::load($geojsonA, 'geojson');
    $g2 = geoPHP::load($geojsonB, 'geojson');

    $union = $g1->union($g2);
    $inter = $g1->intersection($g2);

    echo $union->out('geojson');
    echo $inter->out('geojson');
