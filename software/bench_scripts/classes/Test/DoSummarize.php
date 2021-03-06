<?php
namespace Test;

final class DoSummarize extends AbstractTest
{

    private string $summaryType;

    private int $strPrefixSize;

    public function __construct(\DataSet $ds, CmdArgs $cmdParser, Data\IPartition ...$partitions)
    {
        parent::__construct($ds, $cmdParser, ...$partitions);

        $args = $cmdParser->parsed()['args'];

        if (($cmd = $args['cmd']) !== 'summarize')
            throw new \Exception(__CLASS__ . " cannot handle cmd=$cmd");

        $this->summaryType = $args['summary'];
        $this->strPrefixSize = $cmdParser->parsed()['javaProperties']['summary.filter.stringValuePrefix'];
    }

    public static function summarize(\DataSet $ds, \Data\IPartition $partition, string $summaryType, int $strPrefixSize): void
    {
        $summArgs = [
            'cmd' => 'summarize',
            'skip-existing' => true,
            'generate-dataset' => false,
            'clean-db' => false,
            'summary' => $summaryType,
            'output' => \sys_get_temp_dir(),
            'plot' => false,
            'doonce' => true,
            'forget-results' => true,
            'Psummary.filter.stringValuePrefix' => $strPrefixSize
        ];
        $doItParser = CmdArgs::default();
        $doItParser->parse($summArgs);
        $doIt = new OneTest($ds, $doItParser, $partition);
        $doIt->setDisplayHeader(false);

        $testConfig = $doIt->getTestConfig();

        echo "\nSummarizing <$ds/{$partition->getID()}>\n";
        $allExists = true;
        $summaries = (array) $testConfig['summary'];

        foreach ($summaries as $summaryPath) {
            $fname = \basename($summaryPath);
            echo "$fname: ";

            if (\is_file($summaryPath)) {
                echo "Already exists\n";
            } else {
                $allExists = false;
                echo "Must be generated\n";
            }
        }

        if ($allExists) {
            if (\count($summaries) > 1)
                echo "Nothing to do!\n";
            return;
        }
        $summaryName = \basename($summaryPath);

        $xmlLoader = \XMLLoader::of($ds);
        $xmlLoader->convert();
        $xmlLoader->load();
        $doIt->execute();
        \clearstatcache();
    }

    public function execute()
    {
        self::summarize($this->ds, $this->partitions[0], $this->summaryType, $this->strPrefixSize);
    }
}