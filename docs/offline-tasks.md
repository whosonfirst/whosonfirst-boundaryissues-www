# Boundary Issues Offline tasks

Boundary Issues has a bunch of places where a task might take longer than  a "reasonable" amount of time to complete. Or maybe we want to run a task many times over that, collectively, will take a long time. For these situations we have offline tasks.

## How does it work?

To run offline tasks, we need to install a few other services:

* Redis to accumulate and dispatch the tasks
* Logstash to log scheduling and execution of tasks
* Gearman to run worker processes
* Supervisor to keep the Gearman workers running

Within the web application, you can check on the status of offline tasks at the path `/offlinetasks`.

## Where are the configs?

* Logstash: `/etc/logstash/conf.d/flamework-logstash.conf`

## Where are the logs?

* Logged messages: `/usr/local/logstash/flamework-offline-tasks-YYYY-MM-DD.conf`
* Logstash service logs: `/var/log/logstash/logstash.log`

## How to stop/start/restart?

* Don't use the upstart service
* Do use: `sudo /etc/init.d/logstash [start|stop|restart]`
* Supervisor: `sudo supervisorctl [start|stop|restart] all`

## omgwtf nothing is working

First, subscribe to a Redis channel.
```
cd /usr/local/mapzen/redis-tools
./bin/subscribe omgwtf
```

Next, publish something to it.
```
cd /usr/local/mapzen/redis-tools
./bin/publish omgwtf hello
```

Did you see anything come through on the subscribe end of things? Ok, then let's check that other things are working.

* Check the Logstash service logs for error messages
* Check the logged messages to see if they got through
* Try a running minimal test script in the `whosonfirst-boundary-issues/bin` folder:

```
<?php

include __DIR__ . '/init_local.php';
loadlib('offline_tasks_gearman');
loadlib('offline_tasks');


$rsp = offline_tasks_schedule_task('omgwtf', array('ok' => -1));
print_r($rsp);
```
