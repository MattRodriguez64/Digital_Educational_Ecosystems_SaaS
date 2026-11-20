"""Docker counters: 100% CPU = one core; memory excludes inactive file cache."""
def university(labels):
    import re
    name = labels.get('com.docker.stack.namespace', '')
    return name if re.fullmatch(r'univ-[a-z0-9][a-z0-9-]*', name) else None


def counters(stats, now):
    memory = stats['memory_stats']
    cpu = stats['cpu_stats']['cpu_usage']['total_usage']
    cache = memory.get('stats', {}).get('inactive_file',
            memory.get('stats', {}).get('total_inactive_file', 0))
    network = stats.get('networks', {})
    return dict(time=now, cpu=cpu, memory=max(0, memory['usage'] - cache),
                rx=sum(v['rx_bytes'] for v in network.values()),
                tx=sum(v['tx_bytes'] for v in network.values()))


def measure(current, previous):
    result = dict(memory_mib=current['memory'] / 2**20, cpu_pct=None,
                  rx_kib_s=None, tx_kib_s=None, core_hours=0.0,
                  gib_hours=0.0, traffic_gib=0.0, measured_seconds=0.0)
    if not previous:
        return result
    seconds = current['time'] - previous['time']
    if not 0 < seconds <= 90 or any(current[k] < previous[k] for k in ('cpu', 'rx', 'tx')):
        return result
    cpu = current['cpu'] - previous['cpu']
    rx, tx = (current[k] - previous[k] for k in ('rx', 'tx'))
    result.update(cpu_pct=100 * cpu / 1e9 / seconds,
                  rx_kib_s=rx / 1024 / seconds, tx_kib_s=tx / 1024 / seconds,
                  core_hours=cpu / 3.6e12,
                  gib_hours=(current['memory'] + previous['memory']) / 2 / 2**30 * seconds / 3600,
                  traffic_gib=(rx + tx) / 2**30, measured_seconds=seconds)
    return result


def aggregate(measures):
    fields = ('memory_mib', 'cpu_pct', 'rx_kib_s', 'tx_kib_s',
              'core_hours', 'gib_hours', 'traffic_gib')
    return {key: None if any(m[key] is None for m in measures)
            else sum(m[key] for m in measures) for key in fields}
