<?php

declare(strict_types=1);

namespace Zoox;

class Transaction extends Base
{
    private $path;
    private $participantPath;
    private $participantNode;
    private $transactionPath;
    private $participantChannel;

    private $participants = [];
    private $transaction;
    private $transactionStatus = [];

    public const STATUS_INITIAL = 999;
    public const STATUS_ABORT = 1;
    public const STATUS_AGREEMENT = 2;
    public const STATUS_COMMIT = 3;
    public const STATUS_ROLLBACK = 4;
    public const TIMEOUT = 5;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->participantPath = $this->path.'/participant';
        $this->transactionPath = $this->path.'/transaction';
        $this->transaction = new \SplQueue();
    }

    public function join(): void
    {
        if (empty($this->participantNode)) {
            if (!$this->isExist($this->participantPath)) {
                $this->makePath($this->participantPath);
            }

            $acls = [
                [
                    'perms' => \Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id' => 'anyone',
                ],
            ];

            $this->participantNode = $this->participantPath.'/'.UUID::generate();

            $this->getZookeeper()->create($this->participantNode, '', $acls, 0);

            $this->participantChannel = new Queue($this->participantNode);
            $this->participantChannel->setZookeeper($this->getZookeeper());
            //must give agree or abort in callback
            $this->participantChannel->listen([$this, 'prepareCallback']);

            //$this->getZookeeper()->get($this->participantPath."/".$this->participantNode, [$this, "prepareCallback"]);
        }

        $children = $this->getZookeeper()->getChildren($this->participantPath, [$this, 'participateCallback']);
        if (!empty($children)) {
            foreach ($children as $child) {
                $channel = new Queue($this->participantPath.'/'.$child);
                $channel->setZookeeper($this->getZookeeper());
                $this->participants[$child] = $channel;
            }
        }
    }

    public function parse($path)
    {
        $paths = \explode('/', $path);
        $znode = \array_pop($paths);
        $uuid = \array_pop($paths);

        return [$znode, $uuid];
    }

    public function prepareCallback($data): void
    {
        [$znode, $uuid] = $this->parse($data);
        //prepare
        $result = $this->prepare($data);
        $this->transactionStatus[$uuid][$znode] = $result;
        $this->getZookeeper()->set($data, (string) $result);
        //if ($result === self::STATUS_ABORT) //prepare rollback;
        $this->getZookeeper()->exists($this->transactionPath.'/'.$uuid.'/process');
    }

    public function processCallback(int $eventType, int $state, string $name): void
    {
        if (\Zookeeper::CREATED_EVENT === $eventType) {
            [$znode, $uuid] = $this->parse($name);
            //process
            $result = $this->process($uuid);
            $this->getZookeeper()->set($name, $result);
            $this->getZookeeper()->exists($this->transactionPath.'/'.$uuid.'/commit');
            $this->getZookeeper()->exists($this->transactionPath.'/'.$uuid.'/rollback');
        }
    }

    public function commitCallback(int $eventType, int $state, string $name): void
    {
        if (\Zookeeper::CREATED_EVENT === $eventType) {
            [$znode, $uuid] = $this->parse($name);
            //commit
            $this->commit($uuid);
        }
    }

    public function rollbackCallback(int $eventType, int $state, string $name): void
    {
        if (\Zookeeper::CREATED_EVENT === $eventType) {
            [$znode, $uuid] = $this->parse($name);
            //rollback
            $this->rollback($uuid);
        }
    }

    public function participateCallback(int $eventType, int $state, string $name): void
    {
        $children = $this->getZookeeper()->getChildren($this->participantPath, [$this, 'participateCallback']);
        if (!empty($children)) {
            //TODO need a status lock in case of other callback is using it
            $this->participants = [];
            foreach ($children as $child) {
                $channel = new Queue($this->participantPath.'/'.$child);
                $channel->setZookeeper($this->getZookeeper());
                $this->participants[$child] = $channel;
            }
        }
    }

    public function start(): void
    {
        $flag = true;
        do {
            $uuid = UUID::generate();
            $path = $this->transactionPath.'/'.$uuid;
            if (!$this->isExist($path)) {
                //prepare
                if ($this->makePath($path)) {
                    if (!empty($this->participants)) {
                        $this->transactionStatus[$uuid] = [];
                        foreach ($this->participants as $znode => $channel) {
                            $transPath = $path.'/'.$znode;
                            $this->makePath($transPath);
                            $this->transactionStatus[$uuid][$znode] = self::STATUS_INITIAL;
                            $this->getZookeeper()->get($transPath, [$this, 'askCallback']);
                            $channel->enqueue($transPath);
                        }
                    }
                }
                $flag = false;
            } else {
                $flag = true;
            }
        } while ($flag);

        $this->transaction->enqueue($uuid);
    }

    public function askCallback(int $eventType, int $state, string $name): void
    {
        $result = $this->getZookeeper()->get($name, [$this, 'askCallback']);
        if (!empty($result)) {
            [$znode, $uuid] = $this->parse($name);
            $this->transactionStatus[$uuid][$znode] = (int) $result;
        }
    }

    public function mark(): void
    {
        $uuid = $this->transaction->dequeue();
        $start = \time();
        do {
            $firstPhase = self::STATUS_INITIAL;
            $transStatus = $this->transactionStatus[$uuid];
            foreach ($transStatus as $status) {
                if ((self::STATUS_ABORT === $status) || (self::STATUS_INITIAL === $status)) {
                    $firstPhase = $status;
                    break;
                }
                $firstPhase = self::STATUS_AGREEMENT;
            }

            if (self::STATUS_AGREEMENT === $firstPhase) {
                break;
            }
            if ((\time() - $start) > self::TIMEOUT) {
                break;
            }
            \usleep(500);
        } while (true);
        if (self::STATUS_AGREEMENT === $firstPhase) {
            $rollback = true;
            $this->makePath($this->transactionPath.'/'.$uuid.'/process');
            //process myself
            do {
                $secondPhase = self::STATUS_INITIAL;
                $transStatus = $this->transactionStatus[$uuid];
                foreach ($transStatus as $status) {
                    $secondPhase = $status;
                    if (self::STATUS_ROLLBACK === $status) {
                        break;
                    }
                }

                if (self::STATUS_ROLLBACK === $secondPhase) {
                    break;
                }
                if ((\time() - $start) > self::TIMEOUT) {
                    break;
                }
                \usleep(500);
            } while (true);

            if (self::STATUS_COMMIT === $secondPhase) {
                $rollback = false;
                $this->makePath($this->transactionPath.'/'.$uuid.'/commit');
            }
            if ($rollback) {
                $this->makePath($this->transactionPath.'/'.$uuid.'/rollback');
            }
        }

        $this->deletePath($this->transactionPath.'/'.$uuid);
    }

    public function proceed(string $body): void
    {
        //start
        //prepare
        //mark ready
        //process
        //mark commit/rollback
        //commit/rollback
        $this->start();
        $this->mark();
    }

    public function prepare($data): int
    {
        echo \time().': prepare'.PHP_EOL;
        //\call_user_func_array($this->prepareCallback, [$data]);
        return self::STATUS_AGREEMENT;
    }

    public function process($data)
    {
        echo \time().': process'.PHP_EOL;
        //\call_user_func_array($this->processCallback, [$data]);
        return self::STATUS_AGREEMENT;
    }

    public function commit($data): void
    {
        echo \time().': commit'.PHP_EOL;
        //\call_user_func_array($this->commitCallback, [$data]);
    }

    public function rollback($data): void
    {
        echo \time().': process'.PHP_EOL;
        //\call_user_func_array($this->rollbackCallback, [$data]);
    }

    public function __destruct()
    {
        $this->deletePath($this->participantPath.'/'.$this->participantNode);
    }
}
