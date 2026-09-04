<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Partner;

use App\Application\Port\RegisteredPartner;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;
use App\Infrastructure\Partner\HttpPartnerGateway;
use App\Infrastructure\Partner\PartnerResponseParser;
use App\Tests\Support\QuoteRequestBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\CurlHttpClient;

/**
 * L6. Four partners at 1.500 ms resolve in under 2.000 ms of wall clock, using
 * production timeout values. This is the proof that the calls are concurrent
 * (specs/06-testing.md section 4, specs/04-providers.md section 5.3).
 *
 * Tagged `timing` so the fast loop can exclude it with `--exclude-group timing`.
 */
#[Group('timing')]
final class ConcurrentDispatchTimingTest extends TestCase
{
    /** @var list<resource> */
    private array $processes = [];

    /** @var list<string> */
    private array $logFiles = [];

    #[Test]
    public function four_partners_at_1500_ms_resolve_in_under_2000_ms(): void
    {
        $ports = $this->startServers();
        $gateway = new HttpPartnerGateway(new CurlHttpClient(), new PartnerResponseParser());
        $partners = $this->partners($ports);

        $startedNs = hrtime(true);
        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            $partners,
            3000,
        );
        $elapsedMs = (int) ((hrtime(true) - $startedNs) / 1_000_000);

        self::assertCount(4, $outcomes);
        foreach ($outcomes as $outcome) {
            self::assertSame(PartnerStatus::Ok, $outcome->status, $outcome->partnerId->value.' was '.$outcome->status->value);
            self::assertNotNull($outcome->offer);
        }
        self::assertLessThan(2000, $elapsedMs, sprintf('Comparison took %d ms; calls were not concurrent.', $elapsedMs));
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            $status = proc_get_status($process);
            if (function_exists('posix_kill')) {
                @posix_kill($status['pid'], SIGTERM);
            }
            proc_terminate($process);
            proc_close($process);
        }

        foreach ($this->logFiles as $log) {
            @unlink($log);
        }

        parent::tearDown();
    }

    /**
     * @return list<int>
     */
    private function startServers(): array
    {
        $router = dirname(__DIR__, 2).'/Support/slow_partner_server.php';
        $ports = [];

        for ($i = 0; $i < 4; ++$i) {
            $port = $this->freePort();
            $log = tempnam(sys_get_temp_dir(), 'partner-srv-');
            if (false === $log) {
                throw new RuntimeException('Could not create a log file for the timing server.');
            }
            $this->logFiles[] = $log;

            $process = proc_open(
                [PHP_BINARY, '-S', '127.0.0.1:'.$port, $router],
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $log, 'w'],
                    2 => ['file', $log, 'w'],
                ],
                $pipes,
                null,
                null,
                ['bypass_shell' => true],
            );

            if (!is_resource($process)) {
                throw new RuntimeException('Could not start php -S for the timing test.');
            }

            $this->processes[] = $process;
            fclose($pipes[0]);
            $ports[] = $port;
        }

        foreach ($ports as $port) {
            $this->waitForPort($port);
        }

        return $ports;
    }

    /**
     * @param list<int> $ports
     *
     * @return list<RegisteredPartner>
     */
    private function partners(array $ports): array
    {
        $ids = ['aurum', 'bastion', 'celeris', 'dorsal'];
        $partners = [];

        foreach ($ids as $i => $id) {
            $partners[] = new RegisteredPartner(
                new PartnerId($id),
                $id,
                sprintf('http://127.0.0.1:%d/sim/partners/%s/quote', $ports[$i], $id),
                2000,
            );
        }

        return $partners;
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if (false === $socket) {
            throw new RuntimeException('Could not allocate a TCP port.');
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if (!is_string($name) || 1 !== preg_match('/:(\d+)$/', $name, $matches)) {
            throw new RuntimeException('Could not read the allocated TCP port.');
        }

        return (int) $matches[1];
    }

    private function waitForPort(int $port): void
    {
        $deadline = hrtime(true) + 5_000_000_000;

        do {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }
            usleep(20_000);
        } while (hrtime(true) < $deadline);

        throw new RuntimeException(sprintf('Timing server on port %d did not start: %s', $port, $errstr));
    }
}
