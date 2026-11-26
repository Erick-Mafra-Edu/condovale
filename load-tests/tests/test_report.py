import csv
from datetime import datetime, timedelta, timezone
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest


REPORT = Path(__file__).resolve().parents[1] / 'report.py'
spec = importlib.util.spec_from_file_location('benchmark_report', REPORT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class ReportTest(unittest.TestCase):
    def test_smoke_report_does_not_claim_capacity(self):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            start = datetime(2026, 1, 1, tzinfo=timezone.utc)
            (folder / 'metadata.json').write_text(json.dumps({'measurement_started_at_utc': start.isoformat(), 'measurement_ended_at_utc': (start + timedelta(seconds=10)).isoformat(), 'started_at_utc': start.isoformat(), 'commit': 'abc', 'host': 'test', 'cpu_count': 2, 'scenario': 'smoke', 'profile': 'small', 'limits': {}}))
            (folder / 'k6-summary.json').write_text(json.dumps({'metrics': {'http_reqs': {'values': {'count': 100}}, 'http_req_duration': {'values': {'avg': 20, 'p(95)': 40, 'p(99)': 50}}, 'http_req_failed': {'values': {'rate': 0}}, 'checks': {'values': {'rate': 1}}}}))
            with (folder / 'resource-usage.csv').open('w', newline='') as stream:
                writer = csv.DictWriter(stream, fieldnames=['phase', 'service', 'cpu_cores', 'memory_bytes', 'restarts', 'cpu_throttled_us'])
                writer.writeheader()
                for service in ('api', 'mysql'):
                    writer.writerow({'phase': 'measurement', 'service': service, 'cpu_cores': 0.5, 'memory_bytes': 1024, 'restarts': 0, 'cpu_throttled_us': 0})
            module.main(folder)
            result = json.loads((folder / 'summary.json').read_text())
            self.assertEqual(result['completed_rps'], 10)
            self.assertEqual(result['cpu_efficiency_requests_per_core_second'], 10)
            self.assertIsNone(result['last_sustainable_iterations_per_second'])
            self.assertTrue((folder / 'latency.svg').exists())


if __name__ == '__main__':
    unittest.main()
