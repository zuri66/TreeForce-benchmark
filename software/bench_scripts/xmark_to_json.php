
<?php
require_once __DIR__ . '/classes/autoload.php';
include_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdConfig = [
    'xmark.program.path' => 'software/bin/xmlgen.Linux'
];

$cmdArgsDef = [
    'drop-empty' => false,
    'drop' => false,
    'generate' => true,
    'load' => false,
    'pre-clean' => false,
    'pre-clean-db' => false,
    'pre-clean-all' => false,
    'post-clean' => false,
    'post-clean-xmark' => false,
    'post-clean-all' => false,
    'simplify_object_useConfig' => true
];

if (empty($argv))
    $argv[] = ";";

while (! empty($argv)) {
    $cmdParsed = $cmdArgsDef;
    $cmdRemains = \updateArray_getRemains(\parseArgvShift($argv, ';'), $cmdParsed);

    $dataSets = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);

    $javaProperties = \array_filter_shift($cmdRemains, fn ($k) => ($k[0] ?? '') === 'P', ARRAY_FILTER_USE_KEY);

    if (! empty($cmdRemains)) {
        $usage = "\nValid cli arguments are:\n" . \var_export($cmdParsed, true) . //
        "\nor a Java property of the form P#prop=#val\n";
        throw new \Exception("Unknown cli argument(s):\n" . \var_export($cmdRemains, true) . $usage);
    }
    $cmdParsed += $javaProperties;

    if (\count($dataSets) == 0) {
        $dataSets = [
            null
        ];
    }
    $dataSets = \array_unique(DataSets::all($dataSets));
    DataSets::checkNotExists($dataSets, false);

    // Group by 'group'
    $toProcess = [];
    foreach ($dataSets as $dataSet) {
        $group = $dataSet->group();
        $qualifiers = $dataSet->qualifiersString();
        $toProcess["$group"][] = $dataSet;
    }

    $doNotSimplify = include __DIR__ . '/xmark_to_json/do_not_simplify.php';

    foreach ($toProcess as $dataSets) {
        echo "\n";
        $qualifiers = $dataSets[0]->qualifiers();
        $converter = (new \XMark2Json($dataSets, $cmdConfig))->doNotSimplify($doNotSimplify);

        if ($cmdParsed['pre-clean-db'] || $cmdParsed['pre-clean-all'])
            MongoImport::dropDatabase($dataSet);

        if ($cmdParsed['pre-clean'] || $cmdParsed['pre-clean-all'])
            $converter->clean();

        if ($cmdParsed['drop'])
            $converter->drop();
        elseif ($cmdParsed['drop-empty'])
            $converter->dropEmpty();
        elseif ($cmdParsed['generate'])
            $converter->convert();

        if ($cmdParsed['load'])
            MongoImport::importDataSet($dataSet);

        if ($cmdParsed['post-clean'] || $cmdParsed['post-clean-all'])
            $converter->clean();

        if ($cmdParsed['post-clean-xmark'] || $cmdParsed['post-clean-all'])
            $converter->deleteXMark();
    }
}