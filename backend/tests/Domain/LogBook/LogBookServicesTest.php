<?php

declare(strict_types=1);

namespace Tests\Domain\LogBook;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\SystemClock;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\FieldParser;
use App\Domain\LogBook\AccountSyringeService;
use App\Domain\LogBook\LogEntryService;
use App\Domain\LogBook\LogPeptideService;
use App\Domain\LogBook\PersonService;
use App\Domain\LogBook\VialAssignmentService;
use App\Domain\LogBook\VialNamer;
use App\Domain\LogBook\VialService;
use App\Infrastructure\Persistence\UserMigrator;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class LogBookServicesTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $path = sys_get_temp_dir() . '/logbook-' . bin2hex(random_bytes(4)) . '.sqlite';
        (new UserMigrator())->migrate($path);
        $this->pdo = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    public function testDuplicatePersonAndPeptideNamesRejected(): void
    {
        $ids = new IdGenerator();
        $clock = new SystemClock();
        $assignment = new VialAssignmentService(
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\VialPeopleRepository(),
            new VialNamer(),
            $clock,
        );
        $people = new PersonService($assignment, $ids, $clock);
        $peptides = new LogPeptideService(new \App\Domain\LogBook\LogPeptideRepository(), $ids);

        $people->create($this->pdo, FieldParser::from(['name' => 'Noah']));
        $this->expectException(ValidationException::class);
        $people->create($this->pdo, FieldParser::from(['name' => 'noah']));
    }

    public function testSyringePutRejectsNonPositiveValues(): void
    {
        $syringe = new AccountSyringeService(new \App\Domain\LogBook\AccountSyringeRepository());
        $this->expectException(ValidationException::class);
        $syringe->put($this->pdo, FieldParser::from(['volume_ml' => -1, 'capacity_iu' => 50]));
    }

    public function testVialRequiresLidColorAndLogRejectsInvalidIu(): void
    {
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $ids = new IdGenerator();
        $clock = new SystemClock();
        $doses = new DoseCalculator();
        $assignment = new VialAssignmentService(
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\VialPeopleRepository(),
            new VialNamer(),
            $clock,
        );
        $people = new PersonService($assignment, $ids, $clock);
        $peptides = new LogPeptideService(new \App\Domain\LogBook\LogPeptideRepository(), $ids);
        $vials = new VialService(
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\VialPeopleRepository(),
            $assignment,
            new \App\Domain\LogBook\LidColorRepository(),
            $peptides,
            new \App\Domain\LogBook\PersonRepository(),
            $doses,
            new VialNamer(),
            $ids,
            $clock,
            $logger,
        );
        $logs = new LogEntryService(
            new \App\Domain\LogBook\LogEntryRepository(),
            $people,
            $vials,
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\AccountSyringeRepository(),
            $doses,
            $ids,
            $clock,
            $logger,
        );

        $person = $people->create($this->pdo, FieldParser::from(['name' => 'Noah']));
        $this->expectException(ValidationException::class);
        $vials->create($this->pdo, FieldParser::from([
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
        ]));
    }

    public function testLogRejectsNonPositiveIu(): void
    {
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $ids = new IdGenerator();
        $clock = new SystemClock();
        $doses = new DoseCalculator();
        $assignment = new VialAssignmentService(
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\VialPeopleRepository(),
            new VialNamer(),
            $clock,
        );
        $people = new PersonService($assignment, $ids, $clock);
        $peptides = new LogPeptideService(new \App\Domain\LogBook\LogPeptideRepository(), $ids);
        $vials = new VialService(
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\VialPeopleRepository(),
            $assignment,
            new \App\Domain\LogBook\LidColorRepository(),
            $peptides,
            new \App\Domain\LogBook\PersonRepository(),
            $doses,
            new VialNamer(),
            $ids,
            $clock,
            $logger,
        );
        $logs = new LogEntryService(
            new \App\Domain\LogBook\LogEntryRepository(),
            $people,
            $vials,
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\AccountSyringeRepository(),
            $doses,
            $ids,
            $clock,
            $logger,
        );

        $person = $people->create($this->pdo, FieldParser::from(['name' => 'Noah']));
        $vial = $vials->create($this->pdo, FieldParser::from([
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ]));
        $this->expectException(ValidationException::class);
        $logs->create($this->pdo, FieldParser::from([
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 0,
            'logged_at' => '2026-08-20T10:00',
        ]));
    }

    public function testHappyPathCreatesDefaultsListAndDeletes(): void
    {
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $ids = new IdGenerator();
        $clock = new SystemClock();
        $doses = new DoseCalculator();
        $assignment = new VialAssignmentService(
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\VialPeopleRepository(),
            new VialNamer(),
            $clock,
        );
        $people = new PersonService($assignment, $ids, $clock);
        $peptides = new LogPeptideService(new \App\Domain\LogBook\LogPeptideRepository(), $ids);
        $vials = new VialService(
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\VialPeopleRepository(),
            $assignment,
            new \App\Domain\LogBook\LidColorRepository(),
            $peptides,
            new \App\Domain\LogBook\PersonRepository(),
            $doses,
            new VialNamer(),
            $ids,
            $clock,
            $logger,
        );
        $logs = new LogEntryService(
            new \App\Domain\LogBook\LogEntryRepository(),
            $people,
            $vials,
            new \App\Domain\LogBook\VialRepository(),
            new \App\Domain\LogBook\AccountSyringeRepository(),
            $doses,
            $ids,
            $clock,
            $logger,
        );

        $person = $people->create($this->pdo, FieldParser::from(['name' => 'Noah']));
        $vial = $vials->create($this->pdo, FieldParser::from([
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ]));
        $defaults = $logs->defaults($this->pdo, $person['id']);
        $this->assertNull($defaults['iu']);
        $entry = $logs->create($this->pdo, FieldParser::from([
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 15,
            'logged_at' => '2026-08-20T10:00',
        ]));
        $listed = $logs->list($this->pdo, FieldParser::from(['person_id' => $person['id']]));
        $this->assertCount(1, $listed);
        $logs->delete($this->pdo, $entry['id']);
        $this->assertSame([], $logs->list($this->pdo, FieldParser::from(['person_id' => $person['id']])));
        $people->remove($this->pdo, $person['id']);
        $people->restore($this->pdo, $person['id']);
        $people->patch($this->pdo, $person['id'], FieldParser::from(['name' => 'Nate']));
        $peptides->remove($this->pdo, $peptides->create($this->pdo, FieldParser::from(['name' => 'Hidden']))['id']);
    }
}