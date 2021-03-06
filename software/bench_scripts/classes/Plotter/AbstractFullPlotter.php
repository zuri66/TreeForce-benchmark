<?php
namespace Plotter;

abstract class AbstractFullPlotter implements IPlotter
{

//     protected bool $xtics_pretty = true;

//     protected bool $xtics_infos = true;

//     protected bool $xtics_infos_answers_nb = true;

//     private const factors = [
//         'K' => 10 ** 3,
//         'M' => 10 ** 6,
//         'G' => 10 ** 9
//     ];

//     protected static function nbFromGroupName(string $groupName)
//     {
//         if (\preg_match('#^(\d+)([KMG])#', $groupName, $matches))
//             return (int) $matches[1] * (self::factors[$matches[2]] ?? 0);
//         return 0;
//     }

    protected function cleanCurrentDir()
    {
        foreach ($g = \glob('*.dat') as $file)
            \unlink($file);
    }
}
