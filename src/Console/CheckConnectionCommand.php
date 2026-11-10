<?php

declare(strict_types=1);

namespace Beziworld\Unifi\Console;

use Beziworld\Unifi\Exceptions\UnifiException;
use Beziworld\Unifi\UnifiManager;
use Illuminate\Console\Command;

/**
 * Verifies the credentials of a configured UniFi controller.
 *
 * The official API is checked when an API key is configured, the classic
 * API when a username and password are configured.
 */
final class CheckConnectionCommand extends Command
{
    /** @var string */
    protected $signature = 'unifi:check {connection? : Connection name from config/unifi.php}';

    /** @var string */
    protected $description = 'Check the credentials of a UniFi controller connection';

    public function handle(UnifiManager $manager): int
    {
        $argument = $this->input->getArgument('connection');
        $name = is_string($argument) && $argument !== '' ? $argument : $manager->getDefaultConnection();
        $failed = false;

        try {
            $client = $manager->connection($name);
        } catch (UnifiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $config = $client->config();

        if ($config->hasApiKey()) {
            try {
                $info = $client->info();
                $this->components->twoColumnDetail('Official API', 'Network ' . $info->applicationVersion);

                foreach ($client->sites()->all() as $site) {
                    $this->components->twoColumnDetail('  ' . $site->name . ' (' . $site->internalReference . ')', $site->id);
                }
            } catch (UnifiException $exception) {
                $failed = true;
                $this->components->error('Official API: ' . $exception->getMessage());
            }
        }

        if ($config->hasCredentials()) {
            try {
                $classic = $client->classic();
                $sites = $classic->sites()->all();
                $this->components->twoColumnDetail('Classic API', sprintf('%s, %d site(s)', $classic->api()->isUnifiOs() ? 'UniFi OS' : 'legacy controller', count($sites)));
            } catch (UnifiException $exception) {
                $failed = true;
                $this->components->error('Classic API: ' . $exception->getMessage());
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        $this->components->info(sprintf('Connection "%s" works.', $name));

        return self::SUCCESS;
    }
}
