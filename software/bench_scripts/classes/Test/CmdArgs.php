<?php
namespace Test;

final class CmdArgs
{

    private array $default;

    private array $javaDefault;

    private array $parsed;

    private const cmdArgsDef = [
        'generate-dataset' => true,
        'cmd-display-output' => false,
        'clean-db' => false,
        'pre-clean-db' => false,
        'post-clean-db' => false,
        'summary' => 'key',
        'toNative_summary' => null, // Must be null for makeConfig()
        'native' => '',
        'cmd' => 'querying',
        'doonce' => false,
        'cold' => false,
        'output' => null,
        'skip-existing' => true,
        'plot' => '',
        'forget-results' => false
    ];

    // ========================================================================
    private function __construct(array $cmdArgsDef, array $javaPropertiesDef)
    {
        $this->default = $cmdArgsDef;
        $this->javaDefault = $javaPropertiesDef;
    }

    public static function default(): CmdArgs
    {
        $ret = new self(self::cmdArgsDef, self::getDefaultJavaProperties());
        $ret->parse([]);
        return $ret;
    }

    // ========================================================================
    public function parse(array $argv): array
    {
        $args = $this->default;
        $cmdRemains = \updateArray_getRemains($argv, $args, mapArgKey_default(fn ($k) => ($k[0] ?? '') !== 'P'));

        $dataSets = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);
        $javaProperties = self::shiftJavaProperties($cmdRemains);

        if (! empty($cmdRemains)) {
            $usage = "\nValid cli arguments are:\n" . \var_export($args, true) . //
            "\nor a Java property of the form P#prop=#val:\n" . \var_export($this->javaDefault, true) . "\n";
            fwrite(STDERR, $usage);
            throw new \Exception("Unknown cli argument(s):\n" . \var_export($cmdRemains, true));
        }
        $dataSets = \array_unique(\DataSets::all($dataSets));

        $this->parsed = [
            'dataSets' => $dataSets,
            'args' => $args,
            'javaProperties' => $javaProperties
        ];
        return $this->parsed;
    }

    public function parsed(): array
    {
        return $this->parsed;
    }

    // ========================================================================
    private static function getDefaultJavaProperties(): array
    {
        return (include \getPHPScriptsBasePath() . '/benchmark/config/common.php')['java.properties'];
    }

    private static function shiftJavaProperties(array &$args): array
    {
        $ret = self::getDefaultJavaProperties();

        foreach ($cp = $args as $k => $v) {
            if (! is_string($k))
                continue;
            if ($k[0] !== 'P')
                continue;

            $prop = substr($k, 1);
            if (! \array_key_exists($prop, $ret))
                continue;
            if (is_bool($v))
                $v = $v ? 'y' : 'n';

            $ret[$prop] = $v;
            unset($args[$k]);
        }
        return $ret;
    }
}