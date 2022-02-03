<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/benchmark/config/makeConfig.php';
require_once __DIR__ . '/common/functions.php';

\array_shift($argv);

if (empty($argv)) {
    \fputs(STDERR, "No argument provided");
    exit(1);
}

$cmdArgsDef = [
    // 'data' => null,
    // 'rules' => null,
    'summary' => "key",
    'native' => '',
    'cmd' => 'querying',
    'doonce' => false,
    'cold' => false,
    'output' => null,
    'skip.existing' => true
];

if (empty($argv))
    $argv[] = ";";

while (! empty($argv)) {
    $cmdParsed = \parseArgvShift($argv, ';') + $cmdArgsDef;
    $dataSets = \array_filter($cmdParsed, 'is_int', ARRAY_FILTER_USE_KEY);
    $summarize = false;

    if (\in_array($cmdParsed['cmd'], [
        'summarize',
        'each',
        'config'
    ])) {
        $cmdParsed['doonce'] = true;
        $summarize = $cmdParsed['cmd'] === 'summarize';
    }

    if (\count($dataSets) == 0) {
        echo "Test ALL dataSets\n\n";
        $dataSets = DataSet::getAllGroups();
    }

    while (null !== ($dataSetId = \array_shift($dataSets))) {
        $dataSet = new DataSet($dataSetId);
        $rules = $dataSet->getRules();
        checkDataSetExists($dataSet);

        if (\array_intersect([
            'simplified',
            'simplified.all'
        ], $dataSet->getQualifiers()) !== [])
            $cmdParsed['summary'] = 'key-type';

        foreach ($dataSet->getRules() as $theRules) {
            $dataSet->setTheRules($theRules);

            echo "\n<{$dataSet->getTheId()}>\n";
            $config = makeConfig($dataSet, $cmdParsed);

            $bench = new \Benchmark($config);

            $collection = MongoImport::getCollectionName($dataSet);

            if (! MongoImport::collectionExists($collection)) {
                fwrite(STDERR, "!!The collection treeforce.$collection must exists in the database!!");
                continue;
            }

            if ($cmdParsed['skip.existing']) {

                if ($summarize) {
                    $path = $config['java.properties']['summary'];

                    if (\is_file($path)) {
                        $fname = \basename($path);
                        echo "\n<{$dataSet->getTheId()}>($fname)\n(Skipped) Summary already exists\n";
                        continue;
                    }
                } else if (! empty($existings = $bench->getExistings())) {
                    $existings = implode(",\n", $existings);
                    echo "(Skipped) Similar test already exists: $existings\n";
                    continue;
                }
            }

            try {
                if ($cmdParsed['doonce'])
                    $bench->executeOnce();
                else
                    $bench->doTheBenchmark();
            } catch (\Exception $e) {
                fwrite(STDERR, "<{$dataSet->getTheId()}>Exception:\n {$e->getMessage()}\n");
            }
        }
    }
}