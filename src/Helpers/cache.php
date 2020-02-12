<?php

if (!function_exists('persist_cache')) {
    /**
     * @param string $key
     * @param mixed  $values
     * @param int    $minutes
     * @param array  $tags
     *
     * @return \Illuminate\Contracts\Cache\Repository
     * @throws Exception
     */
    function persist_cache(string $key, $values, int $minutes = 60, array $tags = [])
    {
        if (empty($tags)) {
            if (Cache::get($key)) {
                return Cache::get($key);
            }

            $expiresAt = now()->addMinutes($minutes);

            if (is_callable($values)) {
                $values = $values();
            }

            Cache::put($key, $values, $expiresAt);

            return $values;
        } else {
            if (Cache::tags($tags)->get($key)) {
                return Cache::tags($tags)->get($key);
            }

            if (is_callable($values)) {
                $values = $values();
            }

            $expiresAt = now()->addMinutes($minutes);
            Cache::tags($tags)->put($key, $values, $expiresAt);

            return $values;
        }
        return null;
    }
}
