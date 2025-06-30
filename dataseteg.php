<?php
/** Définit une classe implémentant un JdD trivial */

require_once 'dataset.inc.php';

/** Exemple trivial de JdD */
class DatasetEg extends Dataset {
  const TITLE = "Exemple de jeu de données trivial utilisé pour tester les scripts";
  const DESCRIPTION = "Ce jeu de données trivial est utilisé pour tester les scripts";
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
      'tableEg'=> [
        'title'=> "Exemple de table",
        'description'=> "Un exemple très simple de table.",
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
      'dictEg'=> [
        'title'=> "Exemple de dictionnaire",
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
      'tableOneOf'=> [
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
      'listOfTuples'=> [
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
      'listOfValues'=> [
        'title'=> "Exemple de liste de valeurs",
        'description'=> "Un exemple très simple de liste de valeurs.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'string',
        ],
      ],
    ],
  ];
  const SECTIONS = [
    'tableEg'=> [
      'ABC'=> [
        'champ1'=> "champ1 pour ABC",
        'champ2'=> "champ2 pour ABC",
      ],
    ],
    'dictEg'=> [
      'ABC'=> "Valeur pour ABC",
      'CDE'=> 123, // Violation: Integer value found, but a string is required
    ],
    'tableOneOf'=> [
      'ABC'=> [
        'champ1'=> "champ1 pour ABC",
        'champ2'=> "champ2 pour ABC",
      ],
      'CDE'=> [
        'champ3'=> "champ3 pour CDE",
        'champ4'=> "champ4 pour CDE",
      ],
    ],
    'listOfTuples'=> [
      [
        'champ1'=> "première valeur pour le champ 1",
        'champ2'=> "champ2 pour le 1er tuple",
      ],
      ['champ1'=> "seconde valeur pour le champ 1"], // Violation: The property champ2 is required
    ],
    'listOfValues'=> [
      "première valeur",
      "seconde valeur",
    ],
  ];
  
  function __construct() { parent::__construct(self::TITLE, self::DESCRIPTION, self::JSON_SCHEMA); }
  
  function getTuples(string $section, mixed $filtre=null): Generator {
    foreach (self::SECTIONS[$section] as $key => $tuple) {
      yield $key => $tuple;
    }
  }
  
  function getOneTupleByKey(string $section, string|number $key): array|string {
    return self::SECTIONS[$section][$key];
  }
};

if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 

echo "Rien à faire pour construire le JdD<br>\n";