<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Restart;

use DateTime;
use InvalidArgumentException;

/**
 * Class RedisRestart provides redis implementation of Tomaj\Hermes\Restart\RestartInterface
 *
 * Set UNIX timestamp (as `string`) to key `$key` (default `hermes_restart`) to restart Hermes.
 */
class RedisRestart implements RestartInterface
{
    /** @var string */
    private $key;

    /** @var \Predis\Client|\Redis */
    private $redis;

    public function __construct($redis, string $key = 'hermes_restart')
    {
        if (!(($redis instanceof \Predis\Client) || ($redis instanceof \Redis))) {
            throw new InvalidArgumentException('Predis\Client or Redis instance required');
        }

        $this->key = $key;
        $this->redis = $redis;
    }

    /**
     * {@inheritdoc}
     *
     * Returns true:
     *
     * - if restart timestamp is set,
     * - and timestamp is not in future,
     * - and hermes was started ($startTime) before timestamp
     */
    public function shouldRestart(DateTime $startTime): bool
    {
        // load UNIX timestamp from redis
        $restartTime = $this->redis->get($this->key);
        if ($restartTime === null) {
            return false;
        }
        $restartTime = (int) $restartTime;

        // do not restart if restart time is in future
        if ($restartTime > time()) {
            return false;
        }

        // do not restart if hermes started after restart time
        if ($restartTime < $startTime->getTimestamp()) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Sets to Redis value `$restartTime` (or current DateTime) to `$key` defined in constructor.
     */
    public function restart(DateTime $restartTime = null): bool
    {
        if ($restartTime === null) {
            $restartTime = new DateTime();
        }

        $response = $this->redis->set($this->key, $restartTime->format('U'));

        if ($this->redis instanceof \Redis) {
            // \Redis::set() returns TRUE/FALSE
            return $response;
        }

        if ($this->redis instanceof \Predis\Client) {
            /** @var \Predis\Response\Status $response */
            return $response->getPayload() === 'OK';
        }
    }
}
