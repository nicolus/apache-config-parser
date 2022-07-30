<?php

namespace Nicolus\ApacheConfigParser;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class Parser
{

    /**
     * @var string
     */
    private string $config;


    public function __construct(string $configFilePath)
    {
        if (!file_exists($configFilePath)) {
            throw new RuntimeException("Apche configuration file does not exist ($configFilePath)");
        }
        if (!is_readable($configFilePath)) {
            throw new RuntimeException("Apache configuration file is not readable ($configFilePath)");
        }

        $this->config = $this->getFullConfig($configFilePath);
    }

    /**
     * @return array<Host>
     * @throws Exception
     */
    public function getApacheHosts(): array
    {
        $hosts = [];
        preg_match_all('~(?:^\s*|\n\s*)<VirtualHost[^>]*>(.*?)</VirtualHost>~is', $this->config, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $vhost) {
                $server_name_match = [];
                preg_match('~(?:^\s*|\n\s*)ServerName\s+(?:"|)([^"\s:]*)(?:"|)~i', $vhost, $server_name_match);

                if (!empty($server_name_match[1])) {
                    preg_match('~(?:^\s*|\n\s*)DocumentRoot\s+(?:"|)([^"\s]*)(?:"|)~i', $vhost, $doc_root_match);
                    preg_match_all('~(^\s*|\n\s*)ServerAlias\s+(?:"|)([^"\n]*)(?:"|)~i', $vhost, $aliases_match);

                    $aliases = [];
                    foreach ($aliases_match[2] as $alias) {
                        $split_aliases = preg_split('~\s+~', $alias);
                        foreach ($split_aliases as $split_alias) {
                            $aliases[] = $split_alias;
                        }
                    }

                    $hosts[] = new Host(
                        $server_name_match[1],
                        $doc_root_match[1],
                        $aliases
                    );
                }
            }
        }

        return $hosts;
    }


    /**
     * Looks for Include and includeOption directives in the config
     * and recursively replaces them with the contents of included files
     * @param string $filePath
     * @return string
     */
    private function getFullConfig(string $filePath): string
    {
        $config_dir = dirname($filePath);
        $config_text = file_get_contents($filePath);

        return preg_replace_callback(
            '~(^\s*Include(?:Optional)?\s+("|)([^"\n]*)("|)(\n|$))~Um',
            function ($matches) use ($config_dir) {
                $content = '';
                $include = $matches[3];

                if (empty($include)) {
                    return $content;
                }

                // Relative path => turn it into an absolute path
                if (!str_starts_with($include, '/')) {
                    $include = $config_dir . '/' . $include;
                }

                // Directory => Add a final * to glob everything
                if (str_ends_with($include, '/')) {
                    $include .= '*';
                }

                $files = array_filter(glob($include), 'is_file');

                foreach ($files as $filePath) {
                    $content .= $this->getFullConfig($filePath);
                }

                return $content;
            },
            $config_text
        );
    }
}
