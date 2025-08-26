<?php
/** Définit une classe implémentant un JdD trivial.
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/../dataset.inc.php';

/** Exemple trivial de JdD */
class DatasetEg extends Dataset {
  const TITLE = "Exemple de jeu de données trivial utilisé pour tester les scripts";
  const DESCRIPTION = "Ce jeu de données trivial est utilisé pour tester les scripts";
  const DATA = [
    'exDictOfTuple'=> [
      'schema'=> [
        'title'=> "Exemple de DictOfTuple",
        'description'=> "Un exemple très simple de DictOfTuple, cad table classique.",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z2][A-Z0][A-Z]$'=> [
            'type'=> 'object',
            'required'=> ['champ1','champ2'],
            'additionalProperties'=> false,
            'properties'=> [
              'champ1'=> [
                'description'=> "Description du champ1",
                'type'=> 'string',
              ],
              'champ2'=> [
                'description'=> "Description du champ2",
                'type'=> 'string',
              ],
            ],
          ],
        ],
      ],
      'data'=> [
        'ABC'=> [
          'champ1'=> "champ1 pour ABC",
          'champ2'=> "champ2 pour ABC",
        ],
      ],
    ],
    'exDictOfValue'=> [
      'schema'=> [
        'title'=> "Exemple de DictOfValue",
        'description'=> "Exemple de dictionnaire avec une erreur",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z0-9]{3}$'=> [
            'description'=> "La valeur définie par le dictionnaire",
            'type'=> 'string',
          ],
        ],
      ],
      'data'=> [
        'ABC'=> "Valeur pour ABC",
        'CDE'=> 123, // Violation: Integer value found, but a string is required
      ],
    ],
    'tableOneOf'=> [
      'schema'=> [
        'title'=> "Exemple de table avec un type de tuple OneOf",
        'description'=> "Un exemple très simple de table.",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z2][A-Z0][A-Z]$'=> [
            'OneOf'=> [
              [
                'type'=> 'object',
                'required'=> ['champ1','champ2'],
                'additionalProperties'=> false,
                'properties'=> [
                  'champ1'=> [
                    'description'=> "Description du champ1",
                    'type'=> 'string',
                  ],
                  'champ2'=> [
                    'description'=> "Description du champ2",
                    'type'=> 'string',
                  ],
                ],
              ],
              [
                'type'=> 'object',
                'required'=> ['champ3','champ4'],
                'additionalProperties'=> false,
                'properties'=> [
                  'champ3'=> [
                    'description'=> "Description du champ3",
                    'type'=> 'string',
                  ],
                  'champ4'=> [
                    'description'=> "Description du champ4",
                    'type'=> 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'data'=> [
        'ABC'=> [
          'champ1'=> "champ1 pour ABC",
          'champ2'=> "champ2 pour ABC",
        ],
        'CDE'=> [
          'champ3'=> "champ3 pour CDE",
          'champ4'=> "champ4 pour CDE",
        ],
      ],
    ],
    'listOfTuples'=> [
      'schema'=> [
        'title'=> "Exemple de liste de n-uplets",
        'description'=> "Un exemple très simple de liste de n-uplets avec une erreur.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'required'=> ['champ1','champ2'],
          'additionalProperties'=> false,
          'properties'=> [
            'champ1'=> [
              'description'=> "Description du champ1",
              'type'=> 'string',
            ],
            'champ2'=> [
              'description'=> "Description du champ2",
              'type'=> 'string',
            ],
          ],
        ],
      ],
      'data'=> [
        [
          'champ1'=> "première valeur pour le champ 1",
          'champ2'=> "champ2 pour le 1er tuple",
        ],
        ['champ1'=> "seconde valeur pour le champ 1"], // Violation: The property champ2 is required
      ],
    ],
    'listOfValues'=> [
      'schema'=> [
        'title'=> "Exemple de liste de valeurs",
        'description'=> "Un exemple très simple de liste de valeurs.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'string',
        ],
      ],
      'data'=> [
        "première valeur",
        "seconde valeur",
      ],
    ],
  ]; // Examples organisés avec chacun son schéma et ses données
  const JSON_SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma du jeu de données exemple",
    'description'=> "Ce jeu et son schéma permettent de tester les scripts.",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'tableEg', 'dictEg','tableOneOf'],
    'additionalProperties'=> false,
    'properties'=> [
      'title'=> [
        'description'=> "Titre du jeu de données",
        'type'=> 'string',
      ],
      'description'=> [
        'description'=> "Description du jeu de données",
        'type'=> 'string',
      ],
      '$schema'=> [
        'description'=> "Schéma JSON du jeu de données",
        'type'=> 'object',
      ],
    ],
  ];
  
  function __construct(string $name) {
    $schema = self::JSON_SCHEMA;
    foreach (self::DATA as $sname => $example) {
      $schema['properties'][$sname] = $example['schema'];
    }
    //echo '<pre>$schema='; print_r($schema); echo "</pre>\n";
    parent::__construct($name, self::TITLE, self::DESCRIPTION, $schema);
  }
  
  /** L'accès aux items d'une collection du JdD par un Generator.
   * @param string $collection nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $collection, array $filters=[]): \Generator {
    $skip = $filters['skip'] ?? 0;
    foreach (self::DATA[$collection]['data'] as $key => $item) {
      if ($skip-- > 0)
        continue;
      yield $key => $item;
    }
  }
  
  /** Retourne l'item' ou la valeur ayant la clé indiquée de la collection.
   * @return array<mixed>|string|null
   */ 
  function getOneTupleByKey(string $collection, string|int $key): array|string|null {
    return self::DATA[$collection]['data'][$key];
  }
};

if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 

echo "Rien à faire pour construire le JdD<br>\n";