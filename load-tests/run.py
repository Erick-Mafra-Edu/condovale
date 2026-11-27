"""Run a disposable benchmark and collect resource samples; Python 3.10+."""
import argparse
import csv
import json
import os
from pathlib import Path
import platform
import re
import secrets
import subprocess
import threading
import time
from datetime import datetime, timezone
from report import main as generate_report

ROOT = Path(__file__).resolve().parent
PROJECT = f"condovale-benchmark-{os.getpid()}"
COMPOSE = ["docker", "compose", "-p", PROJECT, "-f", str(ROOT / "docker-compose.infra.yml")]


def command(*args, check=True, capture=False, env=None):
    return subprocess.run([*COMPOSE, *args], check=check, text=True, capture_output=capture, env=env)


def utc():
    return datetime.now(timezone.utc).isoformat()


def size(value):
    match = re.match(r"\s*([\d.]+)\s*([A-Za-z]+)", value)
    if not match:
        raise ValueError(value)
    amount, unit = match.groups()
    return float(amount) * {"B": 1, "kB": 1e3, "KB": 1e3, "MB": 1e6, "MiB": 2**20, "GB": 1e9, "GiB": 2**30}.get(unit, 1)


def collect(stop, output, phase):
    fields = ["timestamp_utc", "phase", "service", "cpu_percent", "cpu_cores", "memory_bytes", "memory_limit_bytes", "net_rx_bytes", "net_tx_bytes", "block_read_bytes", "block_write_bytes", "pids", "restarts", "cpu_throttled_us", "mysql_threads_connected", "mysql_questions", "mysql_slow_queries", "mysql_innodb_buffer_pool_reads", "mysql_innodb_row_lock_waits", "mysql_com_select", "mysql_com_insert", "mysql_com_update", "mysql_com_delete", "mysql_innodb_data_reads", "mysql_innodb_data_writes"]
    with output.open("w", newline="", encoding="utf-8") as stream:
        writer = csv.DictWriter(stream, fieldnames=fields)
        writer.writeheader()
        while not stop.is_set():
            at = utc()
            for service in ("api", "mysql"):
                try:
                    cid = command("ps", "-q", service, capture=True).stdout.strip()
                    raw = subprocess.run(["docker", "stats", "--no-stream", "--format", "{{json .}}", cid], capture_output=True, text=True, check=True).stdout.strip()
                    data = json.loads(raw)
                    mem, limit = data["MemUsage"].split("/")
                    rx, tx = data["NetIO"].split("/")
                    read, write = data["BlockIO"].split("/")
                    inspect = json.loads(subprocess.run(["docker", "inspect", cid], capture_output=True, text=True, check=True).stdout)[0]
                    throttled = None
                    result = subprocess.run(["docker", "exec", cid, "cat", "/sys/fs/cgroup/cpu.stat"], capture_output=True, text=True)
                    for line in result.stdout.splitlines():
                        if line.startswith("throttled_usec "):
                            throttled = int(line.split()[1])
                    row = {"timestamp_utc": at, "phase": phase[0], "service": service, "cpu_percent": float(data["CPUPerc"].strip("%")), "cpu_cores": float(data["CPUPerc"].strip("%")) / 100, "memory_bytes": size(mem), "memory_limit_bytes": size(limit), "net_rx_bytes": size(rx), "net_tx_bytes": size(tx), "block_read_bytes": size(read), "block_write_bytes": size(write), "pids": data.get("PIDs"), "restarts": inspect["RestartCount"], "cpu_throttled_us": throttled}
                    if service == "mysql":
                        status = command("exec", "-T", "mysql", "mysql", "-ubenchmark", "-pbenchmark-only", "-N", "-e", "SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Questions','Slow_queries','Innodb_buffer_pool_reads','Innodb_row_lock_waits','Com_select','Com_insert','Com_update','Com_delete','Innodb_data_reads','Innodb_data_writes')", capture=True)
                        row.update({"mysql_" + key.lower(): int(value) for key, value in (line.split("\t") for line in status.stdout.splitlines())})
                    writer.writerow(row)
                    stream.flush()
                except (ValueError, KeyError, subprocess.CalledProcessError) as exc:
                    print(f"Collector sample unavailable for {service}: {exc}", flush=True)
            stop.wait(2)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--profile", choices=["small", "medium", "large"], default="small")
    parser.add_argument("--scenario", choices=["smoke", "baseline", "short-baseline", "stress", "spike", "soak", "mixed"], default="smoke")
    parser.add_argument("--duration", default="20s")
    parser.add_argument("--rate", type=int, default=5)
    parser.add_argument("--seed", type=int, default=2026)
    args = parser.parse_args()
    if args.scenario == "soak" and args.duration not in ("30m", "45m", "60m"):
        parser.error("soak duration must be 30m, 45m or 60m")
    output = ROOT / "results" / datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    output.mkdir(parents=True, exist_ok=False)
    env = os.environ.copy()
    env['BENCHMARK_PASSWORD'] = secrets.token_urlsafe(32)
    env.update({"K6_SCENARIO": args.scenario, "K6_DURATION": args.duration, "K6_RATE": str(args.rate), "BENCHMARK_USERS": str({"small": 100, "medium": 1200, "large": 5000}[args.profile])})
    docker_info = subprocess.run(["docker", "info", "--format", "{{json .}}"], capture_output=True, text=True, check=True)
    info = json.loads(docker_info.stdout)
    profile_counts = {"small": (100, 50, 500, 300, 450, 60), "medium": (1200, 500, 10000, 6000, 9000, 500), "large": (5000, 2000, 50000, 30000, 45000, 2000)}
    metadata = {"started_at_utc": utc(), "commit": subprocess.run(["git", "rev-parse", "HEAD"], capture_output=True, text=True).stdout.strip(), "host": platform.platform(), "hostname": platform.node(), "machine": platform.machine(), "cpu_count": os.cpu_count(), "docker_version": info.get("ServerVersion"), "docker_cpus": info.get("NCPU"), "docker_total_memory_bytes": info.get("MemTotal"), "compose_project": PROJECT, "scenario": args.scenario, "profile": args.profile, "profile_counts": dict(zip(('users', 'units', 'reservations', 'occurrences', 'histories', 'notices'), profile_counts[args.profile])), "seed": args.seed, "requested_rate": args.rate, "limits": {k: env.get(k, default) for k, default in [("PHP_CPUS", "1.0"), ("PHP_MEMORY", "512m"), ("MYSQL_CPUS", "1.0"), ("MYSQL_MEMORY", "768m")]}}
    (output / "metadata.json").write_text(json.dumps(metadata, indent=2), encoding="utf-8")
    stop = threading.Event()
    phase = ["warmup"]
    collector = None
    try:
        command("down", "-v", "--remove-orphans", check=False, env=env)
        command("up", "-d", "--build", "mysql", "api", env=env)
        command("exec", "-T", "api", "php", "artisan", "migrate:fresh", "--force", env=env)
        command("exec", "-T", "api", "php", "artisan", "benchmark:prepare", args.profile, f"--seed={args.seed}", env=env)
        command("exec", "-T", "api", "php", "artisan", "config:cache", env=env)
        command("exec", "-T", "api", "php", "artisan", "route:cache", env=env)
        collector = threading.Thread(target=collect, args=(stop, output / "resource-usage.csv", phase), daemon=True)
        collector.start()
        command("--profile", "load", "run", "--rm", "-e", "K6_SCENARIO=smoke", "-e", "K6_DURATION=10s", "k6", check=True, env=env)
        phase[0] = "measurement"
        metadata["measurement_started_at_utc"] = utc()
        with (output / "k6.log").open("w", encoding="utf-8") as log:
            k6 = subprocess.run([*COMPOSE, "--profile", "load", "run", "--rm", "k6"], env=env, stdout=log, stderr=subprocess.STDOUT)
        metadata["measurement_ended_at_utc"] = utc()
        phase[0] = "recovery"
        time.sleep(5)
        # The k6 container writes into the mounted results directory. Keep each run separate.
        for name in ("k6-summary.json", "k6-raw.json"):
            source = ROOT / "results" / name
            if source.exists():
                source.replace(output / name)
        metadata["k6_exit_code"] = k6.returncode
        (output / "metadata.json").write_text(json.dumps(metadata, indent=2), encoding="utf-8")
        stop.set()
        collector.join(timeout=10)
        generate_report(output)
        return k6.returncode
    finally:
        stop.set()
        if collector:
            collector.join(timeout=10)
        (output / "compose.log").write_text(command("logs", "--no-color", check=False, capture=True, env=env).stdout, encoding="utf-8")
        command("down", "-v", "--remove-orphans", check=False, env=env)


if __name__ == "__main__":
    raise SystemExit(main())
