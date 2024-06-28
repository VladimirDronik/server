<?php

use Beanstalk\Client;

class BeanstalkQueue extends System
{
    public static function putTask(string $tubeName, array $task, int $priority = 5)
    {
        $beanstalk = new Client();
        $beanstalk->connect();
        $beanstalk->useTube($tubeName);
        $beanstalk->put(
            $priority, // Give the job a priority
            0,  // Do not wait to put job into the ready queue.
            60, // Give the job 1 minute to run.
            json_encode($task) // The job's body.
        );
        $beanstalk->disconnect();
    }

    public static function startQueue(string $tubeName)
    {
            $beanstalk = new Client();
            $beanstalk->connect();
            $beanstalk->watch($tubeName);
            return $beanstalk;
    }

    public static function getTubeStats(string $tubeName)
    {
        $beanstalk = new Client();
        $beanstalk->connect();
        return $beanstalk->statsTube($tubeName);
    }

    public static function disconnectTube(string $tubeName)
    {
        $beanstalk = new Client();
        $beanstalk->connect();
        $beanstalk->useTube($tubeName);
        $beanstalk->disconnect();
    }
}