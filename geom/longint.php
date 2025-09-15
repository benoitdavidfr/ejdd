<?php
/** Intervalle de longitudes sur la Terre.
 * @package BBox
 */
declare(strict_types=1);
namespace BBox;

/** Version 3 proposée par ChatGpt avec le comentaire:
Tu as tout à fait raison : mon approche “candidate AM” était fausse et pouvait rétrécir l’union au lieu de couvrir les 2 intervalles.
Voici une version robuste de union() qui calcule systématiquement l’enveloppe minimale sur le cercle : on passe en coordonnées [0,360), on calcule l’union linéaire, puis on prend le complément du plus grand “trou” pour obtenir l’arc connexe le plus court qui contient tout. Ça corrige ton exemple [-20, 30] ∪ [10, 40] → [-20, 40], et choisit bien une forme chevauchante l’antiméridien quand c’est plus court.

Tu peux remplacer/ajouter les méthodes suivantes dans ta classe (l’intersection() précédente reste valable) :
 */

/**
 * Version 4 compatible PhpStan
 *
 * Intervalle de longitudes sur la Terre pouvant chevaucher l'antiméridien,
 * représenté par un couple ($west, $east) dans (-180, +180], avec conventions :
 * - Sans chevauchement AM : -180 < $west <= $east < +180
 * - Avec chevauchement AM : -180 < $east < $west < +180 (west > east)
 * - Point : $west == $east
 * - Tour complet : (-180, +180)
 *
 * L'union renvoie toujours l'arc connexe minimal (sur le cercle) contenant A ∪ B.
 * L'intersection renvoie null si vide.
 */
final class LongInterval {
    private const EPS = 1e-9;

    public function __construct(
        public readonly float $west,
        public readonly float $east,
    ) {}

    /** L'intervalle chevauche l'antiméridien ? */
    public function crossesAM(): bool {
        return ($this->west > $this->east)
            || (($this->west === -180.0) && ($this->east === 180.0));
    }

    function __toString(): string {
      if (($this->west === -180.0) && ($this->east === 180.0))
        return 'TOUR_OF_THE_EARTH';
      if ($this->west == $this->east)
        return sprintf('[%.2f]', $this->west);
      if (!$this->crossesAM())
        return sprintf('[%.2f,%.2f]', $this->west, $this->east);
      return sprintf('[%.2f,+180] u [-180,%.2f]', $this->west, $this->east);
    }
    
    /* -----------------------------------------------------------------
     * Helpers communs (typés + doc pour PHPStan)
     * ----------------------------------------------------------------- */

    /**
     * Mappe (-180, 180] -> [0, 360)
     */
    private static function to360(float $x): float {
        // -180 et +180 sont canoniquement mappés à 180
        if (\abs($x - 180.0) <= self::EPS || \abs($x + 180.0) <= self::EPS) {
            return 180.0;
        }
        $t = \fmod($x + 360.0, 360.0);
        if ($t < 0.0) {
            $t += 360.0;
        }
        return $t;
    }

    /**
     * Mappe [0,360] -> (-180,180], 360 ≡ 0
     */
    private static function from360(float $t): float {
        if ($t >= 360.0 - self::EPS) {
            $t = 0.0;
        }
        if (\abs($t - 180.0) <= self::EPS) {
            return 180.0;
        }
        return ($t > 180.0 + self::EPS) ? ($t - 360.0) : $t;
    }

    /**
     * Longueur orientée de l’arc (en degrés) selon la convention west..east.
     */
    static function arcLength(float $w, float $e): float {
        if ($w === -180.0 && $e === 180.0) {
            return 360.0; // tour complet
        }
        return ($w <= $e) ? ($e - $w) : (360.0 - ($w - $e));
    }

    /**
     * Convertit l'intervalle en segments [w,e] ne croisant pas l'AM, dans (-180,180].
     *
     * @return list<array{0: float, 1: float}>
     */
    private function toSegments(): array {
        if ($this->west === -180.0 && $this->east === 180.0) {
            return [[-180.0, 180.0]];
        }
        if ($this->west === $this->east) {
            return [[$this->west, $this->east]]; // point
        }
        if ($this->west <= $this->east) {
            return [[$this->west, $this->east]];
        }
        // chevauchement AM
        return [
            [$this->west, 180.0],
            [-180.0, $this->east],
        ];
    }

    /**
     * Fusionne et trie des segments [w,e] (tous non chevauchants AM) dans (-180,180].
     *
     * @param list<array{0: float, 1: float}> $segs
     * @return list<array{0: float, 1: float}>
     */
    private static function mergeSegments(array $segs): array {
        if ($segs === []) {
            return [];
        }
        \usort(
            $segs,
            /** @param array{0: float, 1: float} $a @param array{0: float, 1: float} $b */
            static fn(array $a, array $b): int => $a[0] <=> $b[0]
        );

        $out = [];
        [$cw, $ce] = $segs[0];
        $n = \count($segs);
        for ($i = 1; $i < $n; $i++) {
            [$w, $e] = $segs[$i];
            if ($w <= $ce + self::EPS) {
                $ce = \max($ce, $e);
            } else {
                $out[] = [$cw, $ce];
                [$cw, $ce] = [$w, $e];
            }
        }
        $out[] = [$cw, $ce];

        // Si on a [-180,x] et [y,180] contigus -> tour complet
        if (\count($out) === 2) {
            [$w1, $e1] = $out[0];
            [$w2, $e2] = $out[1];
            if (\abs($w1 + 180.0) <= self::EPS && \abs($e2 - 180.0) <= self::EPS && \abs($e1 - $w2) <= self::EPS) {
                return [[-180.0, 180.0]];
            }
        }
        return $out;
    }

    /**
     * Reconstruit un LongInterval à partir de segments non chevauchants AM.
     *
     * @param list<array{0: float, 1: float}> $segs (longitudes dans (-180,180])
     */
    private static function fromSegments(array $segs): ?self {
        $count = \count($segs);
        if ($count === 0) {
            return null;
        }
        if ($count === 1) {
            return new self($segs[0][0], $segs[0][1]);
        }
        // deux segments attendus : [-180,e] et [w,180]
        [$w1, $e1] = $segs[0];
        [$w2, $e2] = $segs[1];
        if (\abs($w1 + 180.0) <= self::EPS) {
            return new self($w2, $e1);
        }
        return new self($w1, $e2);
    }

    /* -----------------------------------------------------------------
     * Helpers spécifiques pour l’union « arc minimal »
     * ----------------------------------------------------------------- */

    /**
     * Découpe l’intervalle en segments linéaires [s,e] (s<=e) dans l’axe 0..360.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function toSegments360(): array {
        // Tour complet
        if ($this->west === -180.0 && $this->east === 180.0) {
            return [[0.0, 360.0]];
        }
        // Point
        if (\abs($this->west - $this->east) <= self::EPS) {
            $p = self::to360($this->west);
            return [[$p, $p]];
        }
        $w = self::to360($this->west);
        $e = self::to360($this->east);
        if ($w <= $e) {
            return [[$w, $e]];
        }
        // passe par 360 -> 0
        return [[$w, 360.0], [0.0, $e]];
    }

    /**
     * Fusion linéaire (0..360) des segments [s,e] (s<=e). Fusionne aussi les segments tangents (gap ~ 0).
     *
     * @param list<array{0: float, 1: float}> $segs
     * @return list<array{0: float, 1: float}>
     */
    private static function mergeSegmentsLinear(array $segs): array {
        if ($segs === []) {
            return [];
        }
        \usort(
            $segs,
            /** @param array{0: float, 1: float} $a @param array{0: float, 1: float} $b */
            static fn(array $a, array $b): int => $a[0] <=> $b[0]
        );
        $out = [];
        [$cw, $ce] = $segs[0];
        $n = \count($segs);
        for ($i = 1; $i < $n; $i++) {
            [$w, $e] = $segs[$i];
            if ($w <= $ce + self::EPS) {
                $ce = \max($ce, $e);
            } else {
                $out[] = [$cw, $ce];
                [$cw, $ce] = [$w, $e];
            }
        }
        $out[] = [$cw, $ce];
        return $out;
    }

    /**
     * Construit le plus petit arc connexe couvrant les segments fusionnés en 0..360.
     *
     * @param non-empty-list<array{0: float, 1: float}> $merged  Segments fusionnés (s<=e), triés par start, couvrant A∪B
     */
    private static function minimalCoverFromMerged360(array $merged): self {
        $n = \count($merged);

        // Un seul segment
        if ($n === 1) {
            [$a, $b] = $merged[0];
            if (($b - $a) >= 360.0 - self::EPS) {
                return new self(-180.0, 180.0);
            }
            $w = self::from360($a);
            $e = self::from360($b);
            return new self($w, $e);
        }

        // Plusieurs segments : chercher le plus grand "trou" circulaire
        $maxGap = -1.0;
        $gapIdx = -1; // gap entre merged[i]..merged[i+1]
        for ($i = 0; $i < $n - 1; $i++) {
            $gap = $merged[$i + 1][0] - $merged[$i][1];
            if ($gap > $maxGap) {
                $maxGap = $gap;
                $gapIdx = $i;
            }
        }
        // Gap "wrap" entre fin du dernier et début du premier (+360)
        $wrapGap = ($merged[0][0] + 360.0) - $merged[$n - 1][1];
        $useWrap = false;
        if ($wrapGap > $maxGap) {
            $maxGap = $wrapGap;
            $useWrap = true;
        }

        // Si pas de trou significatif → tout le cercle
        if ($maxGap <= self::EPS) {
            return new self(-180.0, 180.0);
        }

        if ($useWrap) {
            // Couverture via Greenwich (non chevauchante) : [start(first), end(last)]
            $west360 = $merged[0][0];
            $east360 = $merged[$n - 1][1];
            $w = self::from360($west360);
            $e = self::from360($east360);
            return new self($w, $e);
        }

        // Couverture via AM (chevauchante) : [start(i+1) .. end(i)] (wrap)
        $west360 = $merged[$gapIdx + 1][0];
        $east360 = $merged[$gapIdx][1];
        $w = self::from360($west360);
        $e = self::from360($east360);
        return new self($w, $e); // si $w > $e → chevauche l'AM
    }

    /* -----------------------------------------------------------------
     * API publique
     * ----------------------------------------------------------------- */

    /**
     * Intersection entre 2 intervalles.
     */
    public function intersection(self $b): ?self {
        /** @var list<array{0: float, 1: float}> $aSegs */
        $aSegs = $this->toSegments();
        /** @var list<array{0: float, 1: float}> $bSegs */
        $bSegs = $b->toSegments();

        /** @var list<array{0: float, 1: float}> $res */
        $res = [];
        foreach ($aSegs as [$aw, $ae]) {
            foreach ($bSegs as [$bw, $be]) {
                $w = \max($aw, $bw);
                $e = \min($ae, $be);
                if ($w < $e + self::EPS) {
                    // normaliser le point si très proche
                    if (\abs($e - $w) <= self::EPS) {
                        $w = $e;
                    }
                    $res[] = [$w, $e];
                }
            }
        }
        $res = self::mergeSegments($res);
        return self::fromSegments($res);
    }

    /**
     * Union = plus petit arc connexe couvrant A ∪ B sur le cercle.
     * Retourne toujours un LongInterval (jamais null).
     */
    public function union(self $b): self {
        // Cas trivial : si l’un couvre tout
        if (($this->west === -180.0 && $this->east === 180.0)
            || ($b->west === -180.0 && $b->east === 180.0)) {
            return new self(-180.0, 180.0);
        }

        // 1) Segmenter dans 0..360
        /** @var list<array{0: float, 1: float}> $segs */
        $segs = \array_merge($this->toSegments360(), $b->toSegments360());

        // 2) Fusionner linéairement
        $merged = self::mergeSegmentsLinear($segs);

        // 3) Prendre le complément du plus grand trou → arc connexe minimal
        return self::minimalCoverFromMerged360($merged);
    }
}

/** Intervalle correspondant au tour complet de la Terre. */
const TOUR_OF_THE_EARTH = new LongInterval(-180.0, 180.0);


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // test unitaire de LongInterval


class LongIntervalTest {
  /** @return array<string, array<string,LongInterval|null>> */
  static function EXAMPLES() {
    return [
      "SimpleOverlap"=> [
        '$a' => new LongInterval(-20, 30),
        '$b' => new LongInterval(10, 40),
        '$expI'=> new LongInterval(10, 30),
        '$expU'=> new LongInterval(-20, 40),
      ],
      "TouchingAtEndpoint"=> [
        '$a' => new LongInterval(-50, 0),
        '$b' => new LongInterval(0, 10),
        '$expI' => new LongInterval(0, 0), // point
      ],
      "CrossesAntimeridianWithNonCrossing"=> [
        '$a' => new LongInterval(170, -170), // [170, 180] ∪ [-180, -170]
        '$b' => new LongInterval(-175, -160),
        '$expI' => new LongInterval(-175, -170),
      ],
      "intervalles éloignés ne c. PAS l'AM mais produisant un intervalle C l'AM"=> [ // example issu de tests 
        '$a' => new LongInterval(-171, -157),
        '$b' => new LongInterval(166, 173),
        '$expI'=> null,
        '$expU'=> new LongInterval(166, -157),
      ],
    ];
  }
  
  static function main(): void {
    echo "<title>LongInterval</title><h1>LongInterval</h1><pre>\n";
    foreach (self::EXAMPLES() as $name => $ex) {
      echo "<h2>$name</h2>\n";
      echo $ex['$a']," intersection ",$ex['$b']," -> ",$ex['$a']->intersection($ex['$b']) ?? 'vide',
                                                " / ",!array_key_exists('$expI', $ex) ? '??' : ($ex['$expI'] ?? 'vide'),"\n";
      echo $ex['$a']," union ",$ex['$b']," -> ",$ex['$a']->union($ex['$b'])," / ",$ex['$expU'] ?? '??',"\n";
    }
  }
};
LongIntervalTest::main();
