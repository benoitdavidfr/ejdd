<?php
/** Génération du jeu de données DeptReg des données des départements et régions 9/6/2025.
 *
 * Ce script permet de produire le jeu de données DeptReg sous la forme du fichier JSON deptreg.json.
 * Il permet aussi de vérifier que le JdD est conforme à son schéma.
 *
 * Ce script illustre la gestion d'une BD en JSON pour Php.
 * Un JdD est défini par:
 *  - 3 MD title, description et $schema,
 *  - un champ par table ou dictionnaire.
 *
 * Une table est un dictionnaire qui associe à une clé un n-uplet, cad un array de la foeme [{key}=> [{col1}=> {val1}, ...]].
 * Un dictionnaire associe à une clé une valeur atomique, cad un array de la foeme [{key}=> {val}].
 */

/** Classe masquée regroupant les éléments pour générer deptRegDataSet() ainsi que le dialogue utilisateurs. */
class DeptRegDataSetGen {
  /** Titre du jeu de données */
  const TITLE = "Jeu de données DeptReg décrivant les départements, les régions et les \"domaines internet des Préfectures\"";
  /** Description du jeu de données */
  const DESCRIPTION = [
    <<<'EOT'
  Ce jeu est composé de 3 tables et un dictionnaire:
   - régions: table des régions de métropole, 
   - départements: table des départements de métropole, chacun référençant la région le contenant,
   - outre-mer: table des 5 DOM/ROM + StP&M,
   - prefdom: dictionnaire des domaines internet des services de l'Etat dans les départements ayant une DDT(M),
     appelé "domaines internet des Préfectures".
  Ce jeu de données est exposé en Php et en JSON.
  Outre son contenu, le jeu de données est documenté par:
   - un titre dans le champ champ title
   - un résumé dans le champ description
   - un schéma JSON, dans le champ $schema, qui décrit notamment le schéma du contenu du jeu de données
EOT
  ];
  /** Schema JSON du jeu de données */
  const SCHEMA_JSON = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma du jeu de données deptreg des départements, régions et domaines internet des préfectures",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'régions', 'départements', 'outre-mer','prefdom'],
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
      'régions'=> [
        'description'=> "Table des régions de métropole avec comme clé les 3 derniers caractères du code ISO 3166-2",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z2][A-Z0][A-Z]$'=> [
            'type'=> 'object',
            'required'=> ['nom','iso'],
            'additionalProperties'=> false,
            'properties'=> [
              'nom'=> [
                'description'=> "nom de la région",
                'type'=> 'string',
              ],
              'iso'=> [
                'description'=> "code ISO 3166-2",
                'type'=> 'string',
                'pattern'=> '^FR-[A-Z0-9]{3}$',
              ],
            ],
          ],
        ],
      ],
      'départements'=> [
        'description'=> "Table des départements de métropole avec comme clé leur code Insee précédé de la lettre 'D'",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^D\d[\dAB]$'=> [
            'type'=> 'object',
            'required'=> ['codeInsee','nom','ancienneRégion','région'],
            'additionalProperties'=> false,
            'properties'=> [
              'codeInsee'=> [
                'description'=> "code Insee du département",
                'type'=> 'string',
                'pattern'=> '^\d[\dAB]$'
              ],
              'nom'=> [
                'description'=> "nom du département",
                'type'=> 'string',
              ],
              'ancienneRégion'=> [
                'description'=> "nom de la région avant 2016",
                'type'=> 'string',
              ],
              'région'=> [
                'description'=> "code région, clé dans régions",
                'type'=> 'string',
                'pattern'=> '^[A-Z20]{3}',
              ],
            ],
          ]
        ],
      ],
      'outre-mer'=> [
        'description'=> "Table des 5 DOM/ROM + Saint-Pierre et Miquelon avec comme clé leur code ISO 3166-1 alpha 3",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z]{3}$'=> [
            'type'=> 'object',
            'required'=> ['codeInsee','nom','alpha2','statut'],
            'additionalProperties'=> false,
            'properties'=> [
              'codeInsee'=> [
                'description'=> "code Insee",
                'type'=> 'integer',
              ],
              'nom'=> [
                'description'=> "nom",
                'type'=> 'string',
              ],
              'alpha2'=> [
                'description'=> "code ISO 3166-1 alpha 2",
                'type'=> 'string',
                'pattern'=> '^[A-Z]{2}$',
              ],
              'alpha3'=> [
                'description'=> "code ISO 3166-1 alpha 3",
                'type'=> 'string',
                'pattern'=> '^[A-Z]{3}$',
              ],
              'statut'=> [
                'description'=> "statut, soit 'DOM/ROM', soit 'COM'",
                'enum'=> ['DOM/ROM', 'COM'],
              ],
            ],
          ],
        ],
      ],
      'prefdom'=> [
        'description'=> "Domaines internet des services de l'Etat dans les départements ayant une DDT(M), avec comme clé leur code INSEE précédé de la lettre 'D', + domaine de la DTAM de StP&M, avec comme clé le code ISO 3166-1 alpha 3 de StP&M. Pour raccourcir on appelle cette liste \"Domaines des Préfectures\"",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z0-9]{3}$'=> [
            'description'=> "le domaine internet",
            'type'=> 'string',
          ],
        ],
      ],
    ],
  ];
  /** Les 5 DOM/ROM + StP&M */
  const OUTREMER = [
    'GLP'=> [
      'codeInsee'=> 971,
      'nom'=> "Guadeloupe",
      'alpha2'=> 'GP',
      'alpha3'=> 'GLP',
      'statut'=> 'DOM/ROM',
    ],
    'GUF'=> [
      'codeInsee'=> 973,
      'nom'=> "Guyane",
      'alpha2'=> 'GF',
      'alpha3'=> 'GUF',
      'statut'=> 'DOM/ROM',
    ],
    'MTQ'=> [
      'codeInsee'=> 972,
      'nom'=> "Martinique",
      'alpha2'=> 'MQ',
      'alpha3'=> 'MTQ',
      'statut'=> 'DOM/ROM',
    ],
    'REU'=> [
      'codeInsee'=> 974,
      'nom'=> "La Réunion",
      'alpha2'=> 'RE',
      'alpha3'=> 'REU',
      'statut'=> 'DOM/ROM',
    ],
    'MYT'=> [
      'codeInsee'=> 976,
      'nom'=> "Mayotte",
      'alpha2'=> 'YT',
      'alpha3'=> 'MYT',
      'statut'=> 'DOM/ROM',
    ],
    'SPM'=> [
      'codeInsee'=> 975,
      'nom'=> "Saint-Pierre-et-Miquelon",
      'alpha2'=> 'PM',
      'alpha3'=> 'SPM',
      'statut'=> 'COM',
    ],
  ];
  /** Les domaines internet des services de l'Etat dans les départements ayant une DDT(M) + domaine de la DTAM de StP&M */
  const PREFDOM = [
    'D01' => 'ain.gouv.fr',
    'D02' => 'aisne.gouv.fr',
    'D03' => 'allier.gouv.fr',
    'D04' => 'alpes-de-haute-provence.gouv.fr',
    'D05' => 'hautes-alpes.gouv.fr',
    'D06' => 'alpes-maritimes.gouv.fr',
    'D07' => 'ardeche.gouv.fr',
    'D08' => 'ardennes.gouv.fr',
    'D09' => 'ariege.gouv.fr',
    'D10' => 'aube.gouv.fr',
    'D11' => 'aude.gouv.fr',
    'D12' => 'aveyron.gouv.fr',
    'D13' => 'bouches-du-rhone.gouv.fr',
    'D14' => 'calvados.gouv.fr',
    'D15' => 'cantal.gouv.fr',
    'D16' => 'charente.gouv.fr',
    'D17' => 'charente-maritime.gouv.fr',
    'D18' => 'cher.gouv.fr',
    'D19' => 'correze.gouv.fr',
    'D2A' => 'corse-du-sud.gouv.fr',
    'D2B' => 'haute-corse.gouv.fr',
    'D21' => 'cote-dor.gouv.fr',
    'D22' => 'cotes-darmor.gouv.fr',
    'D23' => 'creuse.gouv.fr',
    'D24' => 'dordogne.gouv.fr',
    'D25' => 'doubs.gouv.fr',
    'D26' => 'drome.gouv.fr',
    'D27' => 'eure.gouv.fr',
    'D28' => 'eure-et-loir.gouv.fr',
    'D29' => 'finistere.gouv.fr',
    'D30' => 'gard.gouv.fr',
    'D31' => 'haute-garonne.gouv.fr',
    'D32' => 'gers.gouv.fr',
    'D33' => 'gironde.gouv.fr',
    'D34' => 'herault.gouv.fr',
    'D35' => 'ille-et-vilaine.gouv.fr',
    'D36' => 'indre.gouv.fr',
    'D37' => 'indre-et-loire.gouv.fr',
    'D38' => 'isere.gouv.fr',
    'D39' => 'jura.gouv.fr',
    'D40' => 'landes.gouv.fr',
    'D41' => 'loir-et-cher.gouv.fr',
    'D42' => 'loire.gouv.fr',
    'D43' => 'haute-loire.gouv.fr',
    'D44' => 'loire-atlantique.gouv.fr',
    'D45' => 'loiret.gouv.fr',
    'D46' => 'lot.gouv.fr',
    'D47' => 'lot-et-garonne.gouv.fr',
    'D48' => 'lozere.gouv.fr',
    'D49' => 'maine-et-loire.gouv.fr',
    'D50' => 'manche.gouv.fr',
    'D51' => 'marne.gouv.fr',
    'D52' => 'haute-marne.gouv.fr',
    'D53' => 'mayenne.gouv.fr',
    'D54' => 'meurthe-et-moselle.gouv.fr',
    'D55' => 'meuse.gouv.fr',
    'D56' => 'morbihan.gouv.fr',
    'D57' => 'moselle.gouv.fr',
    'D58' => 'nievre.gouv.fr',
    'D59' => 'nord.gouv.fr',
    'D60' => 'oise.gouv.fr',
    'D61' => 'orne.gouv.fr',
    'D62' => 'pas-de-calais.gouv.fr',
    'D63' => 'puy-de-dome.gouv.fr',
    'D64' => 'pyrenees-atlantiques.gouv.fr',
    'D65' => 'hautes-pyrenees.gouv.fr',
    'D66' => 'pyrenees-orientales.gouv.fr',
    'D67' => 'bas-rhin.gouv.fr',
    'D68' => 'haut-rhin.gouv.fr',
    'D69' => 'rhone.gouv.fr',
    'D70' => 'haute-saone.gouv.fr',
    'D71' => 'saone-et-loire.gouv.fr',
    'D72' => 'sarthe.gouv.fr',
    'D73' => 'savoie.gouv.fr',
    'D74' => 'haute-savoie.gouv.fr',
    'D76' => 'seine-maritime.gouv.fr',
    'D77' => 'seine-et-marne.gouv.fr',
    'D78' => 'yvelines.gouv.fr',
    'D79' => 'deux-sevres.gouv.fr',
    'D80' => 'somme.gouv.fr',
    'D81' => 'tarn.gouv.fr',
    'D82' => 'tarn-et-garonne.gouv.fr',
    'D83' => 'var.gouv.fr',
    'D84' => 'vaucluse.gouv.fr',
    'D85' => 'vendee.gouv.fr',
    'D86' => 'vienne.gouv.fr',
    'D87' => 'haute-vienne.gouv.fr',
    'D88' => 'vosges.gouv.fr',
    'D89' => 'yonne.gouv.fr',
    'D90' => 'territoire-de-belfort.gouv.fr',
    'D91' => 'essonne.gouv.fr',
    'D95' => 'val-doise.gouv.fr',
    'SPM' => 'equipement-agriculture.gouv.fr',
  ];
  /** Départements de Paris et sa petite couronne dans lesquels il n'y a pas de DDT */
  const PARIS_ET_PETITE_COURONNE = ['D75','D92','D93','D94'];
  /** Données d'origine pour les régions */
  const DATA_REGS = [
    'ARA'=> "Auvergne-Rhône-Alpes",
    'BFC'=> "Bourgogne-Franche-Comté",
    'BRE'=>	"Bretagne",
    'CVL'=> "Centre-Val de Loire",
    '20R'=> "Corse",
    'GES'=> "Grand Est",
    'HDF'=> "Hauts-de-France",
    'IDF'=> "Île-de-France",
    'NOR'=> "Normandie",
    'NAQ'=> "Nouvelle-Aquitaine",
    'OCC'=> "Occitanie",
    'PDL'=> "Pays de la Loire",
    'PAC'=> "Provence-Alpes-Côte d'Azur",
  ];
  /** Données d'origine à transformer, en partie fausses mais non corrigée, */
  const DATA_DEPTS = [
<<<'EOT'
No
Département
Région administrative
01
Ain
Rhône-Alpes > Auvergne-Rhône-Alpes
02
Aisne
Picardie > Hauts-de-France
03
Allier
Auvergne > Auvergne-Rhône-Alpes
04
Alpes de Haute-Provence
Provence-Alpes-Côte d'Azur
05
Hautes-Alpes
Provence-Alpes-Côte d'Azur
06
Alpes-Maritimes
Provence-Alpes-Côte d'Azur
07
Ardêche
Rhône-Alpes > Auvergne-Rhône-Alpes
08
Ardennes
Champagne-Ardenne > Grand-Est
09
Ariège
Midi-Pyrénées > Occitanie
10
Aube
Champagne-Ardenne > Grand-Est
11
Aude
Languedoc-Roussillon > Occitanie
12
Aveyron
Midi-Pyrénées > Occitanie
13
Bouches-du-Rhône
Provence-Alpes-Côte d'Azur
14
Calvados
Basse-Normandie > Normandie
15
Cantal
Auvergne > Auvergne-Rhône-Alpes
16
Charente
Poitou-Charentes > Aquitaine-Limousin-Poitou-Charentes
17
Charente-Maritime
Poitou-Charentes > Aquitaine-Limousin-Poitou-Charentes
18
Cher
Centre
19
Corrèze
Limousin > Aquitaine-Limousin-Poitou-Charentes
2A
Corse-du-Sud
Corse
2B
Haute-Corse
Corse
21
Côte-d'Or
Bourgogne > Bourgogne-Franche-Comté-Dijon
22
Côtes d'Armor
Bretagne
23
Creuse
Limousin  > Aquitaine-Limousin-Poitou-Charentes
24
Dordogne
Aquitaine > Aquitaine-Limousin-Poitou-Charentes
25
Doubs
Franche-Comté  > Bourgogne-Franche-Comté-Dijon
26
Drôme
Rhône-Alpes > Auvergne-Rhône-Alpes
27
Eure
Haute-Normandie > Normandie
28
Eure-et-Loir
Centre
29
Finistère
Bretagne
30
Gard
Languedoc-Roussillon > Occitanie
31
Haute-Garonne
Midi-Pyrénées > Occitanie
32
Gers
Midi-Pyrénées > Occitanie
33
Gironde
Aquitaine > Aquitaine-Limousin-Poitou-Charentes
34
Hérault
Languedoc-Roussillon > Occitanie
35
Îlle-et-Vilaine
Bretagne
36
Indre
Centre
37
Indre-et-Loire
Centre
38
Isère
Rhône-Alpes > Auvergne-Rhône-Alpes
39
Jura
Franche-Comté  > Bourgogne-Franche-Comté-Dijon
40
Landes
Aquitaine > Aquitaine-Limousin-Poitou-Charentes
41
Loir-et-Cher
Centre
42
Loire
Rhône-Alpes > Auvergne-Rhône-Alpes
43
Haute-Loire
Auvergne > Auvergne-Rhône-Alpes
44
Loire-Atlantique
Pays de la Loire
45
Loiret
Centre
46
Lot
Midi-Pyrénées > Occitanie
47
Lot-et-Garonne
Aquitaine > Aquitaine-Limousin-Poitou-Charentes
48
Lozère
Languedoc-Roussillon > Occitanie
49
Maine-et-Loire
Pays de la Loire
50
Manche
Basse-Normandie > Normandie
51
Marne
Champagne-Ardenne > Grand-Est
52
Haute-Marne
Champagne-Ardenne > Grand-Est
53
Mayenne
Pays de la Loire
54
Meurthe-et-Moselle
Lorraine > Grand-Est
55
Meuse
Lorraine > Grand-Est
56
Morbihan
Bretagne
57
Moselle
Lorraine > Grand-Est
58
Nièvre
Bourgogne  > Bourgogne-Franche-Comté-Dijon
59
Nord
Nord-Pas-de-Calais > Hauts-de-France
60
Oise
Picardie > Hauts-de-France
61
Orne
Basse-Normandie > Normandie
62
Pas-de-Calais
Nord-Pas-de-Calais > Hauts-de-France
63
Puy-de-Dôme
Auvergne > Auvergne-Rhône-Alpes
64
Pyrénées-Atlantiques
Aquitaine > Aquitaine-Limousin-Poitou-Charentes
65
Hautes-Pyrénées
Midi-Pyrénées > Occitanie
66
Pyrénées-Orientales
Languedoc-Roussillon > Occitanie
67
Bas-Rhin
Alsace > Grand-Est
68
Haut-Rhin
Alsace > Grand-Est
69
Rhône
Rhône-Alpes > Auvergne-Rhône-Alpes
70
Haute-Saône
Franche-Comté  > Bourgogne-Franche-Comté-Dijon
71
Saône-et-Loire
Bourgogne  > Bourgogne-Franche-Comté-Dijon
72
Sarthe
Pays de la Loire
73
Savoie
Rhône-Alpes > Auvergne-Rhône-Alpes
74
Haute-Savoie
Rhône-Alpes > Auvergne-Rhône-Alpes
75
Paris
Île-de-France
76
Seine-Maritime
Haute-Normandie > Normandie
77
Seine-et-Marne
Île-de-France
78
Yvelines
Île-de-France
79
Deux-Sèvres
Poitou-Charentes > Aquitaine-Limousin-Poitou-Charentes
80
Somme
Picardie > Hauts-de-France
81
Tarn
Midi-Pyrénées > Occitanie
82
Tarn-et-Garonne
Midi-Pyrénées > Occitanie
83
Var
Provence-Alpes-Côte d'Azur
84
Vaucluse
Provence-Alpes-Côte d'Azur
85
Vendée
Pays de la Loire
86
Vienne
Poitou-Charentes > Aquitaine-Limousin-Poitou-Charentes
87
Haute-Vienne
Limousin > Aquitaine-Limousin-Poitou-Charentes
88
Vosges
Lorraine > Grand-Est
89
Yonne
Bourgogne  > Bourgogne-Franche-Comté-Dijon
90
Territoire-de-Belfort
Franche-Comté  > Bourgogne-Franche-Comté-Dijon
91
Essonne
Île-de-France
92
Hauts-de-Seine
Île-de-France
93
Seine-Saint-Denis
Île-de-France
94
Val-de-Marne
Île-de-France
95
Val-d'Oise
Île-de-France

EOT
  ];

  /** Calcul du code région à partir du libellé de DATA_DEPTS.
   * @param array<string,array<string,string>> $regs */
  static function codeReg(array $regs, string $label): string {
    if (preg_match('!^([^>]+) >..(.*)$!', $label, $matches))
      $nom = $matches[2];
    else
      $nom = $label;
    switch($nom) { // correction d'erreurs 
      case "Aquitaine-Limousin-Poitou-Charentes": {
        $nom = "Nouvelle-Aquitaine";
        break;
      }
      case "Grand-Est": {
        $nom = "Grand Est";
        break;
      }
      case "Centre": {
        $nom = "Centre-Val de Loire";
        break;
      }
      case "Bourgogne-Franche-Comté-Dijon": {
        $nom = "Bourgogne-Franche-Comté";
        break;
      }
    }
    
    foreach ($regs as $creg => $reg) {
      if ($reg['nom'] == $nom)
        return $creg;
    }
    return "--$nom--";
  }

  /** Calcul de nom de l'ancienne région à partir du libellé de DATA_DEPTS */
  static function areg(string $label): string {
    if (preg_match('!^([^>]+) >.(.*)$!', $label, $matches))
      return $matches[1];
    else
      return $label;
  }

  /** Retourne la structure DeptReg construite à partir des données en constantes de la classe pour être conforme au schéma.
   * @return array<string, mixed> */
  static function build(): array {
    { // construction de $regs à partir de DATA_REGS
      $regs = [];
      foreach (self::DATA_REGS as $creg => $nom) {
        $regs[$creg] = [
          'nom'=> $nom,
          'iso'=> "FR-$creg",
        ];
      }
      //echo '$regs = ',var_export($regs),";\n";
    }
  
    { // construction de la partie $depts à partir de DATA_DEPTS
      $dataDepts = explode("\n", self::DATA_DEPTS[0]);
      $depts = [];
      for($i=3; $dataDepts[$i]; $i=$i+3) {
        $codeInsee = $dataDepts[$i];
        $nom = $dataDepts[$i+1];
        $reg = $dataDepts[$i+2];
        $depts["D$codeInsee"] = [
          'codeInsee'=> $codeInsee,
          'nom'=> $nom,
          'ancienneRégion'=> self::areg($reg),
          'région'=> self::codeReg($regs, $reg)
        ];
      }
    }

    return [
      'title'=> self::TITLE,
      'description'=> self::DESCRIPTION[0],
      '$schema'=> self::SCHEMA_JSON,
      'régions'=> $regs,
      'départements'=> $depts,
      'outre-mer'=> self::OUTREMER,
      'prefdom'=> self::PREFDOM,
    ];
  }
  
  /** Fonction principale appelée par le script */
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        //echo "<a href='?action=php'>Génération du code Php de deptreg à recopier dans la première partie du script</a><br>\n";
        echo "<a href='?action=json'>Affiche le JSON du jeu de données</a><br>\n";
        echo "<a href='?action=schema'>Affiche le JSON du schéma du jeu de données</a><br>\n";
        echo "<a href='?action=storeJson'>Enregistre le jeu de données en JSON </a><br>\n";
        echo "<a href='?action=validate'>Valide le JSON par rapport à son schéma</a><br>\n";
        echo "<a href='?action=deptsSsDom'>Départements sans nom de domaine</a><br>\n";
        die();
      }
      case 'json': {
        header('Content-Type: application/json');
        die(json_encode(self::build(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
      case 'schema': {
        header('Content-Type: application/json');
        die(json_encode(self::SCHEMA_JSON, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
      case 'storeJson': {
        file_put_contents(
          'deptreg.json',
          json_encode(self::build(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        die("Ecriture JSON ok<br>\n");
      }
      case 'validate': {
        require_once __DIR__.'/vendor/autoload.php';
        $data = json_decode(file_get_contents('deptreg.json'), false);
        $schema = json_decode(file_get_contents('deptreg.json'), true)['$schema'];
        
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
      case 'deptsSsDom': {
        header('Content-Type: text/plain');
        echo "-- Départements ayant une DDT(M) manquants dans prefdom\n";
        foreach(self::build()['départements'] as $cdept => $dept) {
          if (!isset(self::build()['prefdom'][$cdept]) && !in_array($cdept, self::PARIS_ET_PETITE_COURONNE))
            echo "$dept[nom] ($cdept)\n";
        }
        echo "---\n";
        /*$domcomp = explode("\n", DOMCOMP[0]);
        foreach ($domcomp as $dom) {
          if (!in_array($dom, array_values(deptregSrc()['prefdom'])))
            echo "$dom\n";
        }*/
        die();
      }
    }
  }
};
DeptRegDataSetGen::main();
