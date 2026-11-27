"""Compare completed benchmark summaries on the same host/profile/scenario."""
import json
from pathlib import Path
import re
import sys

RESULTS = Path(__file__).resolve().parent / 'results'


def summary_path(run_id):
    if not re.fullmatch(r'[A-Za-z0-9_-]+', run_id):
        raise SystemExit('Use only a run directory name from load-tests/results.')
    return RESULTS / run_id / 'summary.json'


def main(run_ids):
    runs = [json.loads(summary_path(run_id).read_text(encoding='utf-8')) for run_id in run_ids]
    keys = ('host', 'hostname', 'profile', 'seed', 'scenario', 'docker_cpus', 'docker_total_memory_bytes')
    for key in keys:
        if len({run['metadata'].get(key) for run in runs}) != 1:
            raise SystemExit(f'Incompatible {key}; results must not be compared as equivalent.')
    print('| Run | PHP CPU/RAM | MySQL CPU/RAM | RPS | p95 ms | Errors | Dropped |')
    print('| --- | --- | --- | ---: | ---: | ---: | ---: |')
    for run_id, run in zip(run_ids, runs):
        resources = run['resources']
        app = resources['api']
        mysql = resources['mysql']
        print(f"| {run_id} | {app['cpu_mean_cores']} / {app['memory_peak_bytes']} | {mysql['cpu_mean_cores']} / {mysql['memory_peak_bytes']} | {run['completed_rps']} | {run['latency_ms']['p(95)']} | {run['http_failure_rate']} | {run['dropped_iterations']} |")


if __name__ == '__main__':
    if len(sys.argv) < 3:
        raise SystemExit('Usage: python load-tests/compare.py RUN1 RUN2')
    main(sys.argv[1:])
