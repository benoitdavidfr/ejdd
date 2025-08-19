<?php
/** inseecog.php.
 * @package Dataset
 */
require_once 'vendor/autoload.php';
require_once 'dataset.inc.php';

use Symfony\Component\Yaml\Yaml;

/** JdD Code officiel géographique au 1er janvier 2025 (Insee) (InseeCog).
 * Chaque section correspond à un fichier csv  dans le répertoire inseecog et chaque tuple correspond à une ligne du fichier.
 * Le schéma est défini dans inseecog.yaml.
 */
class InseeCog extends Dataset {
  function __construct(string $name) {
    $params = Yaml::parseFile(__DIR__.strToLower("/$name.yaml"));
    parent::__construct($name, $params['title'], $params['description'], $params['$schema']);
  }
  
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip', 'predicate']; }

  /** L'accès aux tuples d'une section du JdD par un Generator.
   * @param string $section nom de la section
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return Generator
   */
  function getTuples(string $section, array $filters=[]): Generator {
    $skip = $filters['skip'] ?? 0;
    $predicate = $filters['predicate'] ?? null;
    $file = fopen(__DIR__.'/'.strToLower($this->name)."/$section.csv", 'r');
    $headers = fgetcsv(stream: $file, escape: "\\");
    $nol = 0;
    while ($data = fgetcsv(stream: $file, escape: "\\")) {
      $tuple = [];
      foreach ($headers as $i => $name) {
        $tuple[$name] = $data[$i];
      }
      
      if ($predicate && !$predicate->eval($tuple)) {
        $nol++;
        continue;
      }
      if ($skip-- > 0) {
        $nol++;
        continue;
      }
      
      yield $nol++ => $tuple;
    }
    fclose($file);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


/** Construction d'InseeCog. */
class InseeCogBuild {
  /** Répertoire de stockage des fichiers CSV */
  const FILE_DIR = 'inseecog';
  const SRCE_PATH = '../data/insee-cog2025/cog_ensemble_2025_csv';
  
  /** Produit les fichiers CSV à partir des fichiers CSV de la livraison stockée dans SRCE_DIR */
  static function copyCsvFiles(string $srcePath, string $fileDir): void {
    if (!is_dir($fileDir))
      mkdir($fileDir);
    $srcedir = dir($srcePath);
    while (false !== ($entry = $srcedir->read())) {
      if (preg_match('!\.(csv)$!', $entry)) {
        echo "> $entry<br>\n";
        $cmde = "cp $srcePath/$entry $fileDir/";
        echo "$ $cmde<br>\n";
        $ret = exec($cmde, $output, $result_code);
        if ($result_code <> 0) {
          echo '$ret='; var_dump($ret);
          echo "result_code=$result_code<br>\n";
          echo '<pre>$output'; print_r($output); echo "</pre>\n";
        }
      }
    }
    $srcedir->close();
  }
  
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=build'>Initialiser le JdD à partir des données source</a><br>\n";
        break;
      }
      case 'build': {
        self::copyCsvFiles(self::SRCE_PATH, self::FILE_DIR);
        break;
      }
    }
  }
};
InseeCogBuild::main();
