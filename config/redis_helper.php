<?php

class RedisHelper
{
    private $redis = null;
    private $available = false;

    public function __construct()
    {
        try {
            if (class_exists('Redis')) {
                $this->redis = new Redis();
                $host = getenv('REDIS_HOST') ?: 'redis';
                $this->available = @$this->redis->connect($host, 6379, 2.5);
            }
        } catch (Throwable $e) {
            $this->available = false;
        }
    }

    public function isAvailable()
    {
        return $this->available;
    }

    public function get($key)
    {
        if (!$this->available) {
            return null;
        }
        try {
            $val = $this->redis->get($key);
            return $val !== false ? json_decode($val, true) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function set($key, $value, $ttl = 300)
    {
        if (!$this->available) {
            return false;
        }
        try {
            return $this->redis->setex($key, $ttl, json_encode($value));
        } catch (Throwable $e) {
            return false;
        }
    }

    public function delete($key)
    {
        if (!$this->available) {
            return false;
        }
        try {
            return $this->redis->del($key) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function remember($key, $callback, $ttl = 300)
    {
        if (!$this->available) {
            return $callback();
        }
        try {
            $val = $this->get($key);
            if ($val !== null) {
                return $val;
            }
        } catch (Throwable $e) {
        }
        $val = $callback();
        $this->set($key, $val, $ttl);
        return $val;
    }
}
