<?php

namespace Modules\AdminFaker\Console\Commands;

use Illuminate\Console\Command;
use Modules\AdminFaker\Support\AdminFakerService;

class RefreshAdminFakerCommand extends Command
{
    protected $signature = 'admin-faker:refresh {--no-clear : Skip clearing existing demo data before seeding}';

    protected $description = 'Refresh Admin Faker demo data for the first user account';

    public function handle(AdminFakerService $faker): int
    {
        $result = $faker->seedForFirstUser(! $this->option('no-clear'));

        $user = (array) ($result['user'] ?? []);
        $counts = (array) ($result['counts'] ?? []);

        $this->info(sprintf(
            'Admin Faker refreshed for user #%s (%s).',
            (string) ($user['id'] ?? '?'),
            (string) ($user['email'] ?? 'n/a')
        ));

        foreach ($counts as $key => $value) {
            $this->line(sprintf('- %s: %s', $key, (string) $value));
        }

        return self::SUCCESS;
    }
}
