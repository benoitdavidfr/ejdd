<?php
/** Construction d'un dataset */
ini_set('memory_limit', '1G');

class Ae2025 {
  const TITLE = "AE2025";
  const DESCRIPTION = "AE2025";
  const SCHEMA_JSON = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma du jeu de données deptreg des départements, régions et domaines internet des préfectures",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'region'],
    'properties'=> [
      'title'=> [
        'description'=> "Titre du jeu de données",
        'type'=> 'string',
      ],
      'description'=> [
        'description'=> "Commentaire sur le jeu de données",
        'type'=> 'string',
      ],
      '$schema'=> [
        'description'=> "Schéma JSON du jeu de données",
        'type'=> 'object',
      ],
      'region'=> [
        'description'=> "Table des régions",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'required'=> ['id','nom_m','nom','insee_reg','geometry'],
          'additionalProperties'=> false,
          'properties'=> [
            'id'=> [
              'description'=> "id AE",
              'type'=> 'string',
            ],
            'nom_m'=> [
              'description'=> "nom en majuscules",
              'type'=> 'string',
            ],
            'nom'=> [
              'description'=> "nom",
              'type'=> 'string',
            ],
            'insee_reg'=> [
              'description'=> "code INSEE de la région",
              'type'=> 'string',
            ],
            'geometry'=> [
              'description'=> "Géométrie GeoJSON",
              'type'=> 'object',
              'properties'=> [
                'type'=> [
                  'description'=> "Type de géométrie",
                  'enum'=> ['MultiPolygon','Polygon'],
                ],
                'coordinates'=> [
                  'description' => "Coordonnées",
                  'type'=> 'array',
                ],
              ],
            ],
          ],
        ],
      ],
      'departement'=> [
        'description'=> "Table des départements",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'required'=> ['id','nom_m','nom','insee_dep','insee_reg','geometry'],
          'properties'=> [
            'id'=> [
              'description'=> "id AE",
              'type'=> 'string',
            ],
            'nom_m'=> [
              'description'=> "nom en majuscules",
              'type'=> 'string',
            ],
            'nom'=> [
              'description'=> "nom",
              'type'=> 'string',
            ],
            'insee_dep'=> [
              'description'=> "code INSEE du département",
              'type'=> 'string',
            ],
            'insee_reg'=> [
              'description'=> "code INSEE de la région à laquelle appartient le département",
              'type'=> 'string',
            ],
            'geometry'=> [
              'description'=> "Géométrie GeoJSON",
              'type'=> 'object',
              'properties'=> [
                'type'=> [
                  'description'=> "Type de géométrie",
                  'enum'=> ['MultiPolygon','Polygon'],
                ],
                'coordinates'=> [
                  'description' => "Coordonnées",
                  'type'=> 'array',
                ],
              ],
            ],
          ],
        ],
      ],
      'epci'=> [
        'description'=> "EPCI",
      ],
      'commune'=> [
        'description'=> "Commune",
      ],
    ],
  ];
  
  static function buildPart(string $name): array {
    $geojson = json_decode(file_get_contents("ae2025/$name.geojson"), true);
    $part = array_map(
      function(array $feature): array {
        //print_r($feature);
        $tuple = array_merge(array_change_key_case($feature['properties']), ['geometry'=> $feature['geometry']]);
        //print_r($tuple);
        return $tuple;
      },
      $geojson['features']
    );
    //print_r($part);
    return $part;
  }
  
  static function build(): array {
    return [
      'title'=> self::TITLE,
      'description'=> self::DESCRIPTION,
      '$schema'=> self::SCHEMA_JSON,
      'region'=> self::buildPart('region'),
      'departement'=> self::buildPart('departement'),
      'epci'=> self::buildPart('epci'),
      'commune'=> self::buildPart('commune'),
    ];
  }
  
  static function main(): void {
    switch($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=storeJson'>Enregistre le jeu de données en JSON </a><br>\n";
        echo "<a href='?action=json'>Affiche le JSON du jeu de données</a><br>\n";
        echo "<a href='?action=validate'>Valide le JSON par rapport à son schéma</a><br>\n";
        break;
      }
      case 'storeJson': {
        file_put_contents(
          'ae2025.json',
          json_encode(self::build(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        die("Ecriture JSON ok<br>\n");
      }
      case 'json': {
        header('Content-Type: application/json');
        die(json_encode(self::build(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
      case 'validate': {
        require_once __DIR__.'/vendor/autoload.php';
        $data = json_decode(file_get_contents('ae2025.json'), false);
        $schema = json_decode(file_get_contents('ae2025.json'), true)['$schema'];
        
        // Validate
        $validator = new JsonSchema\Validator;
        $validator->validate($data, $schema);

        if ($validator->isValid()) {
          echo "Le JdD est conforme à son schéma.<br>\n";
        } else {
          echo "<pre>Le JdD n'est pas conforme à son schéma. Violations:<br>\n";
          foreach ($validator->getErrors() as $error) {
            printf("[%s] %s<br>\n", $error['property'], $error['message']);
          }
        }
        break;
      }
      
    }
  }
};
Ae2025::main();
