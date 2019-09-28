<?php

declare(strict_types=1);

namespace Zoox;

class Lock extends Base
{
    private $path;
    private $callback;

    public function __construct(string $path)
    {
        //every object should have an uuid here
        $this->path = $path;
    }

    public function aquire(int $timeout = 0)
    {
        $start = \time();
        while ($this->isExist($this->path)) {
            if (($timeout > 0) && ((\time() - $start) > $timeout)) {
                return false;
            }
            \usleep(\random_int(600, 1000));
        }
        $result = false;
        while (!$result) {
            if (($timeout > 0) && ((\time() - $start) > $timeout)) {
                return false;
            }

            try {
                $znode = $this->makePath($this->path);
                $result = true;
            } catch (\Exception $e) {
                //keep try
                \usleep(\random_int(600, 1000));
            }
        }

        return $result;
    }

    public function release(): void
    {
        $this->deletePath($this->path);
    }
}
