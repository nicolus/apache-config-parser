<?php

use Nicolus\ApacheConfigParser\Host;
use Nicolus\ApacheConfigParser\Parser;
use PHPUnit\Framework\TestCase;

class ParseVhostTest extends TestCase {
    public function setUp(): void
    {
        parent::setUp();
        // Create an empty dir because we can't commit it via git :
        if (!file_exists(__DIR__ . '/fixtures/emptydir')) {
            mkdir(__DIR__ . '/fixtures/emptydir');
        }
    }

    public function testRetrievesVhost() {
        $parser = new Parser(__DIR__ . '/fixtures/foo.test.conf');

        $this->assertIsArray($hosts = $parser->getHosts());

        $host = $hosts[0];
        $this->assertEquals('foo.test', $host->name);
        $this->assertEquals('/var/www/foo/public/', $host->root);
        $this->assertEquals('80', $host->port);

        $aliases = $host->aliases;
        $this->assertIsArray($aliases);
        $this->assertEquals('www.foo.test', $aliases[0]);
        $this->assertEquals('www2.foo.test', $aliases[1]);
        $this->assertEquals('www3.foo.test', $aliases[2]);
    }

    public function testLoadsFiles() {
        $parser = new Parser(__DIR__ . '/fixtures/apache.conf');

        $this->assertIsArray($hosts = $parser->getHosts());
        $this->assertEquals('foo.test', $hosts[0]->name);
    }

    public function testLoadsAbsoluteFiles() {
        $configContent = file_get_contents(__DIR__ . '/fixtures/apache.conf');
        $configContent = str_replace(
            'foo.test.conf',
            __DIR__.'/fixtures/foo.test.conf',
            $configContent
        );
        file_put_contents(__DIR__ . '/fixtures/apacheabsolute.conf', $configContent);
        $parser = new Parser(__DIR__ . '/fixtures/apacheabsolute.conf');

        $this->assertIsArray($hosts = $parser->getHosts());
        $this->assertEquals('foo.test', $hosts[0]->name);
    }

    public function testLoadsDirectories()
    {
        $parser = new Parser(__DIR__ . '/fixtures/apache2.conf');

        $hosts = $parser->getHosts();
        $this->assertIsArray($hosts);
        $this->assertContainsOnlyInstancesOf(Host::class, $hosts);
        $this->assertHostsContain(['foo1.test', 'foo2.test'], $hosts);

    }

    public function testLoadsAbsoluteDirectories() {
        $configContent = file_get_contents(__DIR__ . '/fixtures/apache2.conf');
        $configContent = str_replace(
            'sites-available/',
            __DIR__.'/fixtures/sites-available/',
            $configContent
        );
        file_put_contents(__DIR__ . '/fixtures/apache2absolute.conf', $configContent);

        $parser = new Parser(__DIR__ . '/fixtures/apache2absolute.conf');

        $hosts = $parser->getHosts();
        $this->assertIsArray($hosts);
        $this->assertContainsOnlyInstancesOf(Host::class, $hosts);
        $this->assertHostsContain(['foo1.test', 'foo2.test', 'foo4.test'], $hosts);
    }

    public function testLoadsWithRegex() {
        $parser = new Parser(__DIR__ . '/fixtures/apacheregex.conf');

        $this->assertIsArray($hosts = $parser->getHosts());
        $this->assertHostsContain(['foo1.com', 'default.com'], $hosts);
    }

    public function testLoadsDirectoriesWithRegex() {
        $parser = new Parser(__DIR__ . '/fixtures/apacheregex2.conf');

        $this->assertIsArray($hosts = $parser->getHosts());
        $this->assertHostsContain(['foo4.test', 'foo5.test'], $hosts);
    }

    public function testLoadsAbsoluteDirectoriesWithRegex() {
        $configContent = file_get_contents(__DIR__ . '/fixtures/apacheregex.conf');
        $configContent = str_replace(
            'sites-available/*.com.conf',
            __DIR__.'/fixtures/sites-available/*.com.conf',
            $configContent
        );
        file_put_contents(__DIR__ . '/fixtures/apacheregexabsolute.conf', $configContent);

        $parser = new Parser(__DIR__ . '/fixtures/apacheregexabsolute.conf');

        $this->assertIsArray($hosts = $parser->getHosts());
        $this->assertHostsContain(['foo1.com', 'default.com'], $hosts);
    }



    /**
     * @param array $expected
     * @param array $hosts
     * @return void
     */
    public function assertHostsContain(array $expected, array $hosts): void
    {
        foreach ($expected as $search) {
            $found = false;
            foreach ($hosts as $host) {
                if ($host->name === $search) {
                    $found = true;
                }
            }
            if ($found === false) {
                $this->fail("did not find $search in the hosts");
            } else {
                $this->assertTrue(true);
            }
        }
    }

}
