<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/benchmark/config/makeConfig.php';
require_once __DIR__ . '/common/functions.php';

\array_shift($argv);

$cmdParser = \Test\CmdArgs::default();

while (! empty($argv)) {
    $current_argv = \parseArgvShift($argv, ';');
    $cmdParser->parse($current_argv);

    $dataSets = $cmdParser['dataSets'];
    $cmd = $cmdParser['args']['cmd'];

    if ($cmdParser['args']['print-java-config'])
        $testClass = '\Test\PrintJavaConfig';
    elseif ($cmd === 'summarize')
        $testClass = '\Test\DoSummarize';
    else
        $testClass = '\Test\OneTest';

    $preCleanDB = $cmdParser['args']['pre-clean-db'] || $cmdParser['args']['clean-db'];
    $postCleanDB = $cmdParser['args']['post-clean-db'] || $cmdParser['args']['clean-db'];

    // Inhibit db clean until the first or last test
    $cmdParser['args']['clean-db'] = false;
    $cmdParser['args']['pre-clean-db'] = false;

    $cmdExpansions = $cmdParser->expand();
    $cmdExpansions_nb = \count($cmdExpansions);

    $errors = [];
    $cmdGroupExpansions = [
        0 => [], // normal
        1 => [] // parallel
    ];

    foreach ($cmdExpansions as $e) {
        $parallelTest = (int) $e['args']['parallel'];
        $cmdGroupExpansions[$parallelTest][] = $e;
    }

    foreach ($dataSets as $dataSet) {
        $cmdExpansions_i = 0;
        $dsPartitions = $dataSet->getPartitions();
        $partitions = [];

        foreach ($dsPartitions as $subPartitions) {
            $logicalPartitioning = $subPartitions->getLogicalPartitioning();

            // $subPartitions is one PhysicalPartition
            if ($logicalPartitioning === null)
                $partitions[] = $subPartitions;
            else
                $partitions = \array_merge($partitions, $logicalPartitioning->getPartitionsOf($dataSet));
        }

        // parallel tests
        if (! empty($cmdGroupExpansions[1])) {

            foreach ($cmdGroupExpansions[1] as $cmdFinalParser) {
                ++ $cmdExpansions_i;

                if ($dataSet->getPartitioning() instanceof \Data\NoPartitioning) {
                    echo "Invalid test: skipped!";
                    continue;
                }
                $cmdFinalParser['args']['pre-clean-db'] = $preCleanDB;

                if ($cmdExpansions_i == $cmdExpansions_nb)
                    $cmdFinalParser['args']['post-clean-db'] = $postCleanDB;

                $test = new $testClass($dataSet, $cmdFinalParser, ...$partitions);
                $test->execute();
                $test->reportErrors();
                $errors = \array_merge($errors, $test->getErrors());

                if (! isset($skipped) || $skipped)
                    $skipped = $cmdFinalParser['skipped'] ?? false;
                if (! $skipped)
                    $preCleanDB = false;
            }
        }

        if (! empty($cmdGroupExpansions[0])) {
            $cmdExpansions_i_offset = $cmdExpansions_i;
            $partition_nb = \count($partitions);
            $partition_i = 0;

            // Non parallel tests
            foreach ($partitions as $partition) {
                $partition_i ++;
                $cmdExpansions_i = $cmdExpansions_i_offset;

                foreach ($cmdGroupExpansions[0] as $cmdFinalParser) {
                    $cmdExpansions_i ++;
                    $cmdFinalParser['args']['pre-clean-db'] = $preCleanDB;

                    if ($cmdExpansions_i == $cmdExpansions_nb && $partition_i == $partition_nb)
                        $cmdFinalParser['args']['post-clean-db'] = $postCleanDB;

                    $test = new $testClass($dataSet, $cmdFinalParser, $partition);
                    $test->execute();
                    $test->reportErrors();
                    $errors = \array_merge($errors, $test->getErrors());

                    if (! isset($skipped))
                        $skipped = $cmdFinalParser['skipped'] ?? false;
                    if (! $skipped)
                        $preCleanDB = false;
                }
            }
        }
    }
    if (! empty($errors))
        $test->reportErrors($errors);
}
