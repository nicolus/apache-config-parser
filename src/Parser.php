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
        $matches = [];
        preg_match_all('~(?:^\s*|\n\s*)<VirtualHost[^>]*>(.*?)</VirtualHost>~is', $this->config, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $v_host) {
                $server_name_match = [];
                preg_match('~(?:^\s*|\n\s*)ServerName\s+(?:"|)([^"\s:]*)(?:"|)~i', $v_host, $server_name_match);

                if (!empty($server_name_match[1])) {
                    $doc_root_match = [];
                    preg_match('~(?:^\s*|\n\s*)DocumentRoot\s+(?:"|)([^"\s]*)(?:"|)~i', $v_host, $doc_root_match);

                    $aliases_match = [];
                    preg_match_all('~(^\s*|\n\s*)ServerAlias\s+(?:"|)([^"\n]*)(?:"|)~i', $v_host, $aliases_match);

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
     * @param string $config_path
     * @return string
     */
    private function getFullConfig(string $config_path): string
    {
        $config_dir = dirname($config_path);
        $config_text = file_get_contents($config_path);

        return preg_replace_callback(
            '~(^\s*Include(?:Optional)?\s+("|)([^"\n]*)("|)(\n|$))~Um',
            function ($matches) use ($config_dir) {
                $content = '';
                $include = $matches[3];

                if (empty($include)) {
                    return $content;
                }

                $isDirectory = str_ends_with($include, '/');
                $isAbsolutePath = str_starts_with($include, '/');
                $hasWildCard = str_contains($include, '*');

                if ($isAbsolutePath && $isDirectory) {
                    $configs = $this->getConfigs($include);
                    foreach ($configs as $config_path) {
                        $content .= $this->getFullConfig($config_path);
                    }
                    return $content;
                }

                if ($isAbsolutePath) {
                    if ($hasWildCard) {
                        $sub_dir = $this->getBasePath($include);

                        $configs = $this->getConfigs($sub_dir);
                        foreach ($configs as $config_path) {
                            if ($this->pathMatchesRegex($include, $config_path)) {
                                $content .= $this->getFullConfig($config_path);
                            }
                        }

                        return $content;
                    }

                    return $this->getFullConfig($include);
                }

                if ($isDirectory) {
                    $configs = $this->getConfigs($config_dir . '/' . substr($include, 0, -1));
                    foreach ($configs as $config_path) {
                        $content .= $this->getFullConfig($config_path);
                    }

                    return $content;
                }

                // File with path from current directory
                // Search for configs by regular expression
                if ($hasWildCard) {
                    $sub_dir = $this->getBasePath($include);

                    $configs = $this->getConfigs($config_dir . '/' . $sub_dir);
                    foreach ($configs as $config_path) {
                        if ($this->pathMatchesRegex($include, $config_path)) {
                            $content .= $this->getFullConfig($config_path);
                        }
                    }

                    return $content;
                }

                return $this->getFullConfig($config_dir . '/' . $include);
            },
            $config_text
        );
    }


    /**
     * Get configuration files in a directory and in all nested directories
     * @param string $dir_path
     * @return RecursiveIteratorIterator<FilesystemIterator>
     */
    private function getConfigs(string $dir_path): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME)
        );
    }


    private function pathMatchesRegex(string $include, string $config_path): bool
    {
        $regex_include = str_replace(
            ['~', '.', '*'],
            ['\~', '\.', '.*'],
            $include
        );

        return preg_match("~$regex_include$~U", $config_path);
    }

    /**
     * @param mixed $include
     * @return false|string
     */
    function getBasePath(mixed $include): string|false
    {
        $star_pos = strpos($include, '*');
        $sub_dir = substr($include, 0, $star_pos);
        $sub_dir = strrpos($sub_dir, '/');
        $sub_dir = substr($include, 0, $sub_dir);
        $sub_dir = realpath($sub_dir);
        return $sub_dir;
    }
}
