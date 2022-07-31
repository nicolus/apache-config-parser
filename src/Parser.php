<?php

namespace Nicolus\ApacheConfigParser;

class Parser
{
    public function __construct(private readonly string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Apche configuration file or directory does not exist ($configPath)");
        }
        if (!is_readable($configPath)) {
            throw new \RuntimeException("Apche configuration file or directory is not readable ($configPath)");
        }
    }

    /**
     * @return array<Host>
     */
    public function getHosts(): array
    {
        $hosts = [];
        $config = $this->getFullConfig($this->configPath);
        preg_match_all('~(?:^\s*|\n\s*)<VirtualHost[^>]*>(.*?)</VirtualHost>~is', $config, $matches);

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
     *
     * @param string $filePath
     * @return string the fully concatenated Apache config
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

                if (str_ends_with($include, '/')) {
                    $files = $this->getFilesRecursive($include);
                } else {
                    // Use glob which kinda works like Apache's '*' syntax, and only keep files (not directories)
                    $files = array_filter(glob($include), 'is_file');
                }

                foreach ($files as $filePath) {
                    $content .= $this->getFullConfig($filePath);
                }

                return $content;
            },
            $config_text
        );
    }

    /**
     * Get all files in a directory and in all nested directories
     *
     * @param string $path
     * @return \RecursiveIteratorIterator
     */
    private function getFilesRecursive(string $path): \RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME)
        );
    }
}

