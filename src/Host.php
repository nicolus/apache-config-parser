<?php

namespace Nicolus\ApacheConfigParser;

class Host
{
    public function __construct(
        public readonly string $name,
        public readonly string $root,
        public readonly array $aliases = []
    )
    {}
}
