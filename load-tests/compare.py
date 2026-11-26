"""Compare completed benchmark summaries on the same host/profile/scenario."""
import json
from pathlib import Path
import sys


def main(paths):
    runs = [json.loads(Path(path).read_text(encoding='utf-8')) for path in paths]
    keys = ('host', 'hostname', 'profile', 'seed', 'scenario', 'docker_cpus', 'docker_total_memory_bytes')
    for key in keys:
        if len({run['metadata'].get(key) for run in runs}) != 1:
            raise SystemExit(f'Incompatible {key}; results must not be compared as equivalent.')
    print('| Run | PHP CPU/RAM | MySQL CPU/RAM | RPS | p95 ms | Errors | Dropped |')
    print('| --- | --- | --- | ---: | ---: | ---: | ---: |')
    for path, run in zip(paths, runs):
        resources = run['resources']
        app = resources['api']
        mysql = resources['mysql']
        print(f"| {Path(path).parent.name} | {app['cpu_mean_cores']} / {app['memory_peak_bytes']} | {mysql['cpu_mean_cores']} / {mysql['memory_peak_bytes']} | {run['completed_rps']} | {run['latency_ms']['p(95)']} | {run['http_failure_rate']} | {run['dropped_iterations']} |")


if __name__ == '__main__':
    if len(sys.argv) < 3:
        raise SystemExit('Usage: python load-tests/compare.py RUN1/summary.json RUN2/summary.json')
    main(sys.argv[1:])
