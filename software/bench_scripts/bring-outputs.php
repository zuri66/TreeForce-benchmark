<?php
require_once __DIR__ . '/classes/autoload.php';
require_once __DIR__ . '/common/functions.php';
array_shift($argv);

final class BringIt
{

    private string $outputPath;

    private array $scanned = [];

    public function __construct(string $outDir)
    {
        $this->outputPath = getBenchmarkBasePath() . "/$outDir";
    }

    private function bringFileName(string $dirName)
    {
        return "at$dirName.png";
    }

    public function scan()
    {
        clearstatcache();
        $lastScan = $this->scanned;
        $this->scanned = [];

        $scanned = scandirNoPoints($this->outputPath);

        foreach ($scanned as $dirName) {
            $path = "$this->outputPath/$dirName";

            if (is_dir($path)) {
                $this->scanned[] = $dirName;
            }
        }
        $dels = array_diff($lastScan, $this->scanned);

        foreach ($this->scanned as $dir) {
            $pathLink = "$this->outputPath/" . $this->bringFileName($dir);
            $ref = "$this->outputPath/$dir/all_time.png";

            if (! is_file($pathLink) && is_file($ref)) {
                @symlink($ref, $pathLink);
            }
        }

        foreach ($dels as $del) {
            $pathLink = "$this->outputPath/" . $this->bringFileName($del);

            if (is_link($pathLink)) {
                echo "Drop $del\n";
                unlink($pathLink);
            }
        }
    }
}

$dir = array_shift($argv);
$brings = [];

foreach ($argv as $arg) {
    if (\is_numeric($arg))
        $sleep = (int) $arg;
    else
        $brings[] = new BringIt($arg);
}

if (empty($brings))
    $brings[] = new BringIt('outputs');

if (null === $sleep)
    $sleep = 1;

$sleep = (int) $sleep;

if ($sleep < 0)
    $sleep = 1;

if ($sleep === 0)
    foreach ($brings as $bring)
        $bring->scan();
else
    for (;;) {
        foreach ($brings as $bring)
            $bring->scan();
        sleep($sleep);
    }