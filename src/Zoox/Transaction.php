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
    private $znode;

    private $participants = [];
    private $transaction;
    private $transactionStatus = [];

    private $body = '';
    private $acls = [
        [
            'perms' => \Zookeeper::PERM_ALL,
            'scheme' => 'world',
            'id' => 'anyone',
        ],
    ];

    public const STATUS_INITIAL = 0;
    public const STATUS_ABORT = 1;
    public const STATUS_AGREEMENT = 2;
    public const STATUS_COMMIT = 4;
    public const STATUS_ROLLBACK = 8;
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


            $this->znode = UUID::generate();
            $this->participantNode = $this->participantPath.'/'.$this->znode;
            echo "participantNode: ".$this->participantNode.PHP_EOL;

            $this->getZookeeper()->create($this->participantNode, '', $this->acls, 0);

            $this->participantChannel = new Queue($this->participantNode);
            $this->participantChannel->setZookeeper($this->getZookeeper());
            //must give agree or abort in callback
            $this->participantChannel->listen([$this, 'prepareCallback']);

            //$this->getZookeeper()->get($this->participantPath."/".$this->participantNode, [$this, "prepareCallback"]);
        }

        $children = $this->getZookeeper()->getChildren($this->participantPath, [$this, 'participateCallback']);
        if (!empty($children)) {
            foreach ($children as $child) {
                //also add itself
                if ($child === $child) {
                    $this->participants[$child] = $this->participantChannel;
                } else {
                    $channel = new Queue($this->participantPath.'/'.$child);
                    $channel->setZookeeper($this->getZookeeper());
                    $this->participants[$child] = $channel;
                }
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
        echo time().": preparecallback: ".$data.PHP_EOL;
        [$znode, $uuid] = $this->parse($data);
        //prepare
        $this->getZookeeper()->exists($this->transactionPath.'/'.$uuid.'/process', [$this, 'processCallback']);
        $this->getZookeeper()->exists($this->transactionPath.'/'.$uuid.'/commit', [$this, 'commitCallback']);
        $this->getZookeeper()->exists($this->transactionPath.'/'.$uuid.'/rollback', [$this, 'rollbackCallback']);
        $result = $this->prepare($data);
        //$this->transactionStatus[$uuid][$znode] = $result;
        $this->getZookeeper()->set($data, (string) $result);
        //quit quickly
        //if ($result === self::STATUS_ABORT) //prepare rollback;
    }

    public function processCallback(int $eventType, int $state, string $name): void
    {
        //it's ready node, so znode should use itself
        if (\Zookeeper::CREATED_EVENT === $eventType) {
            [$znode, $uuid] = $this->parse($name);
            $body = $this->getZookeeper()->get($name);
            //process
            $path = $this->transactionPath."/".$uuid."/".$this->znode;
            $this->getZookeeper()->set($path, (string) self::STATUS_INITIAL);
            $result = $this->process($body);
            echo time().": processCallback: ".$path." - ".$result.PHP_EOL;
            $this->getZookeeper()->set($path, (string) $result);
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
            $tansId = UUID::generate();
            $path = $this->transactionPath.'/'.$tansId;
            if (!$this->isExist($path)) {
                //prepare
                if ($this->makePath($path)) {
                    if (!empty($this->participants)) {
                        $this->transactionStatus[$tansId] = [];
                        foreach ($this->participants as $znode => $channel) {
                            $transPath = $path.'/'.$znode;
                            $this->makePath($transPath);
                            $this->transactionStatus[$tansId][$znode] = self::STATUS_INITIAL;
                            echo time().": notify: ".$transPath.PHP_EOL;
                            $this->getZookeeper()->exists($transPath, [$this, 'askCallback']);
                            $channel->enqueue($transPath);
                        }
                    }
                }
                $flag = false;
            } else {
                $flag = true;
            }
        } while ($flag);

        $this->transaction->enqueue($tansId);
    }
    public function askCallback(int $eventType, int $state, string $name): void
    {
        //lead receives prepare/process callback, update participant status
        $result = $this->getZookeeper()->get($name, [$this, 'askCallback']);
        echo time().": receive from ".$name." : ".$result.PHP_EOL;
        if (!empty($result)) {
            [$znode, $uuid] = $this->parse($name);
            $this->transactionStatus[$uuid][$znode] = (int) $result;
        }
    }

    public function wait(): void
    {
        $uuid = $this->transaction->dequeue();
        $start = \time();
        $secondPhase = self::STATUS_INITIAL;
        $firstPhase = self::STATUS_INITIAL;
        do {
            $transStatus = $this->transactionStatus[$uuid];
            foreach ($transStatus as $status) {
                if ((self::STATUS_ABORT === $status) || (self::STATUS_INITIAL === $status)) {
                    $firstPhase = $status;
                    break;
                }
                $firstPhase = self::STATUS_AGREEMENT;
            }

            if ((self::STATUS_AGREEMENT === $firstPhase) || (self::STATUS_ABORT === $firstPhase)) {
                break;
            }
            if ((\time() - $start) > self::TIMEOUT) {
                break;
            }
            \usleep(500);
        } while (true);
        if (self::STATUS_AGREEMENT === $firstPhase) {
            //$this->makePath($this->transactionPath.'/'.$uuid.'/process');
            $this->getZookeeper()->create($this->transactionPath.'/'.$uuid.'/process', $this->body, $this->acls, 0);
            do {
                $transStatus = $this->transactionStatus[$uuid];
                foreach ($transStatus as $status) {
                    $secondPhase = $status;
                    if ((self::STATUS_ROLLBACK === $status) || (self::STATUS_INITIAL === $status)) {
                        break;
                    }
                }

                if ((self::STATUS_ROLLBACK === $secondPhase) || (self::STATUS_COMMIT === $secondPhase)) {
                    break;
                }
                if ((\time() - $start) > self::TIMEOUT) {
                    break;
                }
                \usleep(500);
            } while (true);

        }
        if (self::STATUS_COMMIT === $secondPhase) {
            $this->makePath($this->transactionPath.'/'.$uuid.'/commit');
        } else {
            $this->makePath($this->transactionPath.'/'.$uuid.'/rollback');
        }
        //should rollback or do nothing
        //$this->deletePath($this->transactionPath.'/'.$uuid);
    }

    public function execute(string $body): void
    {
        //start
        //prepare
        //mark ready
        //process
        //mark commit/rollback
        //commit/rollback
        $this->body = $body;
        $this->start();
        $this->wait();
    }

    public function prepare($data): int
    {
        echo \time().': prepare'.PHP_EOL;
        //\call_user_func_array($this->prepareCallback, [$data]);
        return self::STATUS_AGREEMENT;
    }

    public function process($data)
    {
        echo \time().': process->'.$data.PHP_EOL;
        //\call_user_func_array($this->processCallback, [$data]);
        return self::STATUS_COMMIT;
    }

    public function commit($data): void
    {
        echo \time().': commit'.PHP_EOL;
        //\call_user_func_array($this->commitCallback, [$data]);
    }

    public function rollback($data): void
    {
        echo \time().': rollback'.PHP_EOL;
        //\call_user_func_array($this->rollbackCallback, [$data]);
    }

    public function __destruct()
    {
        //unset($this->participantChannel);
        //$this->deletePath($this->participantNode);
    }
}
