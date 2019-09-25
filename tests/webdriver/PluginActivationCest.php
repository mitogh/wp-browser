<?php


class PluginActivationCest
{

    /**
     * @var array
     */
    protected $delete = [];

    public function _before(WebdriverTester $I)
    {
        $this->deleteFiles($I);
    }

    protected function deleteFiles(WebdriverTester $I)
    {
        foreach ($this->delete as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (count($this->delete)) {
            $I->waitForFileToNotExist(end($this->delete));
        }
    }

    public function _after(WebdriverTester $I)
    {
        $this->deleteFiles($I);
    }

    public function _failed(WebdriverTester $I)
    {
        $this->deleteFiles($I);
    }

    /**
     * It should be able to activate plugins
     *
     * @test
     */
    public function be_able_to_activate_plugins(WebdriverTester $I)
    {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();

        $I->activatePlugin('hello-dolly');
        $I->seePluginActivated('hello-dolly');

        $I->deactivatePlugin('hello-dolly');
        $I->seePluginDeactivated('hello-dolly');
    }

    /**
     * It should be able to activate plugins in a long list
     *
     * @test
     */
    public function be_able_to_activate_plugins_in_a_long_list(WebdriverTester $I)
    {
        $this->scaffoldTestPlugins($I);

        $I->loginAsAdmin();
        $I->amOnPluginsPage();

        $I->activatePlugin('plugin-z');
        $I->seePluginActivated('plugin-z');

        $I->deactivatePlugin('plugin-z');
        $I->seePluginDeactivated('plugin-z');
    }

    protected function scaffoldTestPlugins(WebdriverTester $I)
    {
        $template
            = <<< HANDLEBARS
/*
Plugin Name: Plugin {{letter}}
Plugin URI: https://wordpress.org/plugins/{{letter}}/
Description: Plugin {{letter}} description
Version: 0.1.0
Author: Plugin {{letter}} author
Author URI: http://example.com/{{letter}}-plugin
Text Domain: {{letter}}_plugin
Domain Path: /languages
*/
HANDLEBARS;

        foreach (range('A', 'Z') as $letter) {
            $compiled = str_replace('{{letter}}', $letter, $template);
            $I->havePlugin("plugin-{$letter}/plugin-{$letter}.php", $compiled);
        }

        $I->waitForFileToExist($I->getWpPluginsPath('plugin-Z/plugin-Z.php'));
    }

    /**
     * It should be able to activate multiple plugins
     *
     * @test
     */
    public function be_able_to_activate_multiple_plugins(WebdriverTester $I)
    {
        $this->scaffoldTestPlugins($I);

        $I->loginAsAdmin();
        $I->amOnPluginsPage();

        $plugins = ['plugin-a', 'plugin-b', 'plugin-i', 'plugin-o', 'plugin-z'];

        $I->activatePlugin($plugins);
        foreach ($plugins as $plugin) {
            $I->seePluginActivated($plugin);
        }

        $I->deactivatePlugin($plugins);
        foreach ($plugins as $plugin) {
            $I->seePluginDeactivated($plugin);
        }
    }
}
