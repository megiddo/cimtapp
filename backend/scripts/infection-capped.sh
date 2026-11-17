#!/usr/bin/env bash
# Optional v2 gate helper: kill Infection after MUTATION_TIME_LIMIT_SEC (default 30 minutes).
set -euo pipefail
cd "$(dirname "$0")/.."
LIMIT="${MUTATION_TIME_LIMIT_SEC:-1800}"
echo "Infection watchdog: ${LIMIT}s max"
exec perl -e '
  my $limit = shift;
  alarm $limit;
  $SIG{ALRM} = sub {
    print STDERR "mutation watchdog: Infection exceeded ${limit}s; killed.\n";
    exit 124;
  };
  exec @ARGV or die "exec failed: $!\n";
' "$LIMIT" php -d memory_limit=512M vendor/bin/infection --threads=max --min-msi=80 --min-covered-msi=80