<?php
$basePath = \realpath(__DIR__ . "/../..");

$measuresNb = 6;
$measuresForget = 1;
$batchSize = 500;

$measuresNb = 1;
$measuresForget = 0;
// $batchSize = 1;

$time = \date(DATE_ATOM);
$outDir = $time;

return [
    'jar.path' => "$basePath/software/java/treeforce-demo-0.1-SNAPSHOT.jar",
    'java.opt' => "-Xmx10G",

    'java.properties' => [
        'base.path' => $basePath,
        'output.measures' => "std://out" . ($measuresNb > 1 ? '' : ",\${output.path}/\${query.name}_measures.txt"),
        'query.native' => "",
        'query.batchSize' => $batchSize,
        'data.batchSize' => 100,
        'leaf.checkTerminal' => 'n',
        'querying.each' => 'n',
        'inhibitBatchStreamTime' => 'y',
        'querying.display.answers' => 'n',
        'querying.output.pattern' => '${output.path}/${query.name}_%s.txt',
        'query' => '${queries.dir}/${query.name}',
        'querying.config.print' => 'y',
        'data' => 'mongodb://localhost/treeforce.${db.collection}',
        'rules' => '',
        'summary' => ''
    ],

    'bench.measures' => [
        'default' => [
            'nb' => $measuresNb,
            // We delete this number of measure from the start and the end of the sorted array of measures
            'forget' => $measuresForget
        ],
        '100000' => [
            'nb' => min(3, $measuresNb),
            'forget' => 0
        ],
        '1000000' => [
            'nb' => 1,
            'forget' => 0
        ]
    ],
    'bench.cold.function' => function () {
        system('sudo service mongodb stop');
        system('sudo service mongodb start');
        sleep(1);
    },
    'bench.output.base.path' => "$basePath/outputs",
    'bench.output.dir' => $outDir,
    'bench.datetime' => $outDir
];
