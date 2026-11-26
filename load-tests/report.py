"""Generate evidence only from k6 and resource collector output."""
import csv
from datetime import datetime
import json
import math
from pathlib import Path
import statistics
from xml.sax.saxutils import escape


def metric(data, name, key):
    return data.get('metrics', {}).get(name, {}).get('values', {}).get(key)


def percentile(values, p):
    if not values:
        return None
    ordered = sorted(values)
    return ordered[math.ceil(len(ordered) * p) - 1]


def svg(name, values, color, title):
    valid = [(i, v) for i, v in enumerate(values) if v is not None]
    width, height = 800, 250
    if not valid:
        return f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}"><text x="20" y="30">{escape(title)}: sem amostras</text></svg>'
    maximum = max(v for _, v in valid) or 1
    points = ' '.join(f'{40 + i * 730 / max(1, len(values)-1):.1f},{210 - v * 170 / maximum:.1f}' for i, v in valid)
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" role="img" aria-label="{escape(title)}"><rect width="100%" height="100%" fill="white"/><text x="40" y="25" font-size="18">{escape(title)}</text><line x1="40" y1="210" x2="770" y2="210" stroke="#777"/><polyline points="{points}" fill="none" stroke="{color}" stroke-width="3"/><text x="40" y="235" font-size="12">{escape(name)} · máximo {maximum:.2f}</text></svg>'


def cpu_memory_svg(rows):
    cpus = [float(row['cpu_cores']) for row in rows if row['service'] == 'api']
    memory = [float(row['memory_bytes']) / 2**20 for row in rows if row['service'] == 'api']
    if not cpus:
        return svg('amostra', [], '#dc2626', 'CPU e RAM Apache/PHP')
    def line(values, maximum):
        return ' '.join(f'{40 + i * 730 / max(1, len(values)-1):.1f},{200 - value * 160 / (maximum or 1):.1f}' for i, value in enumerate(values))
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="800" height="250" role="img" aria-label="CPU e RAM Apache/PHP"><rect width="100%" height="100%" fill="white"/><text x="40" y="25" font-size="18">CPU e RAM Apache/PHP</text><polyline points="{line(cpus, max(cpus))}" fill="none" stroke="#dc2626" stroke-width="3"/><polyline points="{line(memory, max(memory))}" fill="none" stroke="#2563eb" stroke-width="3"/><text x="40" y="235" font-size="12">CPU (vermelho): {max(cpus):.2f} núcleos · RAM (azul): {max(memory):.1f} MiB</text></svg>'


def main(folder):
    folder = Path(folder)
    metadata = json.loads((folder / 'metadata.json').read_text(encoding='utf-8'))
    k6 = json.loads((folder / 'k6-summary.json').read_text(encoding='utf-8'))
    with (folder / 'resource-usage.csv').open(newline='', encoding='utf-8') as stream:
        rows = list(csv.DictReader(stream))
    measured = [row for row in rows if row['phase'] == 'measurement']
    resources = {}
    for service in ('api', 'mysql'):
        selected = [row for row in measured if row['service'] == service]
        cpus = [float(row['cpu_cores']) for row in selected]
        mem = [float(row['memory_bytes']) for row in selected]
        resources[service] = {'samples': len(selected), 'cpu_mean_cores': statistics.mean(cpus) if cpus else None, 'cpu_p95_cores': percentile(cpus, .95), 'cpu_max_cores': max(cpus) if cpus else None, 'memory_peak_bytes': max(mem) if mem else None, 'restarts_max': max((int(row['restarts']) for row in selected), default=None), 'cpu_throttled_us_end': next((int(row['cpu_throttled_us']) for row in reversed(selected) if row['cpu_throttled_us']), None)}
        if selected:
            for field in ('net_rx_bytes', 'net_tx_bytes', 'block_read_bytes', 'block_write_bytes', 'cpu_throttled_us'):
                if field in selected[0]:
                    readings = [float(row[field]) for row in selected if row.get(field)]
                    resources[service][field + '_delta'] = max(0, readings[-1] - readings[0]) if len(readings) > 1 else None
    mysql_rows = [row for row in measured if row['service'] == 'mysql']
    mysql_stats = {}
    for field in ('mysql_threads_connected', 'mysql_questions', 'mysql_slow_queries', 'mysql_innodb_buffer_pool_reads', 'mysql_innodb_row_lock_waits', 'mysql_com_select', 'mysql_com_insert', 'mysql_com_update', 'mysql_com_delete', 'mysql_innodb_data_reads', 'mysql_innodb_data_writes'):
        readings = [float(row[field]) for row in mysql_rows if row.get(field)]
        if field == 'mysql_threads_connected':
            mysql_stats['connections_max'] = max(readings) if readings else None
        else:
            mysql_stats[field.removeprefix('mysql_') + '_delta'] = max(0, readings[-1] - readings[0]) if len(readings) > 1 else None
    duration = (datetime.fromisoformat(metadata['measurement_ended_at_utc']) - datetime.fromisoformat(metadata['measurement_started_at_utc'])).total_seconds()
    completed = metric(k6, 'http_reqs', 'count')
    throughput = completed / duration if completed is not None and duration else None
    total_cpu = sum(item['cpu_mean_cores'] or 0 for item in resources.values())
    mysql_stats['questions_per_second'] = mysql_stats.get('questions_delta') / duration if duration and mysql_stats.get('questions_delta') is not None else None
    summary = {'metadata': metadata, 'duration_seconds': duration, 'http_requests': completed, 'completed_rps': throughput, 'completed_iterations_per_second': metric(k6, 'iterations', 'rate'), 'latency_ms': {key: metric(k6, 'http_req_duration', key) for key in ('avg', 'p(95)', 'p(99)')}, 'http_failure_rate': metric(k6, 'http_req_failed', 'rate'), 'checks_rate': metric(k6, 'checks', 'rate'), 'dropped_iterations': metric(k6, 'dropped_iterations', 'count') or 0, 'resources': resources, 'mysql_status': mysql_stats, 'cpu_efficiency_requests_per_core_second': throughput / total_cpu if throughput is not None and total_cpu else None}
    buckets = {}
    raw = folder / 'k6-raw.json'
    if raw.exists():
        with raw.open(encoding='utf-8') as stream:
            for line in stream:
                try:
                    item = json.loads(line)
                except json.JSONDecodeError:
                    continue
                if item.get('type') != 'Point':
                    continue
                data = item['data']
                second = int(datetime.fromisoformat(data['time'].replace('Z', '+00:00')).timestamp())
                bucket = buckets.setdefault(second, {'latencies': [], 'requests': 0, 'iterations': 0, 'failed': 0, 'dropped': 0})
                if item['metric'] == 'http_req_duration': bucket['latencies'].append(data['value'])
                elif item['metric'] == 'http_reqs': bucket['requests'] += data['value']
                elif item['metric'] == 'iterations': bucket['iterations'] += data['value']
                elif item['metric'] == 'http_req_failed': bucket['failed'] += data['value']
                elif item['metric'] == 'dropped_iterations': bucket['dropped'] += data['value']
    seconds = sorted(buckets)
    p95_series = [percentile(buckets[s]['latencies'], .95) for s in seconds]
    rps_series = [buckets[s]['requests'] for s in seconds]
    (folder / 'latency.svg').write_text(svg('second', p95_series, '#2563eb', 'p95 por segundo (ms)'), encoding='utf-8')
    (folder / 'throughput.svg').write_text(svg('second', rps_series, '#16a34a', 'Requisições concluídas por segundo'), encoding='utf-8')
    (folder / 'cpu-memory.svg').write_text(cpu_memory_svg(measured), encoding='utf-8')
    stage_rates = [5, 10, 20, 40] if metadata['scenario'] in ('baseline', 'short-baseline') else [5, 10, 20, 40, 80, 120] if metadata['scenario'] == 'stress' else []
    stage_seconds = 300 if metadata['scenario'] == 'baseline' else 30 if metadata['scenario'] == 'short-baseline' else 120
    stages = []
    if seconds and stage_rates:
        start = seconds[0]
        for i, rate in enumerate(stage_rates):
            stage_start = start + i * (stage_seconds + 1) + 1
            selected = [buckets[s] for s in seconds if stage_start <= s < stage_start + stage_seconds]
            resource_window = {}
            for service in ('api', 'mysql'):
                samples = [float(row['cpu_cores']) for row in measured if row['service'] == service and stage_start <= datetime.fromisoformat(row['timestamp_utc']).timestamp() < stage_start + stage_seconds]
                resource_window[service] = statistics.mean(samples) if samples else None
            latencies = [x for bucket in selected for x in bucket['latencies']]
            requests = sum(bucket['requests'] for bucket in selected)
            iterations = sum(bucket['iterations'] for bucket in selected)
            failed = sum(bucket['failed'] for bucket in selected)
            dropped = sum(bucket['dropped'] for bucket in selected)
            stages.append({'requested_iterations_per_second': rate, 'completed_iterations_per_second': iterations / stage_seconds, 'completed_http_rps': requests / stage_seconds, 'p95_ms': percentile(latencies, .95), 'http_failure_rate': failed / requests if requests else None, 'dropped_iterations': dropped, 'cpu_mean_cores_by_service': resource_window, 'sustainable': bool(requests and iterations and dropped == 0 and failed / requests < .01 and percentile(latencies, .95) < 800 and iterations / stage_seconds >= rate * .95)})
    summary['stages'] = stages
    summary['last_sustainable_iterations_per_second'] = max((s['requested_iterations_per_second'] for s in stages if s['sustainable']), default=None)
    cpu_ratios = {}
    for service, limit_name in (('api', 'PHP_CPUS'), ('mysql', 'MYSQL_CPUS')):
        mean = resources[service]['cpu_mean_cores']
        limit = float(metadata.get('limits', {}).get(limit_name, 0) or 0)
        if mean is not None and limit:
            cpu_ratios[service] = mean / limit
    bottleneck = max(cpu_ratios, key=cpu_ratios.get) if cpu_ratios else None
    summary['bottleneck_candidate'] = {'service': bottleneck, 'mean_cpu_quota_fraction': cpu_ratios.get(bottleneck)} if bottleneck else None
    summary['recommendation'] = ('Investigar contenção de CPU e consultas lentas em ' + bottleneck + ' antes de aumentar sua cota de CPU.' if bottleneck and cpu_ratios[bottleneck] >= .8 else 'Não há saturação de CPU demonstrada; analisar janelas de latência e contadores do banco antes de dimensionar infraestrutura.')
    if not stages:
        summary['capacity_note'] = 'Capacity cannot be established from this scenario.'
    (folder / 'summary.json').write_text(json.dumps(summary, indent=2), encoding='utf-8')
    lines = ['# Relatório de desempenho', '', f"- Commit: {metadata['commit']}", f"- Início UTC: {metadata['started_at_utc']}", f"- Cenário/perfil: {metadata['scenario']} / {metadata['profile']}", f"- Volumes sintéticos: {metadata.get('profile_counts')}", f"- Duração medida: {duration:.1f} s", f"- Host: {metadata['host']} ({metadata['cpu_count']} CPUs lógicas)", f"- Limites: {metadata['limits']}", f"- Requisições HTTP/s concluídas: {throughput}", f"- Iterações/s concluídas: {summary['completed_iterations_per_second']}", f"- Latência (média/p95/p99 ms): {summary['latency_ms']}", f"- Falhas HTTP: {summary['http_failure_rate']}", f"- Checks: {summary['checks_rate']}", f"- Iterações descartadas: {summary['dropped_iterations']}", f"- Eficiência CPU (req/s/núcleo usado): {summary['cpu_efficiency_requests_per_core_second']}", '', '| Serviço | CPU média (núcleos) | CPU p95 | CPU máxima | Pico de RAM (bytes) | Reinícios |', '| --- | ---: | ---: | ---: | ---: | ---: |']
    for service, values in resources.items(): lines.append(f"| {service} | {values['cpu_mean_cores']} | {values['cpu_p95_cores']} | {values['cpu_max_cores']} | {values['memory_peak_bytes']} | {values['restarts_max']} |")
    lines += ['', '## MySQL', '', f"- Indicadores do banco na janela medida: {mysql_stats}", '', '## Patamares', '', '| Iterações/s solicitadas | Iterações/s concluídas | HTTP req/s | p95 (ms) | Falhas | Descartadas | CPU PHP / MySQL (núcleos) | Sustentável |', '| ---: | ---: | ---: | ---: | ---: | ---: | --- | --- |']
    for stage in stages: lines.append(f"| {stage['requested_iterations_per_second']} | {stage['completed_iterations_per_second']:.2f} | {stage['completed_http_rps']:.2f} | {stage['p95_ms']} | {stage['http_failure_rate']} | {stage['dropped_iterations']} | {stage['cpu_mean_cores_by_service']} | {stage['sustainable']} |")
    lines += ['', f"Maior patamar sustentável observado: {summary['last_sustainable_iterations_per_second'] if stages else 'não determinado'} iterações/s." if stages else 'Capacidade sustentável não determinada neste cenário.', '', f"- Candidato a gargalo por fração média da cota de CPU: {summary['bottleneck_candidate']}", f"- Recomendação condicional: {summary['recommendation']}", '', 'Resultados dependem deste host, perfil de dados, limites e duração. Testes curtos não comprovam capacidade de produção. Investigue também throttling e consultas lentas antes de ajustar infraestrutura.', '']
    text = '\n'.join(lines)
    (folder / 'summary.md').write_text(text, encoding='utf-8')
    (folder / 'capacity-report.md').write_text(text, encoding='utf-8')
