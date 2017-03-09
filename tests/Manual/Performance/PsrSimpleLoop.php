<?php
declare(strict_types = 1);


namespace Tests\Manual\Performance;

use Paymaxi\Component\CircuitBreaker\Core\CircuitBreaker;
use Paymaxi\Component\CircuitBreaker\Storage\Adapter\PsrCacheAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

require dirname(__FILE__) . '/../../../vendor/autoload.php';

$callCount = 10000;

$cb = new CircuitBreaker(new PsrCacheAdapter(new ArrayAdapter()), 30, 3600);

$start = microtime(true);
for ($i = 0; $i < $callCount; $i++) {
    $serviceName = "someServiceName" . ($i % 5);
    $cb->isAvailable($serviceName);
    if (mt_rand(1, 1000) > 700) {
        $cb->reportSuccess($serviceName);
    } else {
        $cb->reportFailure($serviceName);
    }
}
$stop = microtime(true);

print_r(array(
    sprintf("Total time for %d calls: %.5f", $callCount, $stop - $start),
));
