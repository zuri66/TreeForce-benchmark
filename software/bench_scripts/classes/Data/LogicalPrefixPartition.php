<?php
namespace Data;

final class LogicalPrefixPartition extends LogicalPartition
{

    private \DataSet $ds;

    private string $prefix_s;

    private array $prefix;

    private string $cname;

    public function __construct(\DataSet $ds, string $collectionName, string $id, string $prefix)
    {
        parent::__construct($id);
        $this->ds = $ds;
        $this->cname = $collectionName;
        $this->prefix = \explode('.', $prefix);
        $this->prefix_s = $prefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix_s;
    }

    private function getRangeFilePath(): string
    {
        return "partition.{$this->getID()}.txt";
    }

    public function getLogicalRange(): array
    {
        $fpath = $this->getRangeFilePath();

        \wdPush($this->ds->path());

        if (! \is_file($fpath))
            $ret = [];
        else {
            $contents = \file_get_contents($fpath);
            \preg_match_all('#\d+#', $contents, $matches);
            $ret = $matches[0];
        }
        \wdPop();
        return $ret;
    }

    public function getCollectionName(): string
    {
        return $this->cname;
    }

    public function contains(array $data): bool
    {
        $noPrefix = (object) null;
        $f = \array_follow($data, $this->prefix, $noPrefix);
        return $f !== $noPrefix;
    }
}