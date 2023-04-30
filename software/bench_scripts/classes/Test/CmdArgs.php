<?php
namespace Test;

final class CmdArgs implements \ArrayAccess
{

    private array $default;

    private array $javaDefault;

    private array $parsed;

    private const cmdArgsDef = [
        'debug' => true,
        'server.name' => 'localhost',
        'server.db' => 'treeforce',
        'server.collection' => null,
        'server.url' => null,
        'documentstore' => 'MongoDB',
        'generate-dataset' => true,
        'cmd-display-output' => false,
        'write-all-partitions' => true,
        'skip-summary-check' => false,
        'clean-ds' => false,
        'clean-db' => false,
        'pre-clean-ds' => false,
        'pre-clean-db' => false,
        'post-clean-ds' => false,
        'post-clean-db' => false,
        'summary' => 'label',
        'toNative_summary' => null, // Must be null for makeConfig()
        'native' => '',
        'cmd' => 'querying',
        'parallel' => false,
        'doonce' => false,
        'print-java-config' => false,
        'cold' => false,
        'output' => null,
        'skip-existing' => true,
        'plot' => '',
        'forget-results' => false,
        'sort-measure' => null,
        'timeout-order-queries' => null,
        'bench-measures-nb' => 1
        // 'bench-measures-forget' => 0
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

    public static function cmd(array $cmdArgsDef): CmdArgs
    {
        return new self($cmdArgsDef, []);
    }

    private const expandables = [
        'args' => [
            'summary',
            'parallel',
            'bench-measures-nb'
        ],
        'javaProperties' => [
            'query.batchSize',
            'data.batchSize',
            'query.batches.nbThreads',
            'querying.filter',
            'summary.filter.stringValuePrefix'
        ]
    ];

    public static function expandables(): array
    {
        return self::expandables;
    }

    public function expand(): array
    {
        $ret = $this->_expand();

        if (empty($ret))
            return [
                $this
            ];

        return $ret;
    }

    private function _expand(): array
    {
        foreach (self::expandables as $group => $expandables) {
            $parsed = $this->parsed[$group];

            foreach ($expandables as $expandk) {

                if (\is_array($parsed[$expandk])) {
                    $ret = [];

                    foreach ($parsed[$expandk] as $val) {
                        $newExpand = clone $this;
                        $newExpand[$group][$expandk] = $val;
                        $subExpand = $newExpand->_expand();

                        if (empty($subExpand))
                            $ret[] = $newExpand;
                        else
                            $ret = \array_merge($ret, $subExpand);
                    }
                    return $ret;
                }
            }
        }
        return [];
    }

    // ========================================================================
    public function parse(array $argv): array
    {
        $args = $this->default;
        $cmdRemains = \updateArray_getRemains($argv, $args, mapArgKey_default(fn ($k) => ($k[0] ?? '') !== 'P'));

        $dataSets = \array_filter_shift($cmdRemains, 'is_int', ARRAY_FILTER_USE_KEY);
        $javaProperties = self::shiftJavaProperties($cmdRemains);

        if (! empty($cmdRemains)) {
            $usage = "\nValid cli arguments are:\n" . \var_export($args, true);

            if (! empty($this->javaDefault))
                $usage .= "\nor a Java property of the form P#prop=#val:\n" . \var_export($this->javaDefault, true) . "\n";

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

    public function offsetSet($offset, $value)
    {
        $this->parsed[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->parsed[$offset]);
    }

    public function offsetUnset($offset)
    {
        if (isset($this->parsed[$offset]))
            $this->parsed[$offset] = null;
    }

    public function &offsetGet($offset)
    {
        return $this->parsed[$offset];
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