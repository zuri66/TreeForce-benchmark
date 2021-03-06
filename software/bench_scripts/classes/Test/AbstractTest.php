<?php
namespace Test;

abstract class AbstractTest
{

    protected array $errors = [];

    protected \DataSet $ds;

    protected array $partitions;

    protected CmdArgs $cmdParser;

    private \XMLLoader $xmlLoader;

    public abstract function execute();

    public function __construct(\DataSet $ds, CmdArgs $cmdParser, \Data\IPartition ...$partitions)
    {
        $this->ds = $ds;
        $this->partitions = $partitions;

        if (! empty($collection) && ! \in_array($collectionName, $ds->getCollections()))
            throw new \Exception("$ds does not have the collection $collectionName");

        $this->cmdParser = $cmdParser;
        $this->xmlLoader = \XMLLoader::of($ds);
    }

    public final function getCollectionsName(): array
    {
        return \array_unique(\array_map(fn ($p) => $p->getCollectionName(), $this->partitions));
    }

    public final function collectionsExists(): bool
    {
        return \MongoImport::collectionsExists($this->getCollectionsName());
    }

    public final function dropCollections(string $clean = "*.json"): void
    {
        if ($this->cmdParser['args']['write-all-partitions'])
            \MongoImport::dropDataset($this->ds);
        else
            \MongoImport::dropCollections($this->getCollectionsName());

        if (! empty($clean))
            $this->xmlLoader->clean($clean);
    }

    public final function loadIndex(string $indexName): void
    {
        foreach ($this->getCollectionsName() as $coll)
            \MongoImport::createIndex($coll, $indexName);
    }

    public final function loadCollections(): void
    {
        $this->xmlLoader->convert();

        if ($this->cmdParser['args']['write-all-partitions'])
            \MongoImport::importDataset($this->ds);
        else
            \MongoImport::importCollections($this->ds, $this->getCollectionsName());
    }

    public final function reportErrors(?array $errors = null): void
    {
        $errors = $errors ?? $this->errors;

        if (empty($errors))
            return;

        \ob_start();
        echo "\n== Error reporting ==\n\n";

        foreach ($errors as $err) {
            $collections = \implode(',', $err['collections']);
            echo "= {$err['dataset']}/{$collections} =\n{$err['exception']->getMessage()}\n{$err['exception']->getTraceAsString()}\n\n";
        }
        \fwrite(STDERR, \ob_get_clean());
    }

    public final function getErrors(): array
    {
        return $this->errors;
    }
}