<?php

final class CSVReader
{

    private function __construct(string $filePath)
    {}

    public static function read($filePath): array
    {
        $ret = [];
        $file = new SplFileObject($filePath, "r");
        $file->setFlags(SPLFileObject::READ_CSV);

        foreach ($file as $line) {

            if (empty($line[0]))
                $group = null;
            elseif (! isset($group)) {
                $group = $line[0];
                $p = &$ret[$group];
            } else {
                $val = $line[1] ?? null;

                if (is_numeric($val))
                    $val = (int) $val;

                $p[$line[0]] = $val;
            }
        }
        return $ret;
    }

    public static function write(string $filePath, array $csvData): void
    {
        $file = new SplFileObject($filePath, "w");

        foreach ($csvData as $group => $items) {
            $file->fputcsv([
                $group
            ]);

            foreach ($items as $k => $item)
                $file->fputcsv([
                    $k,
                    $item
                ]);

            $file->fputcsv([]);
        }
    }
}