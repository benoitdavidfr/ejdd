<?php
/** GdDrawing - Dessin en GD.
 * @package Drawing
 */
namespace Drawing;

require_once __DIR__.'/../geom/ebox.php';
require_once __DIR__.'/drawing.php';
require_once __DIR__.'/../lib/sexcept.inc.php';

use BBox\EBox;

/**
 * Dessin utilisant les primitives GD et définissant un espace de coordonnées utilisateur.
 *
 * Un dessin définit un système de coord. utilisateurs, une taille d'image d'affichage et une couleur de fond.
 * Il définit des méthodes de dessin d'une ligne brisée et d'un polygone.
 * Le système de coordonnées utilisateur correspond à 2 fonctions affines en X et en Y par rappport aux cooordonnées écran.
 * Il est défini par d'une part la taille de l'image et, d'autre part, le EBox des coordonnées utilisateur.
 */
class GdDrawing implements Drawing {
  const ErrorCreate = 'GdDrawing::ErrorCreate';
  const ErrorCreateFromPng = 'GdDrawing::ErrorCreateFromPng';
  const ErrorCopy = 'GdDrawing::ErrorCopy';
  const ErrorColorAllocate = 'GdDrawing::ErrorColorAllocate';
  const ErrorFilledRectangle = 'GdDrawing::ErrorFilledRectangle';
  const ErrorRectangle = 'GdDrawing::ErrorRectangle';
  const ErrorPolyline = 'GdDrawing::ErrorPolyline';
  const ErrorPolygon = 'GdDrawing::ErrorPolygon';
  const ErrorFilledPolygon = 'GdDrawing::ErrorFilledPolygon';
  const ErrorDrawString = 'GdDrawing::ErrorDrawString';
  const ErrorSaveAlpha = 'GdDrawing::ErrorSaveAlpha';
  
  /** @var array<int, int> $colors */
  protected array $colors=[]; // table des couleurs [RGBA => int]
  protected \GdImage $im; // l'image comme resource
  
  /**
   * __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) - initialise
   *
   * @param int $width largeur du dessin sur l'écran en nbre de pixels
   * @param int $height hauteur du dessin sur l'écran en nbre de pixels
   * @param EBox $world système de coordonnées utilisateur
   * @param int $bgColor couleur de fond du dessin codé en RGB, ex. 0xFFFFFF
   * @param float $bgOpacity opacité du fond entre 0 (transparent) et 1 (opaque)
   */
  function __construct(readonly int $width, readonly int $height, readonly EBox $world, int $bgColor=0xFFFFFF, float $bgOpacity=1) {
    //printf("Drawing::__construct(%d, %d, $world, %x, %f)<br>\n", $width, $height, $bgColor, $bgOpacity);
    if (($width <= 0) || ($width > 100000))
      throw new \SExcept("width=$width dans GdDrawing::__construct() incorrect", self::ErrorCreate);
    if (($height <= 0) || ($height > 100000))
      throw new \SExcept("height=$height dans GdDrawing::__construct() incorrect");
    if (($this->world->north() - $this->world->south())==0)
      throw new \SExcept("Erreur north - south == 0 dans GdDrawing::__construct()", self::ErrorCreate);
    //print_r($this);
    if (!($this->im = imagecreatetruecolor($this->width, $this->height)))
      throw new \SExcept("erreur de imagecreatetruecolor($this->width, $this->height)", self::ErrorCreate);
    // remplissage dans la couleur de fond
    if (!imagealphablending($this->im, false))
      throw new \SExcept("erreur de imagealphablending()", self::ErrorCreate);
    $bgcolor = $this->colorallocatealpha($bgColor, $bgOpacity);
    if (!imagefilledrectangle($this->im, 0, 0, $this->width - 1, $this->height - 1, $bgcolor))
      throw new \SExcept("erreur de imagefilledrectangle()", self::ErrorCreate);
    if (!imagealphablending($this->im, true))
      throw new \SExcept("erreur de imagealphablending()", self::ErrorCreate);
  }
  
  /** prend une couleur définie en rgb et un alpha et renvoie une ressource évitant ainsi les duplications. */
  private function colorallocatealpha(int $rgb, float $opacity): int {
    if ($opacity < 0) $opacity = 0;
    if ($opacity > 1) $opacity = 1;
    $alpha = intval((1 - $opacity) * 128);
    $rgba = ($rgb << 8) | ($alpha & 0xFF);
    if (isset($this->colors[$rgba]))
      return $this->colors[$rgba];
    $color = imagecolorallocatealpha(
      $this->im, ($rgba >> 24) & 0xFF, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0x7F);
    if ($color === FALSE)
      throw new \Exception("Erreur imagecolorallocatealpha()");
    $this->colors[$rgba] = $color;
    return $color;
  }
  
  /**
   * proj(array $pos): array - transforme une position en coord. World en une position en coordonnées écran
   *
   * Le retour est un array de 2 entiers.
   * Le dessin de l'Antarctique en WM génère par défaut des erreurs car la proj en WM fournit un y = -INF qui reste flottant
   * après un round() puis génère une erreur de type dans imageline()/imagefilledpolygon()
   * La solution consiste à remplacer les valeurs très grandes ou très petites par un entier très grand/petit.
   * Cette solution ne fonctionne pas bien avec PHP_INT_MAX/PHP_INT_MIN car le remplissage du polygon remplit l'extérieur.
   * Après tests, l'utilisation des valeurs 1000000 et -1000000 donne de bons résultats.
   *
   * @param TPos $pos
   * @return array<int, int>
  */
  function proj(array $pos): array {
    $x = round(($pos[0] - $this->world->west()) / ($this->world->east() - $this->world->west()) * $this->width);
    $y = round(($this->world->north() - $pos[1]) / ($this->world->north() - $this->world->south()) * $this->height);
    // il s'assurer que $x et $y soient entiers et pas INF
    if ($x < -1000000)
      $x = -1000000;
    if ($x > 1000000)
      $x = 1000000;
    if ($y < -1000000)
      $y = -1000000;
    if ($y > 1000000)
      $y = 1000000;
    return [intval($x), intval($y)];
  }
  
  /**
   * userCoord(array $pos): TPos - passe de coord écran en coord. utilisateurs
   *
   * @param array<int, int> $pos coord. écran
   * @return TPos coord. utilisateur
   */
  function userCoord(array $pos): array {
    return [
      $this->world->west() + $pos[0] / $this->width * ($this->world->east() - $this->world->west()),
      $this->world->north() - $pos[1] / $this->height * ($this->world->north() - $this->world->south()),
    ];
  }
  
  /**
   * dessine une ligne brisée
   *
   * @param TLPos $lpos liste de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polyline(array $lpos, array $style=[]): void {
    $color = $this->colorallocatealpha(
      isset($style['stroke']) ? $style['stroke'] : 0x000000,
      isset($style['stroke-opacity']) ? $style['stroke-opacity'] : 1);
    $pPim = null; // previous pim
    foreach ($lpos as $pos) {
      $pim = $this->proj($pos);
      if (!$pPim)
        $pPim = $pim;
      elseif (($pim[0]<>$pPim[0]) || ($pim[1]<>$pPim[1])) {
        if (!imageline($this->im, $pPim[0], $pPim[1], $pim[0], $pim[1], $color))
          throw new \Exception("Erreur imageline(im, $pPim[0], $pPim[1], $pim[0], $pim[1], $color) ligne ".__LINE__);
      }
      $pPim = $pim;
    }
  }
  
  /**
   * dessine un polygone
   *
   * @param TLLPos $llpos liste de listes de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polygon(array $llpos, array $style=[]): void {
    $color = $this->colorallocatealpha(
      isset($style['fill']) ? $style['fill'] : 0x808080,
      isset($style['fill-opacity']) ? $style['fill-opacity'] : 1);
    $pts = []; // le tableau des coords écran des points
    $pt = []; // coords écran courant
    $pt0 = []; // première coords écran
    $ptn = []; // le dernier point de l'extérieur
    foreach ($llpos as $lpos) {
      foreach ($lpos as $i => $pos) {
        $pt = $this->proj($pos);
        if ($i == 0)
          $pt0 = $pt;
        $pts[] = $pt[0];
        $pts[] = $pt[1];
      }
      // si le dernier point du ring est différent du premier alors ajout du premier point pour fermer le ring
      if ($pt <> $pt0) {
        $pts[] = $pt0[0];
        $pts[] = $pt0[1];
      }
      // je mémorise le dernier point de l'extérieur pour y revenir après chaque trou
      if (!$ptn)
        $ptn = $pt;
      else {
        $pts[] = $ptn[0];
        $pts[] = $ptn[1];
      }
    }
    //echo "<pre>imagefilledpolygon(pts="; print_r($pts); die(")");
    if (!imagefilledpolygon($this->im, $pts, $color))
      throw new \SExcept("Erreur imagefilledpolygon(im, pts, $color)", self::ErrorFilledPolygon);
    if (isset($style['stroke'])) {
      foreach ($llpos as $lpos)
        $this->polyline($lpos, $style);
    }
  }
  
  /**
   * Effectue une copie d'une image source dans l'image du dessin
   * Les paramètres sont ceux définis par GD:
   *    imagecopy(resource $dst_im, resource $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h): bool
   *      - dst_im - Lien vers la ressource cible de l'image.
   *      - src_im - Lien vers la ressource source de l'image.
   *      - dst_x - X : coordonnées du point de destination.
   *      - dst_y - Y : coordonnées du point de destination.
   *      - src_x - X : coordonnées du point source.
   *      - src_y - Y : coordonnées du point source.
   *      - src_w - Largeur de la source.
   *     - src_h - Hauteur de la source.
   *    Cette méthode n'utilise pas les coordonnées utilisateurs du dessin
   */
  function imagecopy(\GdImage $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h): void {
    if (!imagecopy($this->im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h))
      throw new \Exception("Erreur imagecopy() ligne ".__LINE__);
  }
  
  /*PhpDoc: methods
  name: imagecopy
  title: "function resample(BBox $newWorld, int $width, int $height): Drawing - rééchantillonnage de l'image"
  doc: |
    Effectue un rééchantillonnage de l'image paramétré en coordonnées utilisateur.
    En pratique génère un nouveau dessin dans le nouveau syst. de coord. utilisteur fourni.
  * /
  function resample(BBox $newWorld, int $width, int $height): Drawing {
    $newDrawing = new GdDrawing($newWorld, $width, $height);
    $nw = $this->proj($newWorld->northWest()); // les coords du rect de destination en coord. image d'origine
    $se = $this->proj($newWorld->southEast());
    //imagecopyresampled ( resource $dst_image , resource $src_image , int $dst_x , int $dst_y , int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h ) : bool;
    if (!imagecopyresampled($newDrawing->im, $this->im, 0, 0, $nw[0], $nw[1], $width, $height, $se[0]-$nw[0], $se[1]-$nw[1]))
      throw new \Exception("Erreur imagecopyresampled() ligne ".__LINE__);
    return $newDrawing;
  }*/
  
  /**
   * flush(string $format='', bool $noheader=false): void - affiche l'image construite
   *
   * @param string $format format MIME d'affichage
   * @param bool $noheader si vrai alors le header n'est pas transmis
  */
  function flush(string $format='', bool $noheader=false): void {
    if ($format == 'image/jpeg') {
      if (!$noheader)
        header('Content-type: image/jpeg');
      imagejpeg($this->im);
    }
    else {
      if (!imagesavealpha ($this->im, true))
        throw new \SExcept("Erreur imagesavealpha()", self::ErrorSaveAlpha);
      if (!$noheader)
        header('Content-type: image/png');
      imagepng($this->im);
    }
    imagedestroy($this->im);
    die();
  }
};

/**
 * Utilisation dans GdDrawing du planisphere créé par screenshot d'OSM.
 *
 * Pour l'utiliser avec GdDrawing, il faut d'une part le chemin du fichier, et la largeur et la hauteur de l'image,
 * et, d'autre part, définir l'espace de coordonnées permettant d'utiliser des coordonnées utilisateur en degrés.
 * Cet espace est défini dans GdDrawing par un EBox couvrant l'ensemble de l'espace image, qui est défini dans EBox().
 * L'explication des valeurs est donné dans DOC.
 * Le mapping entre les coordonnées est très approximatif, c'est une fonction affine entre lon/lat et X/Y image.
 * Je pourrais aller chercher les tuiles de OSM pour créer cette image.
 */
class Planisphere {
  /** Chemin du fichier. */
  const PATH = __DIR__.'/img/planisphere2.png';
  /** Largeur de l'image. */
  const WIDTH = 1315;
  /** Hauteur de l'image. */
  const HEIGHT = 821;
  /** Documentation du calcul de l'espace de coorddonnées. */
  const DOC = [
    <<<'EOT'
planisphere2:
  taille de l'image: 1315 X 821, -180° correspond à x=145, +180° correspond à x=1170
Calcul des paramètres de la fonction affine de la longitude en degrés -> X écran 
 y = a * x + b / où y coord. image, x coord. uti. degré
 145 = a * (-180) + b => a = (145 - b)/-180
 1170 = a * 180 + b => 1170 = (145 - b) * 180 / -180 + b = 2*b - 145 => b = (1170 + 145)/2 = 1315/2
& a = (145 - b)/-180 = (145 - (1170 + 145)/2)/-180 = 512,5 / 180
--
je cherche x pour y=0 et x pour y=1315
y=0 -> x = -b/a = - 1315/2 /  (512,5 / 180) = - 1315/1025 * 180
y=1315 -> 1315 = (512,5 / 180) * x + 1315/2 => x=1315/2 / (512,5 / 180) = 1315/1025 * 180
EOT
  ];
  /* XMIN de l'espace de coordonnées */
  const WEST = - 1315/1025 * 180;
  /** XMAX de l'espace de coordonnées. */
  const EAST = + 1315/1025 * 180;
  
  /** Création d'une image GD à partir du fichier PNG. */
  static function imagecreate(): \GdImage {
    if ($img = imagecreatefrompng(self::PATH))
      return $img;
    else
      throw new \Exception("Lecture du planisphere impossible");
  }
  
  /** Définition de l'espace de coordonnées utilisateurs en degrés. */
  static function EBox(): EBox { return new EBox([self::WEST, -90], [self::EAST, +90]); }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // test unitaire de GdDrawing


require_once __DIR__.'/../geom/gbox.php';
require_once __DIR__.'/../geom/geojson.inc.php';
require_once __DIR__.'/../lib.php';

use GeoJSON\Polygon;
use GeoJSON\LineString;
// La définition de GeoBox détermine si BBox ou GBox est utilisé
#use BBox\BBox as GeoBox;
use BBox\GBox as GeoBox;
use Lib\HtmlForm;

ini_set('memory_limit', '10G');
set_time_limit(5*60);

/** Contexte de l'appli de test transmis en cookie entre les appels. */
class Context {
  /** @var TLPos $lstr - la LigneString courante */
  protected array $lstr=[];
  /** @var TLLPos $mlstr - la MultiLigneString courante */
  protected array $mlstr=[];
  
  /** Ajoute une position à $lstr, contraint la longitude dans [-180, +180].
   * @param TPos $pos */
  function addPos(array $pos): void {
    if ($pos[0] > 180)
      $pos[0] = 180;
    if ($pos[0] < -180)
      $pos[0] = -180;
    $this->lstr[] = [round($pos[0]), round($pos[1])];
  }
  
  function addLsInMls(): void {
    $this->mlstr[] = $this->lstr;
    $this->lstr = [];
  }
  
  /** Construit le "Polygone" correspondant à un GBox sous la forme d'une LPos, utilisé par gboxOfLPosAsLLPos
   * @param TPos $sw
   * @param TPos $ne
   * @return TLPos
   */
  static function lposOfABox(array $sw, array $ne): array {
    return [$sw, [$sw[0],$ne[1]], $ne, [$ne[0],$sw[1]], $sw];
  }
  
  /** Retourne le countour de la GeoBox comme list<LineString> (LLPos).
   * Si la GeoBox N'intersecte PAS l'antiméridien alors c'est le contour de la GeoBox.
   * Si la GeoBox intersecte l'antiméridien alors les contours des 2 côtés
   * @return TLLPos 
   */
  function contourOfGeoBox(GeoBox $bbox): array {
    //print_r($bbox);
    $sw = $bbox->sw->pos();
    $ne = $bbox->ne->pos();
    if ($bbox->crossesAntimeridian()) {
      return [
        self::lposOfABox($sw, [$ne[0]+360, $ne[1]]), // la LPos à l'West de l'AM
        self::lposOfABox([$sw[0]-360,$sw[1]], $ne)   // la LPos à l'Est de l'AM
      ];
    }
    else {
      return [self::lposOfABox($sw, $ne)];
    }
  }
  
  /** Retourne lecountour de la GeoBox de $lstr comme list<LineString> (LLPos).
   * Si la GeoBox N'intersecte PAS l'antiméridien alors c'est le contour de la GeoBox.
   * Si la GeoBox intersecte l'antiméridien alors les contours des 2 côtés
   * @return TLLPos 
   */
  function contourOfGeoboxOfLstr(): array {
    if (!$this->lstr)
      return [];
    return self::contourOfGeoBox(GeoBox::fromLineString($this->lstr));
  }
  
  /** Retourne le countour de la GeoBox de $mlstr comme list<LineString> (LLPos).
   * Si la GeoBox N'intersecte PAS l'antiméridien alors c'est le contour de la GeoBox.
   * Si la GeoBox intersecte l'antiméridien alors les contours des 2 côtés
   * @return TLLPos 
   */
  function contourOfGeoboxOfMLstr(): array {
    if (!$this->mlstr)
      return [];
    return self::contourOfGeoBox(GeoBox::fromMultiLineString($this->mlstr));
  }
  
  /** Réinitialise le contenu du contexte */
  function clearContext(): void {
    $this->lstr = [];
    $this->mlstr = [];
  }
  
  /** Affiche le contexte de l'appli. */
  function display(): void {
    echo 'lstr=[',implode(', ', array_map(function($pos) { return sprintf("%.0f@%.0f", $pos[0], $pos[1]); }, $this->lstr)),"] -> \n";
    echo " geoboxOfLs=",GeoBox::fromLineString($this->lstr),"<br>\n";
    echo 'mlstr=[',
      implode(', ', array_map(
        function($lpos): string {
          return implode(',', array_map(
            function($pos) { return sprintf("%.0f@%.0f", $pos[0], $pos[1]); },
            $lpos));
        },
        $this->mlstr)),
      "] -> \n";
    echo "geoboxOfMLs=",GeoBox::fromMultiLineString($this->mlstr),"<br>\n";
  }
  
  function exportAsJSON(): string {
    return json_encode([
      'lstr'=> $this->lstr,
      'geoboxOfLstr'=> GeoBox::fromLineString($this->lstr)->as4Coords(),
      'mlstr'=> $this->mlstr,
      'geoboxOfMlstr'=> GeoBox::fromMultiLineString($this->mlstr)->as4Coords(),
    ]);
  }
};

/** Test de GdDrawing et une appli de debuggage des BBox et GBox.
 * Le principe est de saisir soit un LPos soit un LLPos sur un planisphère
 * puis de construire un BBox ou un GBox à partir de ces listes.
 * Chaque interaction génère un nouvel affichage.
 * Un cookie est utilisé pour conserver un contexte entre les appels.
 */
class GdDrawingTest {
  /** Le menu de l'appli sous la forme [{code}=> [titre]]. */
  const MENU = [
   'addLsInMls'=> "Ajoute la LS dans la MLS",
   'clearContext'=> "Efface le contenu du contexte",
   'export'=> "Exporte le contexte",
   'reinit'=> "Réinitialise le Cookie avec un nouveau contexte",
  ];
  /** Taille des rectangles de la Mpa Html en coord. écran. */
  const SIZE_OF_RECT = 20;
  
  /** Le context cad les données de l'appli */
  static Context $context;
  
  /** Récupère le contexte à partir du cookie. */
  static function getContext(): void { self::$context = unserialize($_COOKIE['contextGdDrawingTest']); }
  
  /** Enregistre le contexte dans le cookie, doit être appelé avant toute sortie de texte. */
  static function storeContext(): void { setcookie('contextGdDrawingTest', serialize(self::$context), time()+60*60*24*30, '/'); }
  
  /** Fabrique et transmet l'image au navigateur. */
  static function sendImage(GdDrawing $drawing): void {
    $drawing->imagecopy(Planisphere::imagecreate(), 0, 0, 0, 0, Planisphere::WIDTH, Planisphere::HEIGHT);

    (new LineString([[-180,-90],[-180,90]]))->draw($drawing, ['stroke'=> 0x0000FF]); // dessin de l'AM West
    (new LineString([[+180,-90],[+180,90]]))->draw($drawing, ['stroke'=> 0x0000FF]); // dessin de l'AM Est
    (new LineString([[-180, 0],[+180, 0]]))->draw($drawing, ['stroke'=> 0x0000FF]); // dessin de l'Equateur

    self::getContext();
    // dessin du bbox défini par la lstr
    $bboxAsMLS = self::$context->contourOfGeoboxOfLstr();
    foreach ($bboxAsMLS as $ls)
      (new LineString($ls))->draw($drawing, ['stroke'=> 0x888800]);

    // dessin du bbox défini par la mlstr
    $bboxAsMLS = self::$context->contourOfGeoboxOfMLstr();
    foreach ($bboxAsMLS as $ls)
      (new LineString($ls))->draw($drawing, ['stroke'=> 0xFF0000]);
    
    //$drawing->flush();
    $drawing->flush('', true);  // Utiliser cette ligne pour ne pas transmettre le header en cas de bug
  }
  
  /** Gère le Html du rect dans la Map. */
  static function genRect(int $x, int $y, int $dx, int $dy, string $href, string $alt): string {
    $x2 = $x + $dx;
    $y2 = $y + $dy;
    return "<area shape='rect' coords='$x, $y, $x2, $y2' href='$href' alt='$alt' />\n";
  }
  
  /** Génère le Html de la Map et l'intégration de l'image de la carte */
  static function genMapImage(int $width, int $height, int $delta): string {
    $mapName = 'map';
    $map = "<map name='$mapName'>\n";
    for($x = 0; $x < $width; $x += $delta) {
      for ($y=0 ;$y < $height; $y += $delta) {
        $xc = $x + $delta/2;
        $yc = $y + $delta/2;
        $map .= self::genRect($x, $y, $delta, $delta, "?action=ihm&x=$xc&y=$yc", "image{$xc}X{$yc}");
      }
    }
    $map .= "</map>\n";
    $map .= "<img src='?action=image' alt='image' usemap='#$mapName' />\n";
    return $map;
  }
  
  /** Génère le menu de l'appli. */
  static function menu(): void {
    echo "<table border=1><tr><form>\n",
         //"<input type='hidden' name='action' value='ihm'>\n",
         "<td>",HtmlForm::select('action', self::MENU),"</td>",
         "<td><input type='submit' value='ok'></td>\n",
         "</form></tr></table>\n";
  }
  
  static function display(GdDrawing $drawing): void {
    echo "<title>GdDrawing</title>\n";
    self::menu(); // affiche le formulaire de menu
    self::$context->display();
    echo self::genMapImage($drawing->width, $drawing->height, self::SIZE_OF_RECT); // affichage de la HtmlMap et l'image de la carte
    echo "<a href='?action=image'>Affiche l'image directement en PNG</a><br>\n";
  }
  
  /** Fonction principale. */
  static function main(): void {
    self::$context = new Context;
    $drawing = new GdDrawing(Planisphere::WIDTH, Planisphere::HEIGHT, Planisphere::EBox(), 0xFFFFFF);
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<title>GdDrawing</title>\n";
        echo "<a href='?action=test'>Test</a><br>\n";
        echo "<a href='?action=image'>Générer une image</a><br>\n";
        echo "<a href='?action=ihm'>IHM</a><br>\n";
        break;
      }
      case 'test': {
        $drawing = new GdDrawing(1315, 821, new EBox([0,0],[1315, 821]), 0xFFFFFF);

        (new Polygon([
          [[100,100],[100,700],[900,700],[900,100],[100,100]],
          [[110,110],[890,110],[890,300],[110,300],[110,110]],
          [[200,400],[500,400],[500,500]],
          [[890,690],[890,680],[880,680],[880,690],[890,690]]
        ]))->draw($drawing, ['fill'=> 0xaaff, 'stroke'=> 0]);
  

        $drawing->flush();
        //$drawing->flush('', true);  // Utiliser cette ligne pour ne pas transmettre le header en cas de bug
        break;
      }
      case 'image': { // Génération et sortie de l'image 
        self::sendImage($drawing);
        break;
      }
      case 'ihm': { // Affichage de l'IHM 
        if ($pos = isset($_GET['x']) ? $drawing->userCoord([$_GET['x'], $_GET['y']]) : null) {
          self::getContext();
          self::$context->addPos($pos);
          self::storeContext();
        }
        self::display($drawing);
        break;
      }
      case 'clearContext': { // efface le contenu du contexte
        self::getContext();
        self::$context->clearContext();
        self::storeContext();
        self::display($drawing);
        break;
      }
      case 'addLsInMls': { // Ajoute la LS dans la MLS 
        self::getContext();
        self::$context->addLsInMls();
        self::storeContext();
        self::display($drawing);
        break;
      }
      case 'export': { // exporte un cas considéré comme un bug
        self::getContext();
        $json = self::$context->exportAsJSON();
        file_put_contents(__DIR__.'/gddrawingoutput.json', "$json\n",  FILE_APPEND);
        echo "Export: $json<br>\n";
        self::display($drawing);
        break;
      }
      case 'reinit': { // stocke dans le cookie un nouveau contexte
        self::$context = new Context;
        self::storeContext();
        self::display($drawing);
        break;
      }
      default: throw new \Exception("Action $_GET[action] inconnue");
    }
  }
};

GdDrawingTest::main();

