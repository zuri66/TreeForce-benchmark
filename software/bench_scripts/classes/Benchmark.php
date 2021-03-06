<?php

final class Benchmark
{

    private const dateFormat = DateTime::ATOM;

    private array $config;

    private string $cmd;

    private string $tmpOutFile;

    private string $qOutputPath;

    private array $descriptors;

    private array $javaOutput;

    private static $timeMeasure = [
        'r',
        'u',
        's',
        'c'
    ];

    function __construct(array $config)
    {
        $this->config = $config;

        $jarPath = \escapeshellarg($config['jar.path']);
        $appCmd = \escapeshellarg($config['app.cmd']);

        $opt = $config['java.opt'];
        $this->cmd = "java $opt -jar $jarPath $appCmd -c std://in";

        $this->tmpOutFile = \tempnam(sys_get_temp_dir(), 'tf_');
        $this->descriptors = $descriptorspec = array(
            0 => [
                "pipe",
                "r"
            ],
            1 => [
                'file',
                $this->tmpOutFile,
                'w+'
            ],
            2 => STDOUT
        );
        $this->qOutputPath = $config['java.properties']['output.path'];
    }

    private function createOutputDir(): void
    {
        if (! \is_dir($this->qOutputPath)) {
            \mkdir($this->qOutputPath, 0777, true);

            // Protect against too long name
            \wdPush($this->qOutputPath);
            $this->writeBenchConfigToCSV(new SplFileObject("@config.csv", "w"));
            \wdPop();
        }
    }

    public function getExistings(): array
    {
        $outPath = \dirname($this->qOutputPath);

        if (! \is_dir($outPath))
            return [];

        $regex = $this->config['bench.output.pattern'];
        $regex = \str_replace([
            '[',
            ']',
            '(',
            ')'
        ], [
            '\[',
            '\]',
            '\(',
            '\)'
        ], $regex);
        $regex = sprintf($regex, '[^\]]+');
        $a = $files = \scandirNoPoints($outPath);
        $files = \array_filter($files, fn ($f) => \preg_match("#^$regex$#", $f));
        return $files;
    }

    function __destruct()
    {
        \unlink($this->tmpOutFile);
    }

    private function writeJavaProperties(array $incVar)
    {
        $s = "";
        $jprop = $this->config['java.properties'] + $incVar;

        foreach ($this->javaPropertiesPairs($jprop) as $pair) {
            list ($k, $v) = $pair;
            $s .= "$k: $v\n";
        }
        return $s;
    }

    function parseJavaOutput(): array
    {
        $lines = \file($this->tmpOutFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $ret = [];
        $p = &$ret['default'];

        foreach ($lines as $line) {

            if (preg_match("#\[(.+)\]#", $line, $matches)) {
                $group = $matches[1];
                $p = &$ret[$group];
            } else if (preg_match("#([^\s=:]+)[\s=:]+([^\s]+)#", $line, $matches)) {
                $var = $matches[1];
                $val = $matches[2];

                if (\is_numeric($val))
                    $val = (int) $val;

                $p[$var] = $val;
            }
        }
        $ret['measures'] = $this->appExtractMeasures($ret['measures'] ?? []);
        return $ret;
    }

    function appExtractMeasures($measures): array
    {
        foreach ($measures as &$meas) {
            $time = $meas;
            $meas = [];

            foreach ($this::$timeMeasure as $tm) {
                \preg_match("/$tm(-?\d+)/", $time, $capture, PREG_OFFSET_CAPTURE);
                $meas[$tm] = (int) $capture[1][0];
            }
        }
        return $measures;
    }

    private static function avg(array $vals): int
    {
        $c = count($vals);

        if ($c == 0)
            return 0;

        return round(array_sum($vals) / $c);
    }

    private function javaPropertiesPairs(array $jprops): array
    {
        $ret = [];

        foreach ($jprops as $k => $v) {
            if (! \is_array($v))
                $v = (array) $v;

            foreach ($v as $v)
                $ret[] = [
                    $k,
                    $v
                ];
        }
        return $ret;
    }

    private function writeBenchConfigToCSV(SplFileObject $csvFile)
    {
        $basePathEndOffset = \strlen($this->config['java.properties']['base.path']) + 1;
        $csvFile->fputcsv([
            'bench'
        ]);
        $dataSet = \array_slice(\explode('/', $this->qOutputPath), - 3, - 1);
        $csvFile->fputcsv([
            'dataSet',
            $this->config['dataSet']->id()
        ]);
        $csvFile->fputcsv([
            'datetime',
            $this->config['bench.datetime']->format(self::dateFormat)
        ]);

        foreach ($this->javaPropertiesPairs($this->config['java.properties']) as $pair)
            $csvFile->fputcsv($pair);
    }

    private function writeCSV(string $queryFile, array $measures)
    {
        $usedMeasures = $this->forgetMeasures($queryFile, $measures);

        if (empty($usedMeasures))
            return;

        $usedMeasures = \array_column($usedMeasures, 'measures');
        $columns = \array_keys($usedMeasures[0]);
        $avg = [];

        /*
         * Aggregate infos (average, ...)
         */
        foreach ($columns as $column) {
            $colMeasures = \array_column($usedMeasures, $column);
            $avg[$column] = [];

            foreach ($this::$timeMeasure as $tm)
                $avg[$column][$tm] = $this::avg(\array_column($colMeasures, $tm));
        }

        // Make the query file
        \wdPush($this->qOutputPath);
        $qfile = new \SplFileObject("$queryFile.csv", 'w');
        \wdPop();

        $measureConfig = $this->getMeasuresConfig($queryFile);
        $forgetNb = (int) $measureConfig['forget'];
        $forgetLine = \array_fill(0, $forgetNb, 'forget');
        $forgetLine = \array_merge($forgetLine, \array_fill(0, count($usedMeasures), null));
        $forgetLine = \array_merge($forgetLine, \array_fill(0, $forgetNb, 'forget'));
        {
            $qfile->fputcsv([
                'bench'
            ]);
            $qfile->fputcsv([
                'measures.nb',
                $measureConfig['nb'] ?? ''
            ]);
            $qfile->fputcsv([
                'measures.forget',
                $measureConfig['forget'] ?? ''
            ]);
        }
        $qfile->fputcsv([]);

        { // Print no-time measures
            $columns = \array_keys($measures[0]);
            unset($columns[\array_search('measures', $columns, true)]);

            foreach ($columns as $column) {
                $cols = \array_column($measures, $column);

                if (empty($cols[0]))
                    continue;

                $items = \array_keys($cols[0]);
                $qfile->fputcsv([
                    $column
                ]);

                foreach ($items as $item)
                    $qfile->fputcsv(\array_merge([
                        $item
                    ], \array_column($cols, $item)));

                $qfile->fputcsv([]);
            }
        }
        { // Print time measures
            $columns = \array_keys($usedMeasures[0]);
            $allMeasures = \array_column($measures, 'measures');

            foreach ($columns as $column) {
                $colMeasures = \array_column($allMeasures, $column);

                $qfile->fputcsv(\array_merge([
                    $column,
                    'avg'
                ], $forgetLine));

                foreach ($this::$timeMeasure as $tm) {
                    $tmp = \array_merge([
                        "$tm",
                        $avg[$column][$tm]
                    ], \array_column($colMeasures, $tm));
                    $qfile->fputcsv($tmp);
                }
                $qfile->fputcsv([]);
            }
        }
    }

    private function forgetMeasures(string $queryFile, array $measures): array
    {
        $forget = (int) $this->getMeasuresConfig($queryFile)['forget'];

        if ($forget > 0)
            return array_slice($measures, $forget, - $forget);
        else
            return $measures;
    }

    private function prepareIncVars(string $query = '')
    {
        $config = $this->config;

        if (! empty($n = $config['bench.query.native.pattern']))
            $native = sprintf($n, $query);

        return [
            'query.name' => $query,
            'query.native' => $native ?? ''
        ];
    }

    private function getMeasuresConfig(string $queryFile)
    {
        $config = $this->config;
        return $config['bench.measures'][$queryFile] ?? $config['bench.measures']['default'];
    }

    private function executeMeasures(string $queryFile, string $header, ?int $forceNbMeasures = null)
    {
        $config = $this->config;
        $cold = $config['bench.cold'];
        $measures = [];

        if (isset($forceNbMeasures)) {
            $nbMeasures = $totalMeasures = 1;
        } else {
            $confMeasure = $this->getMeasuresConfig($queryFile);
            $nbMeasures = $totalMeasures = $confMeasure['nb'];
        }

        $incVars = $this->prepareIncVars($queryFile);
        $i = 1;

        while ($nbMeasures --) {

            if ($cold)
                $config['bench.cold.function']();

            // if ($nbMeasures === 0)
            // $incVars['querying_config_print'] = 'y';

            echo $totalMeasures - $nbMeasures, "/$totalMeasures $header\n";

            $proc = \proc_open($this->cmd, $this->descriptors, $pipes);
            \fwrite($pipes[0], $this->writeJavaProperties($incVars));
            \fclose($pipes[0]);
            $cmdReturn = \proc_close($proc);

            if (0 !== $cmdReturn)
                exit($cmdReturn);

            if ($this->config['app.output.display'])
                \readfile($this->tmpOutFile);

            $measures[] = [
                'test' => [
                    'index' => $i ++
                ]
            ] + $this->parseJavaOutput();
        }
        $sortMeasure = $this->config['bench.sort.measure'];
        \usort($measures, function ($a, $b) use($sortMeasure) {
            return $a['measures'][$sortMeasure]['r'] - $b['measures'][$sortMeasure]['r'];
        });
        return $measures;
    }

    public function printJavaConfig(): void
    {
        $incVars = $this->prepareIncVars();
        echo $this->writeJavaProperties($incVars);
    }

    public function executeOnce()
    {
        $this->createOutputDir();
        $incVars = $this->prepareIncVars();
        $descriptors = $this->descriptors;
        $descriptors[1] = STDOUT;

        echo $this->cmd, "\n";

        $proc = \proc_open($this->cmd, $descriptors, $pipes);
        \fwrite($pipes[0], $this->writeJavaProperties($incVars));
        \fclose($pipes[0]);
        $cmdReturn = \proc_close($proc);

        if (0 !== $cmdReturn)
            \fputs(STDERR, "Return code: $cmdReturn\n");
    }

    public function doTheBenchmark(?int $forceNbMeasures = null)
    {
        $this->createOutputDir();
        $queries = $this->config['dataSet']->getQueries();

        echo $this->cmd, "\n\n";

        foreach ($queries as $query) {
            $header = "<{$this->config['dataSet']}> ({$this->config['app.cmd']}) query: $query";

            $queryFile = $query;
            $measures = $this->executeMeasures($query, $header, $forceNbMeasures);

            $this->writeCSV($query, $measures);
            echo "\n";
        }
        $this->plot();
    }

    private function bringOutputs(): void
    {
        $argv[] = 'plot.php';
        $argv[] = \dirname($this->qOutputPath);
        $argv[] = 0;

        include __DIR__ . "/../bring-outputs.php";
    }

    private function plot(): void
    {
        $plotTypes = $this->config['bench.plot.types'];

        if (empty($plotTypes))
            return;

        $argv[] = 'plot.php';
        $argv[] = $this->qOutputPath;
        $argv[] = "types=$plotTypes";

        include __DIR__ . "/../plot.php";
        $this->bringOutputs();
    }
}
