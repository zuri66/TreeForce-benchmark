<?php
namespace Plotter;

final class Graphics implements \ArrayAccess
{

    private array $graphics;

    public function __construct()
    {
        $this->graphics = include __DIR__ . '/graphics_conf.php';
    }

    // ========================================================================
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->graphics[] = $value;
        } else {
            $this->graphics[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->graphics[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->graphics[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->graphics[$offset]) ? $this->graphics[$offset] : null;
    }

    // ========================================================================
    public function getGraphics()
    {
        return $this->graphics;
    }

    public function compute(int $nbBars, int $nbBarGroups, int $yMax)
    {
        $g = &$this->graphics;
        $g = [
            'plot.y.max' => $yMax,
            'plot.gap.nb' => $nbBarGroups + 1
        ] + $g;

        $g['plot.y.step.nb'] = (int) ceil(log10($g['plot.y.max']));

        $nbBarsWithGaps = $nbBars + ($nbBarGroups * $g['bar.gap.factor']);
        $g['plot.w'] = $nbBarsWithGaps * $g['bar.w'];
        $g['plot.h'] = $g['plot.y.step.nb'] * $g['plot.y.step'] + $g['plot.h.space'];

        $g['plot.x'] = $g['plot.lmargin'];
        $g['plot.y'] = $g['plot.bmargin'];

        $g['plot.w.full'] = $g['plot.w'] + $g['plot.lmargin'];
        $g['plot.h.full'] = $g['plot.h'] + $g['plot.bmargin'];

        $g['w'] = $g['plot.w.full'] + $g['plot.rmargin'];
        $g['h'] = $g['plot.h.full'];
        
        $g['blocs.w'] = 0;
        $g['blocs.h'] = 0;
    }

    private function graphics_addBSpace(int $space)
    {
        $g = &$this->graphics;
        $g['blocs.h'] += $space;
        $g['h'] += $space;
        $g['plot.y'] += $space;
    }

    public function addFooter(array $footerBlocs): string
    {
        $blocs = \array_map([
            $this,
            'computeFooterBlocGraphics'
        ], $footerBlocs);

        list ($charOffset, $h) = \array_reduce($blocs, fn ($c, $i) => [
            \max($c[0], $i['lines.nb']),
            \max($c[1], $i['h'])
        ], [
            0,
            0
        ]);
        $this->graphics_addBSpace($h);
        $ret = '';

        $x = 0;
        foreach ($blocs as $b) {
            $s = \str_replace('_', '\\\\_', \implode('\\n', $b['bloc']));
            $ret .= "set label \"$s\" at screen 0.01,0.01 offset character $x, character $charOffset\n";
            $x += $b['lines.size.max'];
        }
        $g = &$this->graphics;
        $g['blocs.w'] += $x * $g['font.size'] * 0.9;
        $this->updateWidth();
        return $ret;
    }
    
    private function updateWidth()
    {
        $g = &$this->graphics;
        $g['w'] = \max($g['w'], $g['blocs.w']);
        
    }

    private function computeFooterBlocGraphics(array $bloc): array
    {
        $bloc = \array_map(fn ($v) => empty($v) ? '' : (null === ($v[1] ?? null) ? $v[0] : "$v[0]: $v[1]"), $bloc);
        $maxLineSize = \array_reduce($bloc, fn ($c, $i) => \max($c, strlen($i)), 0);
        $nbLines = \count($bloc);
        $g = $this->graphics;

        return [
            'bloc' => $bloc,
            'lines.nb' => $nbLines,
            'lines.size.max' => $maxLineSize * 0.85,
            'w' => $g['font.size'] * $maxLineSize,
            'h' => ($g['font.size'] + 8) * $nbLines
        ];
    }

    public function getYMinMax(array $arrOfCsvData): array
    {
        $min = PHP_INT_MAX;
        $max = 0;
        $times = [
            'r',
            'c'
        ];

        foreach ($arrOfCsvData as $csvData) {
            
            foreach ($csvData as $meas) {
                
                if (! \Plot::isTimeMeasure($meas))
                    continue;

                foreach ($times as $t) {
                    $max = \max($max, $meas[$t]);
                    $min = \min($min, $meas[$t]);
                }
            }
        }
        return [
            $min,
            $max
        ];
    }

    public function plotYLines(int $yMax): string
    {
        $yNbLine = log10($yMax);

        for ($i = 0, $m = 1; $i < $yNbLine; $i ++) {
            $lines[] = "$m ls 0";
            $m *= 10;
        }
        return implode(",\\\n", $lines);
    }

    public function prepareBlocs(array $groups, array $exclude = [], array $val = []): array
    {
        if (empty($val))
            $val = $this->getPlotVariables();

        $blocs = [];

        foreach ($groups as $group) {
            $blocs[] = $this->prepareOneBloc((array) $group, $exclude, $val);
        }
        return $blocs;
    }

    public function prepareOneBloc(array $group, array $exclude = [], array $val = []): array
    {
        if (empty($val))
            $val = $this->getPlotVariables();

        $line = [];

        foreach ((array) $group as $what) {
            $line[] = [
                "[$what]",
                null
            ];

            foreach ($val[$what] as $k => $v) {

                if (in_array($k, $exclude))
                    continue;

                $line[] = [
                    $k,
                    (string) $v
                ];
            }
            $line[] = null;
        }
        return $line;
    }
}