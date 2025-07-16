<?php
/** Immlémentation d'une jointure entre JdD/sections générant une nouvelle section de JdD.
 * La manière la plus simple d'effectuer une jointure en Php est d'appeller Dataset::get()
 * avec un nom correspondant au motif d'un nom de jointure.
 */
ini_set('memory_limit', '10G');

define('A_FAIRE_JOIN', [
<<<'EOT'
- ajouter un index pour accélérer la jointure
  - en implémentant getTuplesOnValue() en conséquence sur les JdD sur lesquels c'est pertinent
- compléter le schéma à partir des schémas des sections jointes
- ajouter le filtre skip
- ajouter le filtre predicate
- mieux gérer l'analyse du nom poour permettre des imbrications d'opérations ensemblistes
- définir une opération qui applique une fonction à chaque n-uplet
EOT
]
);

require_once 'dataset.inc.php';

/** Une jointure entre JdD/sections est une novelle section de JdD. */
class Join extends Dataset {
  const NAME_PATTERN = '!^(inner-join|left-join|diff-join)\(([^/]+)/([^/]+)/([^ ]+) X ([^/]+)/([^/]+)/([^)]+)\)$!';
  
  /** Décompose le nom du JdD dans les différents paramètres.
   * Par convention, le nom du jeu de données est de la forme:
   *  "{type}({dataset1}/{section1}/{field1} X {dataset2}/{section2}/{field2})"
   * où {type} est:
   *  - 'inner-join': seuls les n-uplets ayant une correspondance dans les 2 sections sont retournés
   *  - 'left-join': tous les n-uplets de la 1ère section sont retournés avec s'ils existent ceux de la 2nd en correspondance
   *  - 'diff-join': ne sont retournés que les n-uplets de la 1ère section n'ayant pas de correspondance dans le 2nd
   * @return array<string,mixed>
   */
  static function params(string $name): array {
    if (!preg_match(self::NAME_PATTERN, $name, $matches))
      throw new Exception("Erreur dans le nom '$name' qui n'est pas conforme au motif défini");
    return [
      'type'=> $matches[1],
      'datasets'=> [
        1=> $matches[2],
        2=> $matches[5],
      ],
      'sections'=> [
        1=> $matches[3],
        2=> $matches[6],
      ],
      'fields'=> [
        1=> $matches[4],
        2=> $matches[7],
      ],
    ];
  }
  
  function __construct(string $name) {
    $p = self::params($name);
    $type = $p['type'];
    $datasets = $p['datasets'];
    $sections = $p['sections'];
    $fields = $p['fields'];
    $title = "Jointure entre $datasets[1].$sections[1].$fields[1] et $datasets[2].$sections[2]. $fields[2]";
    $descr = "Jointure ($type) entre $datasets[1].$sections[1] (s1) et $datasets[2].$sections[2] (s2) "
      ."sur s1.$fields[1]=s2.$fields[2]";
    parent::__construct(
      $name,
      $title,
      $descr,
      [
        '$schema'=> 'http://json-schema.org/draft-07/schema#',
        'properties'=> [
          'join'=> [
            'title'=> $title,
            'description'=> $descr,
            'type'=> 'array',
            'items'=> [],
          ]
        ],
      ]
    );
    
  }
  
  function getTuples(string $section, array $filters=[]): Generator {
    $p = self::params($this->name);
    $ds1 = Dataset::get($p['datasets'][1]);
    $ds2 = Dataset::get($p['datasets'][2]);
    foreach ($ds1->getTuples($p['sections'][1]) as $tuple1) {
      $tuples2 = $ds2->getTuplesOnValue($p['sections'][2], $p['fields'][2], $tuple1[$p['fields'][1]]);
      $tuple = [];
      if ($p['type'] <> 'diff-join') {
        foreach ($tuple1 as $k => $v)
          $tuple["s1.$k"] = $v;
      }
      if (!$tuples2) { // $tuple1 n'a PAS de correspondance dans la 2nd section
        if ($p['type'] == 'left-join')
          yield $tuple;
        elseif ($p['type'] == 'diff-join')
          yield $tuple1;
      }
      else { // $tuple1 A de correspondance dans la 2nd section
        if (in_array($p['type'], ['left-join', 'inner-join'])) {
          foreach ($tuples2 as $tuple2) {
            foreach ($tuple2 as $k => $v)
              $tuple["s2.$k"] = $v;
            yield $tuple;
          }
        }
      }
    }
  }

  /** Construit interactivement les paramètres de la jointure. */
  static function main(): void {
    switch ($action = $_GET['action'] ?? null) {
      case null: {
        if (!isset($_GET['dataset1'])) {
          echo "<h3>Choix des datasets</h3>\n";
          foreach (array_keys(Dataset::REGISTRE) as $dsName) {
            $datasets[$dsName] = Dataset::get($dsName)->title;
          }
          echo "<table border=1><tr><form>\n",
               "<td>",HtmlForm::select('dataset1', array_merge([''=>'dataset1'], $datasets)),"</td>",
               "<td>",HtmlForm::select('dataset2', array_merge([''=>'dataset2'], $datasets)),"</td>\n",
               "<td><input type='submit' value='ok'></td>\n",
               "</form></tr></table>\n",
          die();
        }
        if (!isset($_GET['section1'])) {
          echo "<h3>Choix des sections</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
            $selects[$i] = HtmlForm::select("section$i", array_keys($ds->sections));
          }
          //print_r($dsSectNames);
          echo "<table border=1><form>\n",
               implode(
                 '',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>sections</th><td>$selects[1]</td><td>$selects[2]</td>",
               "<td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        if (!isset($_GET['field1'])) {
          echo "<h3>Choix des champs</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
            $tuple = [];
            foreach ($ds->getTuples($_GET["section$i"]) as $tuple) { break; }
            $selects[$i] = HtmlForm::select("field$i", array_keys($tuple));
            $tuple = [];
          }
          echo "<table border=1><form>\n",
               implode('',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','section1','section2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>sections</td><td>$_GET[section1]</td><td>$_GET[section2]</td></tr>\n",
               "<tr><td>fields</th><td>$selects[1]</td><td>$selects[2]</td>",
               "<td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        if (!isset($_GET['type'])) {
          echo "<h3>Choix du type de jointure</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
          }
          $select = HtmlForm::select('type', [
            'inner-join'=>"Inner-Join - seuls les n-uplets ayant une correspondance dans les 2 sections sont retournés",
            'left-join'=> "Left-Join - tous les n-uplets de la 1ère section sont retournés avec s'ils existent ceux de la 2nd en correspondance",
            'diff-join'=> "Diff-Join - Ne sont retournés que les n-uplets de la 1ère section n'ayant pas de correspondance dans le 2nd",
          ]);
          echo "<table border=1><form>\n",
               implode(
                 '',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','section1','section2','field1','field2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>sections</td><td>$_GET[section1]</td><td>$_GET[section2]</td></tr>\n",
               "<tr><td>fields</th><td>$_GET[field1]</td><td>$_GET[field2]</td></tr>",
               "<tr><td>type</td><td colspan=2>$select</td><td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }

        $name = "$_GET[type]($_GET[dataset1]/$_GET[section1]/$_GET[field1] X $_GET[dataset2]/$_GET[section2]/$_GET[field2])";
        $join = new Join($name);
        $join->displaySection('join');
        break;
      }
      case 'display': {
        if (!isset($_GET['section']))
          die("Erreur section non défie");
        if (!isset($_GET['key'])) {
          Dataset::get($_GET['dataset'])->displaySection($_GET['section']);
        }
        else {
          echo "<pre>$_GET[key] -> ";
          print_r(Dataset::get($_GET['dataset'])->getOneTupleByKey($_GET['section'], $_GET['key']));
        }
        break;
      }
      default: throw new Exception("Erreur action '$action' non prévue");
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


/** Prend une liste de valeurs et retour un array ayant les mêmes avleurs et des clés indentiques aux valeurs.
 * @param list<string> $values
 * @return array<string,string>
 *
function value2keyValue(array $values): array {
  $result = [];
  foreach ($values as $v)
    $result[$v] = $v;
  return $result;
}*/

Join::main();
