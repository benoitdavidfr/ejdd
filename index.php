<?php
/** Ce script définit l'IHM d'utilisation des JdD */
require_once 'dataset.inc.php';

ini_set('memory_limit', '10G');
set_time_limit(5*60);

switch ($_GET['action'] ?? null) {
  case null: {
    if (!isset($_GET['dataset'])) {
      echo "Choix du JdD:<br>\n";
      foreach (Dataset::REGISTRE as $dataset) {
        echo "<a href='?dataset=$dataset'>$dataset</a>.<br>\n";
      }
    }
    else {
      echo "Choix de l'action:<br>\n";
      echo "<a href='",strToLower($_GET['dataset']),".php'>Appli de construction du JdD $_GET[dataset]</a><br>\n";
      echo "<a href='?action=display&dataset=$_GET[dataset]'>Affiche en Html le JdD $_GET[dataset]</a><br>\n";
      echo "<a href='geojson.php/$_GET[dataset]'>Affiche GeoJSON les sections du JdD $_GET[dataset]</a><br>\n";
      echo "<a href='?action=validate&dataset=$_GET[dataset]'>Vérifie la conformité du JdD $_GET[dataset] / son schéma</a><br>\n";
      echo "<a href='?action=json&dataset=$_GET[dataset]'>Affiche le JSON du JdD $_GET[dataset]</a><br>\n";
      /*echo "<a href='?action=proj&file=$_GET[file]'>Exemple d'une projection</a><br>\n";
      echo "<a href='?action=join&file=$_GET[file]'>Exemple d'une jointure</a><br>\n";
      echo "<a href='?action=union&file=$_GET[file]'>Exemple d'une union homogène</a><br>\n";
      echo "<a href='?action=select&file=$_GET[file]'>Exemple d'une sélection</a><br>\n";
      echo "<a href='?action=heteroUnion&file=$_GET[file]'>Exemple d'une union hétérogène</a><br>\n";
      */
    }
    break;
  }
  case 'display': {
    $dataset = Dataset::get($_GET['dataset']);
    if (!isset($_GET['section']))
      $dataset->display();
    else
      $dataset->sections[$_GET['section']]->display($dataset);
    break;
  }
  case 'json': {
    $dataset = Dataset::get($_GET['dataset']);
    header('Content-Type: application/json');
    die(json_encode($dataset->asArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  }
  case 'validate': {
    require_once __DIR__.'/vendor/autoload.php';

    $dataset = Dataset::get($_GET['dataset']);
    if ($dataset->schemaIsValid()) {
      echo "Le schéma du JdD est conforme au méta-schéma JSON Schema et au méta-schéma des JdD.<br>\n";
    }
    else {
      $dataset->displaySchemaErrors();
    }

    if ($dataset->isValid()) {
      echo "Le JdD est conforme à son schéma.<br>\n";
    }
    else {
      $dataset->displayErrors();
    }
    break;
  }
  
  /*case 'proj': { // Exemple de projection
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nom'=>  $dept['nom']
          ];
        },
        $dataset()['départements']
      ),
      "Projection de départements sur codeInsee et nom sans la clé",
      false
    );
    break;
  }
  case 'join': { // Exemple de jointure
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) use ($dataset) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nomDépartement'=>  $dept['nom'],
            'nomRégion'=> $dataset()['régions'][$dept['région']]['nom'],
            'prefdom'=> $dataset()['prefdom']['D'.$dept['codeInsee']] ?? null,
          ];
        },
        $dataset()['départements']
      ),
      "Jointure départements X région X prefdom",
      false
    );
    break;
  }
  case 'union': { // exemple de d'union homogénéisée
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nom'=>  $dept['nom'],
          ];
        },
        array_merge($dataset()['départements'], $dataset()['outre-mer'])
      ),
      "Départements de métropole et d'outre-mer + StP&M",
      false
    );
    break;
  }
  case 'select': { // Exemple d'une sélection 
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) {
          if ($dept['région'] == 'ARA')
            return [
              'codeInsee'=> $dept['codeInsee'],
              'nom'=>  $dept['nom'],
              'région'=>  $dept['région'],
            ];
          else
            return null;
        },
        $dataset()['départements']
      ),
      "Sélection des départements de ARA et projection sur codeInsee, nom et région, sans la clé",
      false
    );
    break;
  }
  case 'heteroUnion': { // Exemple d'union hétérogène
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    //Part::displayTable([], "vide");
    Part::displayTable(
      array_merge($dataset()['départements'], $dataset()['outre-mer']),
      "union(départements, outre-mer) hétérogène",
      true
    );
    break;
  }
  case 'homogenisedUnion': { // Exemple d'union homogénéisée
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_merge(
        array_map(
          function(array $dept) {
            return array_merge($dept,[
              'alpha2'=> $dept['codeInsee'],
              'alpha3'=> "D$dept[codeInsee]",
              'statut'=> "Département de métropole",
            ]);
          },
          $dataset()['départements']
        ),
        array_map(
          function(array $om) {
            return array_merge($om, [
              'ancienneRégion'=> $om['nom'],
              'région'=> $om['alpha3'],
            ]);
          },
          $dataset()['outre-mer']
        )
      ),
      "union(départements, outre-mer) homogénéisée",
      true
    );
    break;
  }
  */
  default: {
    echo "Action $_GET[action] inconnue<br>\n";
    break;
  }
}

/*
echo "<a href='dataset.inc.php'>dataset.inc.php</a><br>\n";
echo "<a href='deptreg.php'>deptreg.php</a><br>\n";
*/