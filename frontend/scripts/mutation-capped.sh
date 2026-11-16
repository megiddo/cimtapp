#!/usr/bin/env bash
# Optional v2 gate helper: kill Stryker after MUTATION_TIME_LIMIT_SEC (default 30 minutes).
set -euo pipefail
cd "$(dirname "$0")/.."
LIMIT="${MUTATION_TIME_LIMIT_SEC:-1800}"
echo "Stryker watchdog: ${LIMIT}s max"
exec perl -e '
  my $limit = shift;
  alarm $limit;
  $SIG{ALRM} = sub {
    print STDERR "mutation watchdog: Stryker exceeded ${limit}s; killed.\n";
    exit 124;
  };
  exec @ARGV or die "exec failed: $!\n";
' "$LIMIT" npm run mutation