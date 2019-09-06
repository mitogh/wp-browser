<?php

namespace Codeception\Lib\Generator;

use Codeception\Configuration;
use Codeception\Lib\Generator\Shared\Classname;
use Codeception\TestCase\WPTestCase;
use Codeception\Util\Shared\Namespaces;
use Codeception\Util\Template;

class WPUnit extends AbstractGenerator implements GeneratorInterface
{
    use Classname;
    use Namespaces;

    protected $settings;
    protected $name;
    protected $baseClass;
    protected $template = <<<EOF
<?php
{{namespace}}
class {{name}}Test extends {{baseClass}}
{
    {{tester}}
    public function setUp(){{voidReturnType}}
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(){{voidReturnType}}
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    // Tests
    public function test_it_works()
    {
        \$post = static::factory()->post->create_and_get();
        
        \$this->assertInstanceOf(\\WP_Post::class, \$post);
    }
}

EOF;

    public function __construct($settings, $name, $baseClass)
    {
        parent::__construct($settings);
        $this->settings = $settings;
        $this->name = $this->removeSuffix($name, 'Test');
        $this->baseClass = $baseClass;
    }

    public function produce()
    {
        $ns = $this->getNamespaceHeader($this->settings['namespace'] . '\\' . $this->name);

        $phpunitSeries = getenv('WPBROWSER_PHPUNIT_SERIES');

        if (empty($phpunitSeries) && class_exists(WPTestCase::class)) {
            // Re-run the check after the auto-loading has autoloaded the file.
            $phpunitSeries = getenv('WPBROWSER_PHPUNIT_SERIES');
        }

        /** @var string $phpunitSeries */
        $voidReturnType = is_string($phpunitSeries) && version_compare($phpunitSeries, '8.0', '<') ?
            ''
            : ': void';

        return (new Template($this->template))->place('namespace', $ns)
            ->place('baseClass', $this->baseClass)
            ->place('name', $this->getShortClassName($this->name))
            ->place('voidReturnType', $voidReturnType)
            ->place('tester', $this->getTester())
            ->produce();
    }

    protected function getTester()
    {
        if (is_array($this->settings) && isset($this->settings['actor'])) {
            $actor = $this->settings['actor'];
        }

        try {
            $config = Configuration::config();
            $propertyName = isset($config['actor_suffix']) ?
                lcfirst($config['actor_suffix'])
                : '';
        } catch (\Exception $e) {
            $propertyName = '';
        }

        if (!isset($actor)) {
            return '';
        }

        $testerFrag = <<<EOF
    /**
     * @var \\$actor
     */
    protected $$propertyName;
    
EOF;

        return ltrim($testerFrag);
    }
}
