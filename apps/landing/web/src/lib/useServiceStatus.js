import { useEffect, useState } from 'react';
import { checkHealth } from './api.js';

export function useServiceStatus(intervalMs = 60000) {
  const [state, setState] = useState('checking');

  useEffect(() => {
    let stopped = false;

    async function check() {
      if (document.hidden) return;
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 4000);
      const ok = await checkHealth(controller.signal);
      clearTimeout(timer);
      if (!stopped) setState(ok ? 'ok' : 'down');
    }

    check();
    const id = setInterval(check, intervalMs);
    return () => {
      stopped = true;
      clearInterval(id);
    };
  }, [intervalMs]);

  return state;
}