<?php

declare(strict_types=1);

/**
 * Fail the test run when Clover line coverage of production source is below 95%.
 */
$cloverPath = dirname(__DIR__) . '/coverage/clover.xml';
if (!is_file($cloverPath)) {
    fwrite(STDERR, "Missing coverage/clover.xml. Run PHPUnit with --coverage-clover first.\n");
    exit(1);
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "Unable to parse coverage/clover.xml.\n");
    exit(1);
}

$metrics = $xml->project->metrics ?? null;
if ($metrics === null) {
    fwrite(STDERR, "Clover file has no project metrics.\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements === 0 ? 0.0 : ($covered / $statements) * 100;
$min = 95.0;

printf("Line coverage: %.2f%% (%d/%d statements). Floor: %.1f%%.\n", $percent, $covered, $statements, $min);

if ($percent + 0.0001 < $min) {
    fwrite(STDERR, sprintf("Coverage %.2f%% is below the %.1f%% floor.\n", $percent, $min));
    exit(1);
}
