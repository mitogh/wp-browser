# Waiter module

This module should be used when, during tests, some state transitions might not happen immediately.  

An example of this are files that should eventually exist, or not exist.  

## Configuration

* `timeout` defaults to `3` seconds - how many seconds to wait for something to happen before throwing an exception.
* `interval` - defaults to `.5` seconds - how long to wait between each check, in seconds.

### Example configuration

```yaml
class_name: WebdriverTester
modules:
  enabled:
    - WPWebDriver
    - WPFilesystem
    - Waiter
  config:
    WPWebDriver:
      url: 'http://wp.test'
      adminUsername: 'admin'
      adminPassword: 'admin'
      adminPath: '/wp-admin'
      browser: chrome
      host: localhost
      port: 4444
      window_size: false
      capabilities:
        chromeOptions:
          args: ["--headless", "--disable-gpu", "--proxy-server='direct://'", "--proxy-bypass-list=*"]
    WPFilesystem:
      wpRootFolder: '/var/www/html'
    Waiter:
      timeout: 5
      interval: .25
```

<!--doc-->


## Public API
<nav>
	<ul>
		<li>
			<a href="#waitfor">waitFor</a>
		</li>
		<li>
			<a href="#waitforfiletoexist">waitForFileToExist</a>
		</li>
		<li>
			<a href="#waitforfiletonotexist">waitForFileToNotExist</a>
		</li>
	</ul>
</nav>

<h3>waitFor</h3>

<hr>

<p>Waits for a condition to come true, else it fails when the time runs out.</p>
<pre><code class="language-php">    // Wait for a specific condition.
    $optionExists = static function(){
            return (bool)get_option('some_option');
    };
    $else = static function(){
            throw new \RuntimeException('Option is empty after waiting.');
    }
    $I-&gt;waitFor($optionExists, $else);</code></pre>
<pre><code>                       evaluated as a boolean.</code></pre>
<h4>Parameters</h4>
<ul>
<li><code>\callable</code> <strong>$check</strong> - The check function to run to know if the expected effect came to be or not. This wil be</li>
<li><code>\callable</code> <strong>$onFailure</strong> - What should be done when, and if, the check fails.</li></ul>
  

<h3>waitForFileToExist</h3>

<hr>

<p>Waits for a file to exist.</p>
<pre><code class="language-php">    // Wait for a plugin file to exist.
    $file = '/var/www/html/wp-content/plugins/my-plugin/plugin.php';
    $I-&gt;waitForFileToExist($file);</code></pre>
<h4>Parameters</h4>
<ul>
<li><code>string</code> <strong>$file</strong> - The file to wait for.</li></ul>
  

<h3>waitForFileToNotExist</h3>

<hr>

<p>Waits for a file to not exist.</p>
<pre><code class="language-php">    // Wait for a plugin file to be deleted.
    $file = '/var/www/html/wp-content/plugins/my-plugin/plugin.php';
    $I-&gt;waitForFileToNotExist($file);</code></pre>
<h4>Parameters</h4>
<ul>
<li><code>string</code> <strong>$file</strong> - The path to the file that should, eventually, not exist.</li></ul>


*This class extends \Codeception\Module*

<!--/doc-->
